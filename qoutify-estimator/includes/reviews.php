<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customer reviews. A review belongs to one user and one completed estimate.
 * New reviews are Pending. Only Approved reviews can ever appear on the public website.
 */
function qtf_review_statuses() {
	return array(
		'pending'  => __( 'Pending', 'qoutify-estimator' ),
		'approved' => __( 'Approved', 'qoutify-estimator' ),
		'rejected' => __( 'Rejected', 'qoutify-estimator' ),
	);
}

function qtf_review_status_badge( $status ) {
	$labels = qtf_review_statuses();
	$label  = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	return '<span class="qtf-badge qtf-badge--review-' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
}

function qtf_review_for_estimate( $user_id, $estimate_id ) {
	global $wpdb;
	return $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . qtf_table( 'reviews' ) . ' WHERE user_id = %d AND estimate_id = %d', (int) $user_id, (int) $estimate_id )
	);
}

function qtf_reviews_for_user( $user_id ) {
	global $wpdb;
	return $wpdb->get_results(
		$wpdb->prepare(
			'SELECT r.*, e.uuid, e.details FROM ' . qtf_table( 'reviews' ) . ' r LEFT JOIN ' . qtf_table( 'estimates' ) . ' e ON e.id = r.estimate_id WHERE r.user_id = %d ORDER BY r.created_at DESC LIMIT 50',
			(int) $user_id
		)
	);
}

/** Completed estimates of this user that have no review yet. */
function qtf_estimates_awaiting_review( $user_id ) {
	global $wpdb;
	return $wpdb->get_results(
		$wpdb->prepare(
			'SELECT e.* FROM ' . qtf_table( 'estimates' ) . ' e LEFT JOIN ' . qtf_table( 'reviews' ) . " r ON r.estimate_id = e.id AND r.user_id = e.user_id WHERE e.user_id = %d AND e.status = 'completed' AND r.id IS NULL ORDER BY e.completed_at DESC LIMIT 20",
			(int) $user_id
		)
	);
}

function qtf_review_dismissed( $user_id ) {
	$list = get_user_meta( $user_id, 'qtf_review_dismissed', true );
	return is_array( $list ) ? array_map( 'intval', $list ) : array();
}

function qtf_review_dismiss( $user_id, $estimate_id ) {
	$list   = qtf_review_dismissed( $user_id );
	$list[] = (int) $estimate_id;
	$list   = array_slice( array_values( array_unique( $list ) ), -200 );
	update_user_meta( $user_id, 'qtf_review_dismissed', $list );
}

/**
 * The popup is only offered for a COMPLETED estimate of this user that has no review
 * and was not dismissed before.
 */
function qtf_should_prompt_review( $estimate ) {
	if ( ! $estimate || 'completed' !== $estimate->status || (int) $estimate->user_id !== get_current_user_id() ) {
		return false;
	}
	if ( qtf_review_for_estimate( $estimate->user_id, $estimate->id ) ) {
		return false;
	}
	return ! in_array( (int) $estimate->id, qtf_review_dismissed( $estimate->user_id ), true );
}

function qtf_clear_public_cache() {
	do_action( 'litespeed_purge_all' ); // LiteSpeed Cache. Ignored if the plugin is not active.
}

/* ---------------------------------------------------------------- user actions */

