<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email verification for people who register with the form on the Account page.
 * Google sign-ins and accounts created by an admin have no flag and count as verified.
 * A verification email is sent ONLY to a brand-new, unverified account.
 *
 * Flow: register -> "Check your email" page -> click the link -> "Email verified" page -> dashboard.
 */

// New customers start unverified and get the link. Then they see the "Check your email" screen.
// WooCommerce does not redirect when it does not log the customer in, so we redirect here.
function qtf_mark_unverified( $user_id ) {
	update_user_meta( $user_id, 'qtf_email_verified', '0' );
	qtf_send_verification_email( $user_id );

	// Only for signups from our Account page form (not for checkout or other code).
	if ( isset( $_POST['register'], $_POST['woocommerce-register-nonce'] ) && ! wp_doing_ajax() ) { // phpcs:ignore WordPress.Security.NonceVerification
		wp_safe_redirect( qtf_check_email_url( $user_id ) );
		exit;
	}
}
add_action( 'woocommerce_created_customer', 'qtf_mark_unverified', 99, 1 );

// Do not log new customers in until they verify.
add_filter( 'woocommerce_registration_auth_new_customer', '__return_false' );

/**
 * Address of the "Check your email" page. The key hides the user id and expires in one hour.
 */
function qtf_check_email_url( $user_id ) {
	$key = strtolower( wp_generate_password( 20, false, false ) );
	set_transient( 'qtf_ce_' . $key, (int) $user_id, HOUR_IN_SECONDS );
	return add_query_arg(
		array(
			'action' => 'check-email',
			'k'      => $key,
		),
		qtf_account_url()
	);
}

function qtf_send_verification_email( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return false;
	}
	$token = wp_generate_password( 40, false, false );
	update_user_meta( $user_id, 'qtf_verify_hash', hash( 'sha256', $token ) );
	update_user_meta( $user_id, 'qtf_verify_expires', time() + 2 * DAY_IN_SECONDS );
	set_transient( 'qtf_last_send_' . $user_id, 1, MINUTE_IN_SECONDS );

	$link    = add_query_arg(
		array(
			'qtf_verify' => $token,
			'uid'        => $user_id,
		),
		home_url( '/' )
	);
	$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$subject = sprintf( __( 'Verify your email for %s', 'qoutify-estimator' ), $site );
	$message = sprintf(
		"Hi %s,\n\nPlease confirm your email address to activate your account:\n\n%s\n\nThis link works for 48 hours. If you did not create this account, you can ignore this email.\n\n%s",
		qtf_user_first_name( $user ),
		$link,
		$site
	);
	return wp_mail( $user->user_email, $subject, $message );
}

// Click on the link in the email: verify, log in, show the "Email verified" page.
function qtf_handle_verify_link() {
	if ( empty( $_GET['qtf_verify'] ) || empty( $_GET['uid'] ) ) {
		return;
	}
	$user_id = absint( $_GET['uid'] );
	$token   = sanitize_text_field( wp_unslash( $_GET['qtf_verify'] ) );
	$user    = get_userdata( $user_id );
	$hash    = (string) get_user_meta( $user_id, 'qtf_verify_hash', true );
	$expires = (int) get_user_meta( $user_id, 'qtf_verify_expires', true );

	if ( ! $user || '' === $hash || time() > $expires || ! hash_equals( $hash, hash( 'sha256', $token ) ) ) {
		wp_safe_redirect( add_query_arg( 'qtf_notice', 'verify_failed', qtf_account_url() ) );
		exit;
	}

	update_user_meta( $user_id, 'qtf_email_verified', '1' );
	delete_user_meta( $user_id, 'qtf_verify_hash' );
	delete_user_meta( $user_id, 'qtf_verify_expires' );

	wp_clear_auth_cookie();
	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true );
	do_action( 'wp_login', $user->user_login, $user );

	wp_safe_redirect( add_query_arg( 'action', 'verified', qtf_account_url() ) );
	exit;
}
add_action( 'init', 'qtf_handle_verify_link', 5 );

