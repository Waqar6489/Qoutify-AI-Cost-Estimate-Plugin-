<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'QTF_SCHEMA_VERSION', 2 );

/**
 * Versioned, non-destructive migrations. They only add tables and settings.
 * Estimates, documents, the credit ledger and balances are never changed.
 */
function qtf_run_migrations() {
	$current = (int) get_option( 'qtf_schema_version', 1 );
	if ( $current >= QTF_SCHEMA_VERSION ) {
		return;
	}

	if ( $current < 2 ) {
		// Adds the reviews table. Existing tables are left as they are.
		qtf_create_tables();

		$settings = get_option( 'qtf_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		// Business rule: residential 1 / 2 credits, commercial 2 / 4 credits.
		$settings['cost_res_quick']     = 1;
		$settings['cost_res_detailed']  = 2;
		$settings['cost_com_quick']     = 2;
		$settings['cost_com_detailed']  = 4;
		if ( empty( $settings['credit_price'] ) ) {
			$settings['credit_price'] = '5.00';
		}
		if ( empty( $settings['max_custom_credits'] ) ) {
			$settings['max_custom_credits'] = 500;
		}
		update_option( 'qtf_settings', $settings );
	}

	update_option( 'qtf_schema_version', QTF_SCHEMA_VERSION );
}
add_action( 'plugins_loaded', 'qtf_run_migrations', 30 );
