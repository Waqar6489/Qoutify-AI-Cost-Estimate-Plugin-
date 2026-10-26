<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Background processing. WooCommerce ships Action Scheduler; wp-cron is the fallback.
 */
function qtf_enqueue_estimate( $id ) {
	if ( function_exists( 'as_enqueue_async_action' ) ) {
		as_enqueue_async_action( 'qtf_process_estimate', array( (int) $id ), 'qoutify' );
	} else {
		wp_schedule_single_event( time(), 'qtf_process_estimate', array( (int) $id ) );
	}
}

/** Only one worker can take a queued estimate. */
function qtf_claim_estimate( $id ) {
	global $wpdb;
	$changed = $wpdb->query(
		$wpdb->prepare(
			'UPDATE ' . qtf_table( 'estimates' ) . " SET status = 'processing', updated_at = %s WHERE id = %d AND status = 'queued'",
			qtf_now(),
			(int) $id
		)
	);
	return 1 === (int) $changed;
}

function qtf_fail_estimate( $estimate, $code ) {
	qtf_estimate_update(
		$estimate->id,
		array(
			'status'        => 'failed',
			'error_message' => substr( (string) $code, 0, 100 ),
		)
	);
	qtf_credits_refund_estimate( $estimate );
}

function qtf_job_process( $id ) {
	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 180 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions
	}
	if ( ! qtf_claim_estimate( $id ) ) {
		return;
	}
	$estimate = qtf_estimate_by_id( $id );
	if ( ! $estimate ) {
		return;
	}

	try {
		$details = qtf_estimate_details( $estimate );
		$run     = qtf_ai_run( $estimate, $details );
		if ( empty( $run['ok'] ) ) {
			qtf_fail_estimate( $estimate, $run['error'] );
			return;
		}

		$ai   = $run['data'];
		$docs = $run['docs'];
		$calc = qtf_calculate( $estimate->project_type, $details, $ai['scope'] );
		list( $confidence, $notes ) = qtf_confidence_score( $details, $docs, $calc['region_known'], $ai['confidence_adjust'] );
		list( $low, $high )         = qtf_price_range( $calc['total'], $confidence );

		$assumptions  = $ai['assumptions'];
		$verify_items = $ai['verify_items'];
		if ( ! $calc['region_known'] ) {
			$verify_items[] = __( 'Local pricing for this state needs verification. A neutral regional factor was used.', 'qoutify-estimator' );
		}
		foreach ( $docs['notes'] as $note ) {
			$verify_items[] = $note;
		}
		if ( qtf_pricing_is_sample() ) {
			$assumptions[] = __( 'TEST MODE: the pricing data is sample data and does not represent real prices.', 'qoutify-estimator' );
		}

		foreach ( $calc['lines'] as $i => $line ) {
			$calc['lines'][ $i ]['reason'] = isset( $ai['reasons'][ $line['key'] ] ) ? $ai['reasons'][ $line['key'] ] : '';
		}

		$result = array(
			'summary'          => $ai['summary'],
			'lines'            => $calc['lines'],
			'direct'           => $calc['direct'],
			'overhead'         => $calc['overhead'],
			'contingency'      => $calc['contingency'],
			'markup'           => $calc['markup'],
			'total'            => $calc['total'],
			'low'              => $low,
			'high'             => $high,
			'confidence'       => $confidence,
			'confidence_notes' => $notes,
			'assumptions'      => $assumptions,
			'verify_items'     => $verify_items,
			'cost_drivers'     => $ai['cost_drivers'],
			'docs'             => $docs,
			'pricing_sample'   => qtf_pricing_is_sample(),
			'prompt_version'   => qtf_prompt_version(),
			'generated_at'     => qtf_now(),
		);

		qtf_estimate_update(
			$estimate->id,
			array(
				'status'        => 'completed',
				'total_low'     => $low,
				'total_high'    => $high,
				'confidence'    => $confidence,
				'result'        => wp_json_encode( $result ),
				'ai_model'      => substr( (string) qtf_setting( 'ai_model' ), 0, 100 ),
				'completed_at'  => qtf_now(),
				'error_message' => null,
			)
		);
		qtf_notify_ready( qtf_estimate_by_id( $estimate->id ) );
	} catch ( Throwable $e ) {
		qtf_fail_estimate( $estimate, 'exception' );
	}
}
add_action( 'qtf_process_estimate', 'qtf_job_process', 10, 1 );

function qtf_notify_ready( $estimate ) {
	$user = get_userdata( $estimate->user_id );
	if ( ! $user ) {
		return;
	}
	$url     = add_query_arg( 'id', $estimate->uuid, qtf_dashboard_url( 'estimate' ) );
	$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$subject = sprintf( __( 'Your %s estimate is ready', 'qoutify-estimator' ), $site );
	$message = sprintf( "Hi %s,\n\nYour estimate is ready:\n\n%s\n\n%s", qtf_user_first_name( $user ), $url, $site );
	wp_mail( $user->user_email, $subject, $message );
}

/* The processing screen asks this endpoint for the status every few seconds. */
function qtf_register_routes() {
	register_rest_route(
		'qoutify/v1',
		'/estimate/(?P<uuid>[a-f0-9\-]{36})/status',
		array(
			'methods'             => 'GET',
			'callback'            => 'qtf_rest_status',
			'permission_callback' => 'is_user_logged_in',
		)
	);
}
add_action( 'rest_api_init', 'qtf_register_routes' );

function qtf_rest_status( $request ) {
	$estimate = qtf_estimate_by_uuid( $request['uuid'], get_current_user_id() );
	if ( ! $estimate ) {
		return new WP_REST_Response( array( 'status' => 'missing' ), 404 );
	}
	$age = time() - (int) strtotime( $estimate->updated_at . ' UTC' );

	// Safety nets: start a job that was never picked up, and fail one that got stuck.
	if ( 'queued' === $estimate->status && $age > 20 ) {
		qtf_job_process( $estimate->id );
		$estimate = qtf_estimate_by_id( $estimate->id );
	} elseif ( 'processing' === $estimate->status && $age > 600 ) {
		qtf_fail_estimate( $estimate, 'timeout' );
		$estimate = qtf_estimate_by_id( $estimate->id );
	}
	return new WP_REST_Response( array( 'status' => $estimate->status ), 200 );
}
