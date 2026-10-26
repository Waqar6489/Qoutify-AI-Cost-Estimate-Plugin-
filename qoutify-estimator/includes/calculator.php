<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deterministic pricing. The AI only decides how much of each category is in scope;
 * every dollar amount comes from the pricing data and the formula below.
 *
 * category cost = sqft x scope share x base $/sqft x condition x quality x region x occupied
 */
function qtf_calculate( $type, $details, $scope ) {
	$pricing = qtf_pricing();
	$type    = ( 'commercial' === $type ) ? 'commercial' : 'residential';
	$sqft    = (float) $details['sqft'];
	$cond    = isset( $pricing['condition'][ $details['condition'] ] ) ? (float) $pricing['condition'][ $details['condition'] ] : 1.0;
	$qual    = isset( $pricing['quality'][ $details['quality'] ] ) ? (float) $pricing['quality'][ $details['quality'] ] : 1.0;

	$state        = isset( $details['state'] ) ? $details['state'] : '';
	$region_known = isset( $pricing['region'][ $state ] ) && (float) $pricing['region'][ $state ] > 0;
	$region       = $region_known ? (float) $pricing['region'][ $state ] : 1.0;

	$occupied = 1.0;
	if ( 'commercial' === $type && isset( $details['occupied'] ) && 'occupied' === $details['occupied'] ) {
		$occupied = 1 + ( (float) qtf_setting( 'occupied_pct' ) / 100 );
	}

	$lines  = array();
	$direct = 0.0;

	foreach ( qtf_categories() as $key => $label ) {
		$share = isset( $scope[ $key ] ) ? max( 0.0, min( 1.0, (float) $scope[ $key ] ) ) : 0.0;
		$base  = isset( $pricing['base'][ $type ][ $key ] ) ? (float) $pricing['base'][ $type ][ $key ] : 0.0;
		$total = $sqft * $share * $base * $cond * $qual * $region * $occupied;
		$split = isset( $pricing['split'][ $key ] ) ? $pricing['split'][ $key ] : array( 50, 45, 5 );

		$lines[] = array(
			'key'       => $key,
			'label'     => $label,
			'scope'     => round( $share, 2 ),
			'material'  => round( $total * $split[0] / 100, 2 ),
			'labor'     => round( $total * $split[1] / 100, 2 ),
			'equipment' => round( $total * $split[2] / 100, 2 ),
			'total'     => round( $total, 2 ),
		);
		$direct += $total;
	}

	$overhead    = $direct * (float) qtf_setting( 'overhead_pct' ) / 100;
	$contingency = $direct * (float) qtf_setting( 'contingency_pct' ) / 100;
	$subtotal    = $direct + $overhead + $contingency;
	$markup      = $subtotal * (float) qtf_setting( 'markup_pct' ) / 100;
	$total       = $subtotal + $markup;

	return array(
		'lines'        => $lines,
		'direct'       => round( $direct, 2 ),
		'overhead'     => round( $overhead, 2 ),
		'contingency'  => round( $contingency, 2 ),
		'markup'       => round( $markup, 2 ),
		'total'        => round( $total, 2 ),
		'region_known' => $region_known,
	);
}

/**
 * Confidence 20-90, built from simple rules. Returns array( score, notes ).
 */
function qtf_confidence_score( $details, $docs, $region_known, $ai_adjust ) {
	$score = 45;
	$notes = array( __( 'Starting point for an estimate based on the form.', 'qoutify-estimator' ) );

	if ( ! empty( $details['description'] ) && strlen( $details['description'] ) >= 40 ) {
		$score  += 10;
		$notes[] = __( 'A project description was provided.', 'qoutify-estimator' );
	}
	if ( ! empty( $details['work_wanted'] ) ) {
		$score  += 5;
		$notes[] = __( 'The work wanted was specified.', 'qoutify-estimator' );
	}
	if ( ! empty( $details['year_built'] ) ) {
		$score += 3;
	}
	if ( ! empty( $docs['images_read'] ) ) {
		$score  += 17;
		$notes[] = __( 'Blueprint or photo pages were read by the AI.', 'qoutify-estimator' );
	}
	if ( ! empty( $docs['text_read'] ) ) {
		$score  += 8;
		$notes[] = __( 'A written scope of work was read.', 'qoutify-estimator' );
	}
	if ( qtf_pricing_is_sample() ) {
		$score  -= 10;
		$notes[] = __( 'Pricing data is sample data (test mode).', 'qoutify-estimator' );
	}
	if ( ! $region_known ) {
		$score  -= 5;
		$notes[] = __( 'Local pricing for this state needs verification.', 'qoutify-estimator' );
	}
	$score += max( -10, min( 10, (int) $ai_adjust ) );

	return array( max( 20, min( 90, $score ) ), $notes );
}

/**
 * The range around the total gets wider when confidence is lower.
 */
function qtf_price_range( $total, $confidence ) {
	$spread = 0.08 + ( ( 100 - (int) $confidence ) / 100 ) * 0.30;
	return array(
		round( $total * ( 1 - $spread ), -2 ),
		round( $total * ( 1 + $spread ), -2 ),
	);
}