function qtf_process_review_post() {
	if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST['qtf_action'] ) ) {
		return;
	}
	$action = sanitize_key( wp_unslash( $_POST['qtf_action'] ) );
	if ( ! in_array( $action, array( 'review_submit', 'review_dismiss', 'review_delete' ), true ) ) {
		return;
	}
	if ( ! is_user_logged_in() || ! qtf_is_dashboard_request() ) {
		return;
	}
	$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'qtf_' . $action ) ) {
		qtf_redirect( qtf_dashboard_url( 'my-reviews' ), 'nonce' );
	}

	global $wpdb;
	$user_id = get_current_user_id();
	$back    = qtf_dashboard_url( 'my-reviews' );

	if ( 'review_delete' === $action ) {
		$id = isset( $_POST['review_id'] ) ? absint( $_POST['review_id'] ) : 0;
		// The user id is part of the query, so nobody can delete another user's review.
		$wpdb->delete( qtf_table( 'reviews' ), array( 'id' => $id, 'user_id' => $user_id ) );
		qtf_clear_public_cache();
		qtf_redirect( $back, 'review_deleted' );
	}

	$uuid     = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
	$estimate = $uuid ? qtf_estimate_by_uuid( $uuid, $user_id ) : null;
	if ( ! $estimate || 'completed' !== $estimate->status ) {
		qtf_redirect( $back, 'missing' );
	}

	if ( 'review_dismiss' === $action ) {
		qtf_review_dismiss( $user_id, $estimate->id );
		qtf_redirect( qtf_estimate_url( $estimate->uuid ) );
	}

	// review_submit
	if ( qtf_review_for_estimate( $user_id, $estimate->id ) ) {
		qtf_redirect( $back, 'review_exists' );
	}
	$rating = isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 0;
	$body   = isset( $_POST['body'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) ) : '';
	$length = function_exists( 'mb_strlen' ) ? mb_strlen( $body ) : strlen( $body );
	if ( $rating < 1 || $rating > 5 || $length < 10 || $length > 1000 ) {
		qtf_redirect( add_query_arg( 'write', $estimate->uuid, $back ), 'review_bad' );
	}

	$now = qtf_now();
	$suppress = $wpdb->suppress_errors( true );
	$ok       = $wpdb->insert(
		qtf_table( 'reviews' ),
		array(
			'user_id'     => $user_id,
			'estimate_id' => (int) $estimate->id,
			'rating'      => $rating,
			'body'        => $body,
			'status'      => 'pending',
			'created_at'  => $now,
			'updated_at'  => $now,
		)
	);
	$wpdb->suppress_errors( $suppress );
	if ( ! $ok ) {
		qtf_redirect( $back, 'review_exists' );
	}
	qtf_review_dismiss( $user_id, $estimate->id );
	qtf_redirect( $back, 'review_sent' );
}
add_action( 'template_redirect', 'qtf_process_review_post', 4 );

/* ------------------------------------------------------------ public shortcode */

/**
 * [qtf_reviews limit="6"] - approved reviews only, newest first. Put it in the existing
 * testimonial section of the home page with a Shortcode widget.
 */
function qtf_sc_reviews( $atts ) {
	global $wpdb;
	$atts  = shortcode_atts( array( 'limit' => 6 ), $atts, 'qtf_reviews' );
	$limit = max( 1, min( 12, (int) $atts['limit'] ) );
	$rows  = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT * FROM ' . qtf_table( 'reviews' ) . " WHERE status = 'approved' ORDER BY created_at DESC, id DESC LIMIT %d",
			$limit
		)
	);

	if ( ! $rows ) {
		return '<div class="qtf-reviews qtf-reviews--empty"><p>' . esc_html__( 'Client reviews will appear here soon.', 'qoutify-estimator' ) . '</p></div>';
	}

	$html = '<div class="qtf-reviews">';
	foreach ( $rows as $row ) {
		$user   = get_userdata( $row->user_id );
		$name   = $user ? qtf_user_first_name( $user ) : __( 'Qoutify user', 'qoutify-estimator' );
		$rating = max( 1, min( 5, (int) $row->rating ) );
		$stars  = str_repeat( '&#9733;', $rating ) . '<span class="qtf-stars-off">' . str_repeat( '&#9733;', 5 - $rating ) . '</span>';

		$html .= '<figure class="qtf-review-card">';
		/* translators: %d: rating out of 5 */
		$html .= '<div class="qtf-review-card__stars" role="img" aria-label="' . esc_attr( sprintf( __( '%d out of 5 stars', 'qoutify-estimator' ), $rating ) ) . '">' . $stars . '</div>';
		$html .= '<blockquote>' . nl2br( esc_html( $row->body ) ) . '</blockquote>';
		$html .= '<figcaption><strong>' . esc_html( $name ) . '</strong><span>' . esc_html__( 'Qoutify customer', 'qoutify-estimator' ) . '</span></figcaption>';
		$html .= '</figure>';
	}
	return $html . '</div>';
}
add_shortcode( 'qtf_reviews', 'qtf_sc_reviews' );

/* ------------------------------------------------------------------ admin page */

