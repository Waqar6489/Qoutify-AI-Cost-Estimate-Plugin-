<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Credit ledger. Rows are only ever added, never edited. Balance = sum of all rows.
 * Every row has a unique idempotency key, so the same event can never be recorded twice.
 */
function qtf_credits_balance( $user_id ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(delta),0) FROM ' . qtf_table( 'credit_ledger' ) . ' WHERE user_id = %d', (int) $user_id ) );
}

function qtf_credits_add( $user_id, $delta, $reason, $idem_key, $args = array() ) {
	global $wpdb;
	$data = array(
		'user_id'    => (int) $user_id,
		'delta'      => (int) $delta,
		'reason'     => $reason,
		'idem_key'   => $idem_key,
		'created_at' => qtf_now(),
	);
	if ( isset( $args['estimate_id'] ) ) {
		$data['estimate_id'] = (int) $args['estimate_id'];
	}
	if ( isset( $args['order_id'] ) ) {
		$data['order_id'] = (int) $args['order_id'];
	}
	if ( ! empty( $args['note'] ) ) {
		$data['note'] = substr( sanitize_text_field( $args['note'] ), 0, 250 );
	}
	// A duplicate idempotency key makes the insert fail on purpose.
	$suppress = $wpdb->suppress_errors( true );
	$res      = $wpdb->insert( qtf_table( 'credit_ledger' ), $data );
	$wpdb->suppress_errors( $suppress );
	return ( false !== $res && 1 === (int) $res );
}

/**
 * Takes credits for an estimate. A lock per user stops two requests from spending the same credits.
 */
function qtf_credits_spend( $user_id, $amount, $estimate_id ) {
	global $wpdb;
	$lock = 'qtf_cr_' . (int) $user_id;
	$got  = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) );
	if ( 1 !== (int) $got ) {
		return false;
	}
	$ok = false;
	if ( qtf_credits_balance( $user_id ) >= (int) $amount ) {
		$ok = qtf_credits_add( $user_id, -1 * (int) $amount, 'spend', 'spend:' . (int) $estimate_id, array( 'estimate_id' => $estimate_id ) );
	}
	$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	return $ok;
}

/**
 * Gives the credits of a failed estimate back, once, and only if they were really taken.
 */
function qtf_credits_refund_estimate( $estimate ) {
	global $wpdb;
	if ( ! $estimate || (int) $estimate->credits_charged < 1 ) {
		return false;
	}
	$spent = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . qtf_table( 'credit_ledger' ) . ' WHERE idem_key = %s', 'spend:' . (int) $estimate->id ) );
	if ( ! (int) $spent ) {
		return false;
	}
	return qtf_credits_add( (int) $estimate->user_id, (int) $estimate->credits_charged, 'refund', 'refund:' . (int) $estimate->id, array( 'estimate_id' => (int) $estimate->id ) );
}

/** Credits shown to the user (never below zero). */
function qtf_get_credits( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	return max( 0, qtf_credits_balance( $user_id ) );
}

/** Can this user open the wizard? Team: always. Public: needs at least the cheapest estimate. */
function qtf_can_start( $user_id = 0 ) {
	return qtf_user_is_staff( $user_id ) || qtf_get_credits( $user_id ) >= qtf_min_cost();
}

function qtf_can_estimate( $user_id = 0 ) {
	return qtf_can_start( $user_id );
}

/* ------------------------------------------------------------- WooCommerce */

// Product field: how many credits one unit of this product gives.
function qtf_product_credit_field() {
	woocommerce_wp_text_input(
		array(
			'id'                => '_qtf_credits',
			'label'             => __( 'Qoutify credits', 'qoutify-estimator' ),
			'description'       => __( 'Credits added to the buyer after payment. Leave empty for normal products.', 'qoutify-estimator' ),
			'desc_tip'          => true,
			'type'              => 'number',
			'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
		)
	);
}
add_action( 'woocommerce_product_options_general_product_data', 'qtf_product_credit_field' );

function qtf_product_credit_save( $post_id ) {
	// WooCommerce checks the nonce and permission before this action runs.
	$value = isset( $_POST['_qtf_credits'] ) ? absint( $_POST['_qtf_credits'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	update_post_meta( $post_id, '_qtf_credits', $value );
}
add_action( 'woocommerce_process_product_meta', 'qtf_product_credit_save' );

/**
 * Credits an order line gives. New orders store the number on the order line itself
 * (_qtf_credits_purchased), so later changes to a product or to the credit price never
 * change an old order. Orders from before this version fall back to the product setting.
 */
function qtf_order_item_credits( $item ) {
	$stored = (int) $item->get_meta( '_qtf_credits_purchased', true );
	if ( $stored > 0 ) {
		return $stored;
	}
	$per = (int) get_post_meta( $item->get_product_id(), '_qtf_credits', true );
	return $per > 0 ? $per * max( 1, (int) $item->get_quantity() ) : 0;
}

/**
 * Add credits after a PAID order. Online payments count at "processing" or "completed".
 * Offline methods (cash, cheque, bank transfer) count only when an admin marks the order "completed".
 * Every grant has a unique key (order + line), so repeated hooks or webhook retries add nothing.
 */
function qtf_credit_order( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}
	$user_id = (int) $order->get_user_id();
	if ( ! $user_id ) {
		return;
	}
	$offline = in_array( $order->get_payment_method(), array( 'cod', 'cheque', 'bacs' ), true );
	if ( $offline && 'completed' !== $order->get_status() ) {
		return;
	}

	$granted = 0;
	foreach ( $order->get_items() as $item_id => $item ) {
		$amount = qtf_order_item_credits( $item );
		if ( $amount < 1 ) {
			continue;
		}
		$added = qtf_credits_add(
			$user_id,
			$amount,
			'purchase',
			'purchase:' . (int) $order_id . ':' . (int) $item_id,
			array(
				'order_id' => (int) $order_id,
				'note'     => $item->get_name(),
			)
		);
		if ( $added ) {
			$granted += $amount;
		}
	}

	// Only the request that really added the credits sends the email, so it is sent once.
	if ( $granted > 0 ) {
		qtf_email_credits_added( $user_id, $granted, (int) $order_id );
	}
}
add_action( 'woocommerce_order_status_processing', 'qtf_credit_order' );
add_action( 'woocommerce_order_status_completed', 'qtf_credit_order' );

function qtf_email_credits_added( $user_id, $credits, $order_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return;
	}
	$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$subject = sprintf( __( 'Your %s credits were added', 'qoutify-estimator' ), $site );
	$message = sprintf(
		"Hi %s,\n\nThank you for your payment (order #%d). We added %s to your account.\nYour balance is now %s.\n\nOpen your credits: %s\n\nNeed help? Email %s\n\n%s",
		qtf_user_first_name( $user ),
		$order_id,
		qtf_credits_label( $credits ),
		qtf_credits_label( max( 0, qtf_credits_balance( $user_id ) ) ),
		qtf_dashboard_url( 'credits' ),
		qtf_support_email(),
		$site
	);
	wp_mail( $user->user_email, $subject, $message );
}