// Unverified users cannot log in. This runs only after the password was correct.
function qtf_block_unverified( $user ) {
	if ( $user instanceof WP_User && '0' === (string) get_user_meta( $user->ID, 'qtf_email_verified', true ) ) {
		$url = wp_nonce_url( add_query_arg( 'qtf_resend', $user->ID, qtf_account_url() ), 'qtf_resend_' . $user->ID );
		$msg = sprintf(
			/* translators: %s: resend link */
			__( 'Please verify your email before logging in. Check your inbox for the verification link, or <a href="%s">send it again</a>.', 'qoutify-estimator' ),
			esc_url( $url )
		);
		return new WP_Error( 'qtf_unverified', $msg );
	}
	return $user;
}
add_filter( 'authenticate', 'qtf_block_unverified', 40, 1 );

/**
 * Send the email again. Never sends to an account that is already verified.
 * Returns: 'sent', 'wait' or 'verified'.
 */
function qtf_resend_verification( $user_id ) {
	if ( '0' !== (string) get_user_meta( $user_id, 'qtf_email_verified', true ) ) {
		return 'verified';
	}
	if ( get_transient( 'qtf_last_send_' . $user_id ) ) {
		return 'wait';
	}
	qtf_send_verification_email( $user_id );
	return 'sent';
}

// "Send it again" link from the login error message.
function qtf_handle_resend_link() {
	if ( empty( $_GET['qtf_resend'] ) ) {
		return;
	}
	$user_id = absint( $_GET['qtf_resend'] );
	$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
	if ( ! $user_id || ! wp_verify_nonce( $nonce, 'qtf_resend_' . $user_id ) ) {
		return;
	}
	$result = qtf_resend_verification( $user_id );
	$code   = array(
		'sent'     => 'resent',
		'wait'     => 'wait',
		'verified' => 'already',
	);
	wp_safe_redirect( add_query_arg( 'qtf_notice', $code[ $result ], qtf_account_url() ) );
	exit;
}
add_action( 'init', 'qtf_handle_resend_link', 5 );

// "Resend Verification Email" button on the "Check your email" page.
function qtf_handle_resend_form() {
	if ( empty( $_POST['qtf_resend_key'] ) ) {
		return;
	}
	$key   = sanitize_key( wp_unslash( $_POST['qtf_resend_key'] ) );
	$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'qtf_resend_form' ) ) {
		return;
	}
	$user_id = (int) get_transient( 'qtf_ce_' . $key );
	if ( ! $user_id ) {
		wp_safe_redirect( qtf_account_url() );
		exit;
	}
	$result = qtf_resend_verification( $user_id );
	if ( 'verified' === $result ) {
		wp_safe_redirect( add_query_arg( 'qtf_notice', 'already', qtf_account_url() ) );
		exit;
	}
	wp_safe_redirect(
		add_query_arg(
			array(
				'action' => 'check-email',
				'k'      => $key,
				'sent'   => ( 'sent' === $result ) ? 'ok' : 'wait',
			),
			qtf_account_url()
		)
	);
	exit;
}
add_action( 'init', 'qtf_handle_resend_form', 5 );

// Small messages shown above the login form.
function qtf_auth_notice_html() {
	$messages = array(
		'resent'        => array( __( 'A new verification email was sent. Check your inbox.', 'qoutify-estimator' ), 'success' ),
		'wait'          => array( __( 'Please wait a minute before asking for another email.', 'qoutify-estimator' ), 'info' ),
		'already'       => array( __( 'Your email is already verified. Please log in.', 'qoutify-estimator' ), 'success' ),
		'verify_failed' => array( __( 'This verification link is invalid, expired or already used. If you already verified, log in below.', 'qoutify-estimator' ), 'error' ),
		'deleted'       => array( __( 'Your account was deleted.', 'qoutify-estimator' ), 'success' ),
	);
	$code = isset( $_GET['qtf_notice'] ) ? sanitize_key( wp_unslash( $_GET['qtf_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! isset( $messages[ $code ] ) ) {
		return '';
	}
	return '<div class="qtf-notice qtf-notice--' . esc_attr( $messages[ $code ][1] ) . '" role="status">' . esc_html( $messages[ $code ][0] ) . '</div>';
}
