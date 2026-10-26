<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Team role. Staff use the estimator free. They get no wp-admin access.
 * Create a staff account: Users > Add New > Role "Qoutify Staff".
 */
function qtf_add_staff_role() {
	if ( ! get_role( 'qtf_staff' ) ) {
		add_role(
			'qtf_staff',
			__( 'Qoutify Staff', 'qoutify-estimator' ),
			array( 'read' => true )
		);
	}
}

function qtf_activate() {
	qtf_add_staff_role();
	qtf_create_tables();
}

add_action( 'init', 'qtf_add_staff_role' );