// Refunded or cancelled order: take the credits back.
function qtf_revoke_order( $order_id ) {
	global $wpdb;
	$rows = $wpdb->get_results(
		$wpdb->prepare( 'SELECT * FROM ' . qtf_table( 'credit_ledger' ) . " WHERE order_id = %d AND reason = 'purchase'", (int) $order_id )
	);
	foreach ( $rows as $row ) {
		qtf_credits_add( (int) $row->user_id, -1 * (int) $row->delta, 'revoke', 'revoke:' . (int) $row->id, array( 'order_id' => (int) $order_id ) );
	}
}
add_action( 'woocommerce_order_status_refunded', 'qtf_revoke_order' );
add_action( 'woocommerce_order_status_cancelled', 'qtf_revoke_order' );

/**
 * "Buy" button: add the package and go straight to the checkout (not the cart).
 * The cart is emptied first, so the checkout holds only the chosen package.
 */
function qtf_buy_redirect( $url ) {
	if ( ! empty( $_GET['qtf_buy'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return wc_get_checkout_url();
	}
	return $url;
}
add_filter( 'woocommerce_add_to_cart_redirect', 'qtf_buy_redirect' );

function qtf_buy_single_package( $passed ) {
	if ( $passed && ! empty( $_GET['qtf_buy'] ) && function_exists( 'WC' ) && WC()->cart ) { // phpcs:ignore WordPress.Security.NonceVerification
		WC()->cart->empty_cart();
	}
	return $passed;
}
add_filter( 'woocommerce_add_to_cart_validation', 'qtf_buy_single_package' );

/** Credit packages: published products that have credits set. */
function qtf_credit_packages() {
	$posts = get_posts(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'meta_query'     => array(
				array(
					'key'     => '_qtf_credits',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
		)
	);
	$out = array();
	foreach ( $posts as $post ) {
		$product = wc_get_product( $post->ID );
		if ( $product && $product->is_purchasable() ) {
			$out[] = array(
				'product' => $product,
				'credits' => (int) get_post_meta( $post->ID, '_qtf_credits', true ),
			);
		}
	}
	usort(
		$out,
		function ( $a, $b ) {
			return (float) $a['product']->get_price() <=> (float) $b['product']->get_price();
		}
	);
	return $out;
}

function qtf_credit_history( $user_id, $limit = 20 ) {
	global $wpdb;
	return $wpdb->get_results(
		$wpdb->prepare( 'SELECT * FROM ' . qtf_table( 'credit_ledger' ) . ' WHERE user_id = %d ORDER BY id DESC LIMIT %d', (int) $user_id, (int) $limit )
	);
}

/* ---------------------------------------- admin: change a user's credits by hand */

function qtf_profile_credit_field( $user ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<h2>Qoutify credits</h2>
	<table class="form-table" role="presentation">
		<tr>
			<th>Current balance</th>
			<td><strong><?php echo esc_html( qtf_credits_balance( $user->ID ) ); ?></strong></td>
		</tr>
		<tr>
			<th><label for="qtf_credit_adjust">Add or remove credits</label></th>
			<td>
				<input type="number" step="1" name="qtf_credit_adjust" id="qtf_credit_adjust" class="small-text" value="0">
				<input type="text" name="qtf_credit_note" class="regular-text" placeholder="Note (for example: test credits)">
				<p class="description">Use a negative number to remove credits. Every change is saved in the credit history.</p>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'edit_user_profile', 'qtf_profile_credit_field' );

function qtf_save_credit_adjust( $user_id ) {
	// WordPress has already checked the profile nonce here.
	if ( ! current_user_can( 'manage_options' ) || empty( $_POST['qtf_credit_adjust'] ) ) {
		return;
	}
	$delta = (int) $_POST['qtf_credit_adjust'];
	if ( 0 === $delta ) {
		return;
	}
	$note = isset( $_POST['qtf_credit_note'] ) ? sanitize_text_field( wp_unslash( $_POST['qtf_credit_note'] ) ) : '';
	qtf_credits_add( (int) $user_id, $delta, 'admin', 'admin:' . (int) $user_id . ':' . wp_generate_password( 12, false, false ), array( 'note' => $note ) );
}
add_action( 'edit_user_profile_update', 'qtf_save_credit_adjust' );
