<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Buying credits with a custom amount, plus the shared checkout details.
 *
 * Custom amount: one hidden WooCommerce product. The price is NOT taken from the browser.
 * The server checks the number of credits, then WooCommerce recalculates the price on every cart
 * calculation from the configured price per credit. Packages use normal WooCommerce products
 * that have the "Qoutify credits" field set.
 */
function qtf_custom_product_id() {
	$id = (int) get_option( 'qtf_custom_product_id', 0 );
	if ( $id && 'product' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) {
		return $id;
	}
	if ( ! class_exists( 'WC_Product_Simple' ) ) {
		return 0;
	}
	$product = new WC_Product_Simple();
	$product->set_name( 'Qoutify Credits (custom amount)' );
	$product->set_status( 'publish' );
	$product->set_catalog_visibility( 'hidden' );
	$product->set_virtual( true );
	$product->set_sold_individually( true );
	$product->set_regular_price( (string) qtf_credit_price() );
	$id = (int) $product->save();
	if ( $id ) {
		update_option( 'qtf_custom_product_id', $id );
	}
	return $id;
}

function qtf_clamp_custom_credits( $credits ) {
	$max = max( 1, (int) qtf_setting( 'max_custom_credits' ) );
	return max( 1, min( $max, (int) $credits ) );
}

/** The "Buy credits" form on the Credits page. */
function qtf_process_purchase_post() {
	if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST['qtf_action'] ) ) {
		return;
	}
	if ( 'buy_custom' !== sanitize_key( wp_unslash( $_POST['qtf_action'] ) ) ) {
		return;
	}
	if ( ! is_user_logged_in() || ! qtf_is_dashboard_request() || 'credits' !== qtf_current_view() ) {
		return;
	}
	$credits_url = qtf_dashboard_url( 'credits' );
	$nonce       = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'qtf_buy_custom' ) ) {
		qtf_redirect( $credits_url, 'nonce' );
	}

	// Only whole numbers inside the allowed range are accepted. The typed total is never read.
	$raw = isset( $_POST['credits'] ) ? trim( (string) wp_unslash( $_POST['credits'] ) ) : '';
	if ( ! ctype_digit( $raw ) ) {
		qtf_redirect( $credits_url, 'bad_amount' );
	}
	$credits = (int) $raw;
	if ( $credits < 1 || $credits > (int) qtf_setting( 'max_custom_credits' ) ) {
		qtf_redirect( $credits_url, 'bad_amount' );
	}

	$product_id = qtf_custom_product_id();
	if ( ! $product_id || ! function_exists( 'WC' ) ) {
		qtf_redirect( $credits_url, 'buy_fail' );
	}
	if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
		wc_load_cart();
	}
	if ( ! WC()->cart ) {
		qtf_redirect( $credits_url, 'buy_fail' );
	}

	// One credit purchase at a time: the checkout holds only this item.
	WC()->cart->empty_cart();
	$added = WC()->cart->add_to_cart( $product_id, 1, 0, array(), array( 'qtf_custom_credits' => $credits ) );
	if ( ! $added ) {
		qtf_redirect( $credits_url, 'buy_fail' );
	}
	wp_safe_redirect( wc_get_checkout_url() );
	exit;
}
add_action( 'template_redirect', 'qtf_process_purchase_post', 4 );

/** The price of a custom-amount line is always credits x the configured price per credit. */
function qtf_price_custom_items( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}
	foreach ( $cart->get_cart() as $cart_item ) {
		if ( ! empty( $cart_item['qtf_custom_credits'] ) ) {
			$cart_item['data']->set_price( qtf_price_for_credits( qtf_clamp_custom_credits( $cart_item['qtf_custom_credits'] ) ) );
		}
	}
}
add_action( 'woocommerce_before_calculate_totals', 'qtf_price_custom_items', 20 );

/** Show the credits clearly in the cart and at the checkout. */
function qtf_cart_item_data( $data, $cart_item ) {
	$credits = 0;
	if ( ! empty( $cart_item['qtf_custom_credits'] ) ) {
		$credits = qtf_clamp_custom_credits( $cart_item['qtf_custom_credits'] );
	} elseif ( ! empty( $cart_item['product_id'] ) ) {
		$per = (int) get_post_meta( $cart_item['product_id'], '_qtf_credits', true );
		if ( $per > 0 ) {
			$credits = $per * max( 1, (int) $cart_item['quantity'] );
		}
	}
	if ( $credits > 0 ) {
		$data[] = array(
			'key'   => __( 'Credits', 'qoutify-estimator' ),
			'value' => (string) $credits,
		);
	}
	if ( ! empty( $cart_item['qtf_custom_credits'] ) ) {
		$data[] = array(
			'key'   => __( 'Price per credit', 'qoutify-estimator' ),
			'value' => qtf_price_text( qtf_credit_price() ),
		);
	}
	return $data;
}
add_filter( 'woocommerce_get_item_data', 'qtf_cart_item_data', 10, 2 );

