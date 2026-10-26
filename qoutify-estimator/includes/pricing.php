<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of truth for what a credit costs.
 * Every price shown anywhere is: credits needed x qtf_credit_price().
 */
function qtf_credit_price() {
	$raw   = qtf_setting( 'credit_price' );
	$price = is_numeric( $raw ) ? (float) $raw : 5.0;
	return $price > 0 ? round( $price, 2 ) : 5.0;
}

/** Whole cents are used for the maths, so there are no floating-point surprises. */
function qtf_price_for_credits( $credits ) {
	$cents = (int) round( qtf_credit_price() * 100 );
	return ( max( 0, (int) $credits ) * $cents ) / 100;
}

/** Plain-text price in the store currency, for example "$10". */
function qtf_price_text( $amount ) {
	$amount = (float) $amount;
	if ( function_exists( 'wc_price' ) ) {
		$decimals = ( floor( $amount ) === $amount ) ? 0 : wc_get_price_decimals();
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount, array( 'decimals' => $decimals ) ) ), ENT_QUOTES, 'UTF-8' );
	}
	return '$' . number_format_i18n( $amount, floor( $amount ) === $amount ? 0 : 2 );
}

function qtf_currency_code() {
	return function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
}

function qtf_credits_label( $credits ) {
	/* translators: %d: number of credits */
	return sprintf( _n( '%d credit', '%d credits', (int) $credits, 'qoutify-estimator' ), (int) $credits );
}

/**
 * The four estimate types with their credits and price.
 */
function qtf_estimate_options() {
	$out = array();
	foreach ( array( 'residential', 'commercial' ) as $type ) {
		foreach ( array( 'quick', 'detailed' ) as $path ) {
			$credits = qtf_credit_cost( $type, $path );
			$out[]   = array(
				'type'    => $type,
				'path'    => $path,
				'title'   => ( 'residential' === $type ? __( 'Residential', 'qoutify-estimator' ) : __( 'Commercial', 'qoutify-estimator' ) ) . ' ' . ( 'quick' === $path ? __( 'Quick', 'qoutify-estimator' ) : __( 'Detailed', 'qoutify-estimator' ) ),
				'credits' => $credits,
				'price'   => qtf_price_for_credits( $credits ),
			);
		}
	}
	return $out;
}

function qtf_support_email() {
	return apply_filters( 'qtf_support_email', 'contact@qoutify.com' );
}

function qtf_support_link() {
	$email = qtf_support_email();
	return '<a class="qtf-support-link" href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
}

/**
 * Message shown when a balance cannot cover an estimate type.
 */
function qtf_need_credits_text( $type, $path ) {
	$types = qtf_project_types();
	$cost  = qtf_credit_cost( $type, $path );
	$have  = qtf_get_credits();
	/* translators: 1: type, 2: quick/detailed, 3: credits needed, 4: credits owned */
	return sprintf(
		__( 'A %1$s %2$s Estimate requires %3$s. You currently have %4$s. Buy more credits to continue.', 'qoutify-estimator' ),
		$types[ $type ],
		'quick' === $path ? __( 'Quick', 'qoutify-estimator' ) : __( 'Detailed', 'qoutify-estimator' ),
		qtf_credits_label( $cost ),
		qtf_credits_label( $have )
	);
}
