<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qtf_back( $code ) {
	wp_safe_redirect( add_query_arg( 'qtf_msg', $code, qtf_dashboard_url( 'settings' ) ) );
	exit;
}

/**
 * Handles the forms on the Settings page (they post to the page itself).
 */
function qtf_process_settings_post() {
	if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST['qtf_action'] ) ) {
		return;
	}
	if ( ! is_user_logged_in() || ! qtf_is_dashboard_request() || 'settings' !== qtf_current_view() ) {
		return;
	}

	$action = sanitize_key( wp_unslash( $_POST['qtf_action'] ) );
	$nonce  = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'qtf_' . $action ) ) {
		qtf_back( 'nonce' );
	}

	switch ( $action ) {
		case 'profile':
			qtf_do_profile();
			break;
		case 'avatar':
			qtf_do_avatar();
			break;
		case 'avatar_remove':
			qtf_remove_avatar( get_current_user_id() );
			qtf_back( 'avatar_removed' );
			break;
		case 'password':
			qtf_do_password();
			break;
		case 'delete':
			qtf_do_delete();
			break;
	}
}
add_action( 'template_redirect', 'qtf_process_settings_post', 2 );

function qtf_avatar_upload_dir( $dirs ) {
	$dirs['subdir'] = '/qtf-avatars';
	$dirs['path']   = $dirs['basedir'] . '/qtf-avatars';
	$dirs['url']    = $dirs['baseurl'] . '/qtf-avatars';
	return $dirs;
}

function qtf_remove_avatar( $user_id ) {
	$file = get_user_meta( $user_id, 'qtf_avatar_file', true );
	if ( $file && file_exists( $file ) ) {
		wp_delete_file( $file );
	}
	delete_user_meta( $user_id, 'qtf_avatar_file' );
	delete_user_meta( $user_id, 'qtf_avatar_url' );
}

function qtf_do_avatar() {
	if ( empty( $_FILES['qtf_avatar']['name'] ) ) {
		qtf_back( 'avatar_none' );
	}
	$file = $_FILES['qtf_avatar']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( ! empty( $file['error'] ) ) {
		qtf_back( 'avatar_bad' );
	}
	if ( $file['size'] > 2 * MB_IN_BYTES ) {
		qtf_back( 'avatar_big' );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	$mimes = array(
		'jpg|jpeg' => 'image/jpeg',
		'png'      => 'image/png',
		'webp'     => 'image/webp',
	);

	add_filter( 'upload_dir', 'qtf_avatar_upload_dir' );
	$result = wp_handle_upload( $file, array( 'test_form' => false, 'mimes' => $mimes ) );
	remove_filter( 'upload_dir', 'qtf_avatar_upload_dir' );

	if ( ! is_array( $result ) || isset( $result['error'] ) || empty( $result['file'] ) ) {
		qtf_back( 'avatar_bad' );
	}

	// It must be a real image. Resize to a 256 px square.
	$editor = wp_get_image_editor( $result['file'] );
	if ( is_wp_error( $editor ) ) {
		wp_delete_file( $result['file'] );
		qtf_back( 'avatar_bad' );
	}
	$editor->resize( 256, 256, true );

	$user_id = get_current_user_id();
	$ext     = strtolower( pathinfo( $result['file'], PATHINFO_EXTENSION ) );
	$name    = 'u' . $user_id . '-' . wp_generate_password( 10, false, false ) . '.' . $ext;
	$path    = trailingslashit( dirname( $result['file'] ) ) . $name;
	$saved   = $editor->save( $path );

	wp_delete_file( $result['file'] );
	if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
		qtf_back( 'avatar_bad' );
	}

	qtf_remove_avatar( $user_id );
	update_user_meta( $user_id, 'qtf_avatar_file', $saved['path'] );
	update_user_meta( $user_id, 'qtf_avatar_url', trailingslashit( dirname( $result['url'] ) ) . basename( $saved['path'] ) );
	qtf_back( 'avatar_ok' );
}

function qtf_do_password() {
	$user    = wp_get_current_user();
	$current = isset( $_POST['current_password'] ) ? wp_unslash( $_POST['current_password'] ) : ''; // phpcs:ignore WordPress.Security
	$new     = isset( $_POST['new_password'] ) ? wp_unslash( $_POST['new_password'] ) : ''; // phpcs:ignore WordPress.Security
	$confirm = isset( $_POST['confirm_password'] ) ? wp_unslash( $_POST['confirm_password'] ) : ''; // phpcs:ignore WordPress.Security

	if ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
		qtf_back( 'pw_wrong' );
	}
	if ( strlen( $new ) < 8 ) {
		qtf_back( 'pw_short' );
	}
	if ( $new !== $confirm ) {
		qtf_back( 'pw_mismatch' );
	}

	wp_update_user( array( 'ID' => $user->ID, 'user_pass' => $new ) );
	// Changing the password logs the user out, so log them in again.
	wp_clear_auth_cookie();
	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, true );
	qtf_back( 'pw_ok' );
}

function qtf_do_delete() {
	$user = wp_get_current_user();
	if ( user_can( $user, 'manage_options' ) ) {
		qtf_back( 'del_admin' );
	}
	$typed = isset( $_POST['confirm_text'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm_text'] ) ) : '';
	$pass  = isset( $_POST['confirm_password'] ) ? wp_unslash( $_POST['confirm_password'] ) : ''; // phpcs:ignore WordPress.Security

	if ( 'DELETE' !== $typed ) {
		qtf_back( 'del_text' );
	}
	if ( ! wp_check_password( $pass, $user->user_pass, $user->ID ) ) {
		qtf_back( 'del_pw' );
	}

	$user_id = $user->ID;
	// Later rounds hook here to delete the user's estimates and uploaded files.
	do_action( 'qtf_before_delete_account', $user_id );
	qtf_remove_avatar( $user_id );

	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_logout();
	wp_delete_user( $user_id );

	wp_safe_redirect( add_query_arg( 'qtf_notice', 'deleted', qtf_account_url() ) );
	exit;
}

/** Saves the name and the optional phone number. The email address cannot be changed here. */
function qtf_do_profile() {
	$user  = wp_get_current_user();
	$name  = isset( $_POST['full_name'] ) ? trim( preg_replace( '/\s+/', ' ', sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) ) ) : '';
	$phone = isset( $_POST['phone'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['phone'] ) ) ) : '';

	$length = function_exists( 'mb_strlen' ) ? mb_strlen( $name ) : strlen( $name );
	if ( $length < 2 || $length > 60 ) {
		qtf_back( 'profile_bad' );
	}
	if ( '' !== $phone && ! preg_match( '/^[0-9+()\-\s.]{5,20}$/', $phone ) ) {
		qtf_back( 'profile_bad' );
	}

	$parts = explode( ' ', $name, 2 );
	wp_update_user(
		array(
			'ID'           => $user->ID,
			'first_name'   => $parts[0],
			'last_name'    => isset( $parts[1] ) ? $parts[1] : '',
			'display_name' => $name,
		)
	);
	if ( '' === $phone ) {
		delete_user_meta( $user->ID, 'qtf_phone' );
	} else {
		update_user_meta( $user->ID, 'qtf_phone', $phone );
	}
	qtf_back( 'profile_ok' );
}
