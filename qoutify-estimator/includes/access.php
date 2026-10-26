<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * After login, go to the dashboard. If WooCommerce asked for a specific page
 * (for example the checkout), keep that page.
 */
function qtf_login_redirect( $redirect ) {
	$account = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '';
	$base    = untrailingslashit( (string) strtok( (string) $redirect, '?' ) );
	if ( empty( $redirect ) || $base === untrailingslashit( $account ) ) {
		return qtf_dashboard_url();
	}
	return $redirect;
}
add_filter( 'woocommerce_login_redirect', 'qtf_login_redirect', 10, 1 );

/**
 * True while a page is open in the Elementor editor or a WordPress preview.
 * The redirects below must not run then, or the editor would not open.
 */
function qtf_is_builder_request() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return isset( $_GET['elementor-preview'] ) || isset( $_GET['preview'] );
}

/**
 * Page rules, run on every front-end page load.
 */
function qtf_route_guard() {
	if ( is_admin() || qtf_is_builder_request() ) {
		return;
	}

	$is_dashboard = qtf_is_dashboard_request();
	$is_account   = qtf_is_account_request();

	// Private pages: never cached, never indexed.
	if ( $is_dashboard || $is_account ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		do_action( 'litespeed_control_set_nocache', 'qoutify private page' );
		header( 'X-Robots-Tag: noindex, nofollow', true );
	}

	if ( is_user_logged_in() && $is_account && function_exists( 'is_wc_endpoint_url' ) ) {
		// The plain Account page goes to the dashboard (except the "Email verified" screen).
		$verified_screen = isset( $_GET['action'] ) && 'verified' === sanitize_key( wp_unslash( $_GET['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! is_wc_endpoint_url() && ! $verified_screen ) {
			wp_safe_redirect( qtf_dashboard_url() );
			exit;
		}
		// The WooCommerce account details page is replaced by our Settings page.
		if ( is_wc_endpoint_url( 'edit-account' ) ) {
			wp_safe_redirect( qtf_dashboard_url( 'settings' ) );
			exit;
		}
	}

	if ( $is_dashboard ) {
		// Logged-out visitors cannot open the dashboard.
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( qtf_account_url() );
			exit;
		}
		// Team members have no Credits page.
		if ( 'credits' === qtf_current_view() && qtf_user_is_staff() ) {
			wp_safe_redirect( qtf_dashboard_url() );
			exit;
		}
	}
}
add_action( 'template_redirect', 'qtf_route_guard', 1 );

/**
 * Only administrators see the WordPress admin bar.
 */
function qtf_admin_bar( $show ) {
	return current_user_can( 'manage_options' ) ? $show : false;
}
add_filter( 'show_admin_bar', 'qtf_admin_bar' );

/**
 * Customers never use wp-login.php. Registration and password reset go to the Account page.
 * wp-login.php stays available for administrators to log in.
 */
function qtf_login_form_register() {
	wp_safe_redirect( add_query_arg( 'action', 'register', qtf_account_url() ) );
	exit;
}
add_action( 'login_form_register', 'qtf_login_form_register' );

function qtf_login_form_lost_password() {
	wp_safe_redirect( function_exists( 'wc_lostpassword_url' ) ? wc_lostpassword_url() : qtf_account_url() );
	exit;
}
foreach ( array( 'lostpassword', 'retrievepassword', 'rp', 'resetpass' ) as $qtf_login_action ) {
	add_action( 'login_form_' . $qtf_login_action, 'qtf_login_form_lost_password' );
}
