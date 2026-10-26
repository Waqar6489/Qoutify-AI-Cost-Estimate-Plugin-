<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qtf_enqueue_assets() {
	// Login, register and password pages.
	wp_enqueue_style( 'qtf-style', QTF_URL . 'assets/qtf.css', array(), QTF_VERSION );

	// Account page (login, register, verified screens).
	if ( qtf_is_account_request() ) {
		wp_enqueue_style(
			'qtf-font',
			'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap',
			array(),
			null
		);
		wp_enqueue_script( 'qtf-auth', QTF_URL . 'assets/qtf-auth.js', array(), QTF_VERSION, true );
	}

	// Dashboard pages.
	if ( qtf_is_dashboard_request() && is_user_logged_in() && ! qtf_is_builder_request() ) {
		wp_enqueue_style(
			'qtf-font',
			'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap',
			array(),
			null
		);
		wp_enqueue_style( 'qtf-app', QTF_URL . 'assets/qtf-app.css', array( 'qtf-style' ), QTF_VERSION );
		wp_enqueue_script( 'qtf-app', QTF_URL . 'assets/qtf-app.js', array(), QTF_VERSION, true );
		wp_localize_script(
			'qtf-app',
			'QTF',
			array(
				'rest'  => esc_url_raw( rest_url( 'qoutify/v1/' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'qtf_enqueue_assets', 20 );