/**
 * Save the credits and the price per credit on the order line. The order keeps these values
 * forever, so a later price change never rewrites an old order.
 */
function qtf_order_item_meta( $item, $cart_item_key, $values, $order ) {
	$credits = 0;
	if ( ! empty( $values['qtf_custom_credits'] ) ) {
		$credits = qtf_clamp_custom_credits( $values['qtf_custom_credits'] );
	} else {
		$per = (int) get_post_meta( $item->get_product_id(), '_qtf_credits', true );
		if ( $per > 0 ) {
			$credits = $per * max( 1, (int) $values['quantity'] );
		}
	}
	if ( $credits < 1 ) {
		return;
	}
	$line_total = isset( $values['line_total'] ) ? (float) $values['line_total'] : 0.0;
	$item->add_meta_data( '_qtf_credits_purchased', $credits, true );
	$item->add_meta_data( '_qtf_price_per_credit', round( $line_total / $credits, 4 ), true );
	$item->add_meta_data( __( 'Credits', 'qoutify-estimator' ), $credits, true );
}
add_action( 'woocommerce_checkout_create_order_line_item', 'qtf_order_item_meta', 10, 4 );

/** After payment: show where the credits are, then go to the Credits page. */
function qtf_order_has_credits( $order ) {
	foreach ( $order->get_items() as $item ) {
		if ( qtf_order_item_credits( $item ) > 0 ) {
			return true;
		}
	}
	return false;
}

function qtf_thankyou_redirect( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || (int) $order->get_user_id() !== get_current_user_id() || ! qtf_order_has_credits( $order ) ) {
		return;
	}
	$url = add_query_arg( 'qtf_order', (int) $order_id, qtf_dashboard_url( 'credits' ) );
	echo '<p><a class="qtf-btn qtf-btn--primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Go to my credits', 'qoutify-estimator' ) . '</a></p>';
	echo '<script>setTimeout(function(){window.location.href=' . wp_json_encode( $url ) . ';},6000);</script>';
}
add_action( 'woocommerce_thankyou', 'qtf_thankyou_redirect', 20 );

/**
 * Status of one order for the notice on the Credits page. Only the real ledger and the
 * real order status are used, never the browser's return URL.
 */
function qtf_order_notice() {
	$order_id = isset( $_GET['qtf_order'] ) ? absint( $_GET['qtf_order'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
		return '';
	}
	$order = wc_get_order( $order_id );
	if ( ! $order || (int) $order->get_user_id() !== get_current_user_id() ) {
		return '';
	}
	global $wpdb;
	$added = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COALESCE(SUM(delta),0) FROM ' . qtf_table( 'credit_ledger' ) . " WHERE order_id = %d AND reason = 'purchase'",
			$order_id
		)
	);
	$status = $order->get_status();

	if ( $added > 0 && in_array( $status, array( 'processing', 'completed' ), true ) ) {
		/* translators: %s: credits */
		return '<div class="qtf-notice qtf-notice--success" role="status">' . esc_html( sprintf( __( 'Payment confirmed. %s were added to your account. We emailed you a confirmation.', 'qoutify-estimator' ), qtf_credits_label( $added ) ) ) . '</div>';
	}
	if ( in_array( $status, array( 'failed', 'cancelled' ), true ) ) {
		return '<div class="qtf-notice qtf-notice--error" role="status">' . esc_html__( 'The payment was not completed, so no credits were added. You can try again below.', 'qoutify-estimator' ) . '</div>';
	}
	if ( in_array( $status, array( 'refunded' ), true ) ) {
		return '<div class="qtf-notice qtf-notice--info" role="status">' . esc_html__( 'This order was refunded, so its credits were removed.', 'qoutify-estimator' ) . '</div>';
	}
	return '<div class="qtf-notice qtf-notice--info" role="status">' . esc_html__( 'We are waiting for the payment to be confirmed. Credits are added automatically once it is. Refresh this page in a minute.', 'qoutify-estimator' ) . '</div>';
}
