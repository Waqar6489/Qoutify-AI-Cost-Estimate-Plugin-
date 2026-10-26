<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings are stored in one option. The AI key is NEVER stored here: it lives in wp-config.php.
 */
function qtf_defaults() {
	return array(
		'ai_provider'     => 'groq',
		'ai_model'        => 'meta-llama/llama-4-scout-17b-16e-instruct',
		'ai_base_url'     => '',
		'ai_max_images'   => 4,
		'credit_price'    => '5.00',
		'max_custom_credits' => 500,
		'cost_res_quick'  => 1,
		'cost_res_detailed' => 2,
		'cost_com_quick'  => 2,
		'cost_com_detailed' => 4,
		'overhead_pct'    => 10,
		'contingency_pct' => 10,
		'markup_pct'      => 10,
		'occupied_pct'    => 10,
		'pricing_status'  => 'sample',
		'daily_limit'     => 10,
		'max_files'       => 10,
		'max_file_mb'     => 20,
	);
}

function qtf_settings() {
	$saved = get_option( 'qtf_settings', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), qtf_defaults() );
}

function qtf_setting( $key ) {
	$settings = qtf_settings();
	return isset( $settings[ $key ] ) ? $settings[ $key ] : '';
}

/**
 * The five cost categories shared by residential and commercial projects.
 */
function qtf_categories() {
	return array(
		'site_demo' => __( 'Site Work & Demolition', 'qoutify-estimator' ),
		'structure' => __( 'Structure', 'qoutify-estimator' ),
		'mep'       => __( 'Electrical, Plumbing & HVAC', 'qoutify-estimator' ),
		'interior'  => __( 'Interior Finishes', 'qoutify-estimator' ),
		'exterior'  => __( 'Windows, Doors & Exterior', 'qoutify-estimator' ),
	);
}

/**
 * SAMPLE pricing data for testing only. Replace with the client's real rates.
 * base = dollars per square foot for a Moderate renovation at Standard quality.
 * split = material / labor / equipment percentages of each category.
 */
function qtf_pricing_defaults() {
	return array(
		'base'      => array(
			'residential' => array( 'site_demo' => 4, 'structure' => 12, 'mep' => 14, 'interior' => 18, 'exterior' => 9 ),
			'commercial'  => array( 'site_demo' => 5, 'structure' => 14, 'mep' => 24, 'interior' => 20, 'exterior' => 12 ),
		),
		'condition' => array( 'cosmetic' => 0.4, 'moderate' => 1.0, 'major' => 1.8, 'gut' => 2.8 ),
		'quality'   => array( 'basic' => 0.85, 'standard' => 1.0, 'premium' => 1.35, 'luxury' => 1.8 ),
		'split'     => array(
			'site_demo' => array( 10, 55, 35 ),
			'structure' => array( 45, 45, 10 ),
			'mep'       => array( 40, 55, 5 ),
			'interior'  => array( 50, 47, 3 ),
			'exterior'  => array( 55, 42, 3 ),
		),
		'region'    => array(),
	);
}

function qtf_pricing() {
	$saved = get_option( 'qtf_pricing', array() );
	return array_replace_recursive( qtf_pricing_defaults(), is_array( $saved ) ? $saved : array() );
}

function qtf_pricing_is_sample() {
	return 'approved' !== qtf_setting( 'pricing_status' );
}

/**
 * Credits needed for one estimate. Team members never pay.
 */
function qtf_credit_cost( $type, $path ) {
	$key = 'cost_' . ( 'commercial' === $type ? 'com' : 'res' ) . '_' . ( 'detailed' === $path ? 'detailed' : 'quick' );
	return max( 1, (int) qtf_setting( $key ) );
}

function qtf_min_cost() {
	return min(
		qtf_credit_cost( 'residential', 'quick' ),
		qtf_credit_cost( 'residential', 'detailed' ),
		qtf_credit_cost( 'commercial', 'quick' ),
		qtf_credit_cost( 'commercial', 'detailed' )
	);
}

function qtf_disclaimer_text() {
	return __( 'This estimate is a preliminary construction cost estimate generated from the information and documents provided. It is not a guaranteed quote, bid, contract price, or final construction budget. Actual costs may vary based on site conditions, material selections, labor availability, permits, subcontractor pricing, market conditions, design changes, and other project-specific factors. The estimate should be reviewed and verified by a qualified construction professional before being used for contractual pricing or final project decisions.', 'qoutify-estimator' );
}
