<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Team = Qoutify Staff role or administrators. Team members use the estimator free.
 */
function qtf_user_is_staff( $user_id = 0 ) {
	$user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();
	if ( ! $user || ! $user->exists() ) {
		return false;
	}
	if ( user_can( $user, 'manage_options' ) ) {
		return true;
	}
	return in_array( 'qtf_staff', (array) $user->roles, true );
}

function qtf_requires_credits( $user_id = 0 ) {
	return ! qtf_user_is_staff( $user_id );
}

/**
 * Number of estimates the user created. Returns 0 until the estimates table exists (Round 2).
 */
function qtf_estimate_count( $user_id = 0 ) {
	global $wpdb;
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	$table   = $wpdb->prefix . 'qtf_estimates';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		return 0;
	}
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND status <> 'draft'", $user_id ) );
}

function qtf_dashboard_url( $path = '' ) {
	return trailingslashit( home_url( '/dashboard/' . ltrim( $path, '/' ) ) );
}

function qtf_account_url( $endpoint = '' ) {
	if ( ! function_exists( 'wc_get_page_permalink' ) ) {
		return wp_login_url();
	}
	$url = wc_get_page_permalink( 'myaccount' );
	if ( $endpoint && function_exists( 'wc_get_endpoint_url' ) ) {
		$url = wc_get_endpoint_url( $endpoint, '', $url );
	}
	return $url;
}

/**
 * True on the "dashboard" page and on any page whose parent is "dashboard".
 */
function qtf_is_dashboard_request() {
	if ( ! is_page() ) {
		return false;
	}
	$page = get_queried_object();
	if ( ! $page || empty( $page->post_name ) ) {
		return false;
	}
	if ( 'dashboard' === $page->post_name && 0 === (int) $page->post_parent ) {
		return true;
	}
	if ( $page->post_parent ) {
		$parent = get_post( $page->post_parent );
		return $parent && 'dashboard' === $parent->post_name;
	}
	return false;
}

function qtf_is_account_request() {
	return function_exists( 'is_account_page' ) && is_account_page();
}

/**
 * Which dashboard view the current page shows, chosen by the page slug.
 */
function qtf_current_view() {
	$page = get_queried_object();
	if ( ! $page || empty( $page->post_name ) ) {
		return 'overview';
	}
	$map = array(
		'dashboard'    => 'overview',
		'new-estimate' => 'new',
		'estimate'     => 'estimate',
		'my-estimates' => 'list',
		'credits'      => 'credits',
		'my-reviews'   => 'reviews',
		'settings'     => 'settings',
	);
	return isset( $map[ $page->post_name ] ) ? $map[ $page->post_name ] : 'overview';
}

/**
 * Sidebar items. Team members do not see Credits.
 */
function qtf_menu_items() {
	$items = array(
		'overview' => array( 'label' => __( 'Overview', 'qoutify-estimator' ), 'url' => qtf_dashboard_url(), 'icon' => 'grid' ),
		'new'      => array( 'label' => __( 'New Estimate', 'qoutify-estimator' ), 'url' => qtf_dashboard_url( 'new-estimate' ), 'icon' => 'plus-circle' ),
		'estimate' => array( 'label' => __( 'Estimate', 'qoutify-estimator' ), 'url' => qtf_dashboard_url( 'estimate' ), 'icon' => 'file' ),
		'list'     => array( 'label' => __( 'My Estimates', 'qoutify-estimator' ), 'url' => qtf_dashboard_url( 'my-estimates' ), 'icon' => 'list' ),
	);
	if ( ! qtf_user_is_staff() ) {
		$items['credits'] = array( 'label' => __( 'Credits', 'qoutify-estimator' ), 'url' => qtf_dashboard_url( 'credits' ), 'icon' => 'coins' );
	}
	$items['reviews']  = array(
		'label' => __( 'My Reviews', 'qoutify-estimator' ),
		'url'   => qtf_dashboard_url( 'my-reviews' ),
		'icon'  => 'star',
		'badge' => get_user_meta( get_current_user_id(), 'qtf_reviews_seen', true ) ? '' : __( 'New', 'qoutify-estimator' ),
	);
	$items['settings'] = array( 'label' => __( 'Settings', 'qoutify-estimator' ), 'url' => qtf_dashboard_url( 'settings' ), 'icon' => 'settings' );
	return $items;
}

function qtf_user_first_name( $user = null ) {
	$user = $user ? $user : wp_get_current_user();
	if ( ! $user || ! $user->exists() ) {
		return '';
	}
	if ( $user->first_name ) {
		return $user->first_name;
	}
	return $user->display_name ? $user->display_name : $user->user_login;
}

/**
 * Profile image, or a circle with the first letter of the first name.
 */
function qtf_avatar_html( $user_id = 0 ) {
	$user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();
	if ( ! $user || ! $user->exists() ) {
		return '';
	}
	$url = get_user_meta( $user->ID, 'qtf_avatar_url', true );
	if ( $url ) {
		return '<img src="' . esc_url( $url ) . '" alt="">';
	}
	$name   = qtf_user_first_name( $user );
	$letter = function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $name, 0, 1 ) ) : strtoupper( substr( $name, 0, 1 ) );
	return '<span aria-hidden="true">' . esc_html( $letter ) . '</span>';
}

function qtf_logo_html() {
	$id = (int) get_theme_mod( 'custom_logo' );
	if ( $id ) {
		$img = wp_get_attachment_image( $id, 'full', false, array( 'class' => 'qtf-logo', 'alt' => get_bloginfo( 'name' ) ) );
		if ( $img ) {
			return $img;
		}
	}
	return '<span class="qtf-logo-text">QOUTIFY</span>';
}