function qtf_admin_reviews_page() {
	global $wpdb;
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['qtf_review_admin'] ) ) {
		check_admin_referer( 'qtf_admin_review' );
		$id     = isset( $_POST['review_id'] ) ? absint( $_POST['review_id'] ) : 0;
		$do     = sanitize_key( qtf_admin_post( 'do' ) );
		$table  = qtf_table( 'reviews' );
		$now    = qtf_now();
		if ( $id && in_array( $do, array( 'approved', 'rejected', 'pending' ), true ) ) {
			$wpdb->update( $table, array( 'status' => $do, 'updated_at' => $now, 'moderated_at' => $now ), array( 'id' => $id ) );
			qtf_clear_public_cache();
			qtf_admin_notice( 'success', 'Review updated.' );
		} elseif ( $id && 'delete' === $do ) {
			$wpdb->delete( $table, array( 'id' => $id ) );
			qtf_clear_public_cache();
			qtf_admin_notice( 'success', 'Review deleted.' );
		}
	}

	$filter  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$filter  = array_key_exists( $filter, qtf_review_statuses() ) ? $filter : '';
	$per     = 20;
	$page    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
	$offset  = ( $page - 1 ) * $per;
	$table   = qtf_table( 'reviews' );
	$where   = $filter ? $wpdb->prepare( 'WHERE r.status = %s', $filter ) : '';
	$total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} r {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL
	$rows    = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT r.*, u.display_name, u.user_email FROM {$table} r LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id {$where} ORDER BY r.created_at DESC, r.id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL
			$per,
			$offset
		)
	);
	$counts  = $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status", OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL
	$base    = admin_url( 'admin.php?page=qtf-reviews' );
	?>
	<div class="wrap">
		<h1>Reviews</h1>
		<p>Only <strong>Approved</strong> reviews can appear on the public website, through the <code>[qtf_reviews]</code> shortcode. Pending and Rejected reviews are never shown.</p>
		<ul class="subsubsub">
			<li><a href="<?php echo esc_url( $base ); ?>" class="<?php echo '' === $filter ? 'current' : ''; ?>">All</a> |</li>
			<?php
			$labels = qtf_review_statuses();
			$keys   = array_keys( $labels );
			foreach ( $keys as $i => $key ) :
				$n = isset( $counts[ $key ] ) ? (int) $counts[ $key ]->n : 0;
				?>
				<li><a href="<?php echo esc_url( add_query_arg( 'status', $key, $base ) ); ?>" class="<?php echo $filter === $key ? 'current' : ''; ?>"><?php echo esc_html( $labels[ $key ] ); ?> (<?php echo esc_html( $n ); ?>)</a><?php echo $i < count( $keys ) - 1 ? ' |' : ''; ?></li>
			<?php endforeach; ?>
		</ul>
		<table class="widefat striped" style="margin-top:12px;">
			<thead><tr><th>Date (UTC)</th><th>User</th><th>Rating</th><th style="width:38%">Review</th><th>Status</th><th>Actions</th></tr></thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="6">No reviews found.</td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td><?php echo esc_html( $row->created_at ); ?></td>
					<td><?php echo esc_html( $row->display_name ); ?><br><small><?php echo esc_html( $row->user_email ); ?></small></td>
					<td><?php echo esc_html( (int) $row->rating ); ?> / 5</td>
					<td><?php echo nl2br( esc_html( $row->body ) ); ?></td>
					<td><?php echo qtf_review_status_badge( $row->status ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
					<td>
						<form method="post" style="display:flex;gap:6px;flex-wrap:wrap;">
							<?php wp_nonce_field( 'qtf_admin_review' ); ?>
							<input type="hidden" name="qtf_review_admin" value="1">
							<input type="hidden" name="review_id" value="<?php echo esc_attr( $row->id ); ?>">
							<?php if ( 'approved' !== $row->status ) : ?><button class="button button-primary" name="do" value="approved">Approve</button><?php endif; ?>
							<?php if ( 'rejected' !== $row->status ) : ?><button class="button" name="do" value="rejected">Reject</button><?php endif; ?>
							<?php if ( 'pending' !== $row->status ) : ?><button class="button" name="do" value="pending">Set pending</button><?php endif; ?>
							<button class="button" name="do" value="delete" onclick="return confirm('Delete this review permanently?');">Delete</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$pages = (int) ceil( $total / $per );
		if ( $pages > 1 ) {
			echo '<p>';
			for ( $i = 1; $i <= $pages; $i++ ) {
				echo '<a class="button' . ( $i === $page ? ' button-primary' : '' ) . '" href="' . esc_url( add_query_arg( array( 'paged' => $i, 'status' => $filter ), $base ) ) . '">' . esc_html( $i ) . '</a> ';
			}
			echo '</p>';
		}
		?>
	</div>
	<?php
}
