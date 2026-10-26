<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [qtf_auth] - the Account page: login, register, check-email and verified screens.
 * One form is shown at a time. Lost-password pages use WooCommerce's own output.
 * Forms post to WooCommerce, so its login and registration code does the real work.
 */

// Registration: first name is required, password needs 8+ characters and must be confirmed.
function qtf_registration_extra_checks( $errors, $username, $password, $email ) {
	$first   = isset( $_POST['first_name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$confirm = isset( $_POST['password2'] ) ? (string) wp_unslash( $_POST['password2'] ) : ''; // phpcs:ignore WordPress.Security
	if ( '' === $first ) {
		$errors->add( 'qtf_first_name', __( 'Please enter your first name.', 'qoutify-estimator' ) );
	} elseif ( strlen( (string) $password ) < 8 ) {
		$errors->add( 'qtf_password_short', __( 'Your password must be at least 8 characters.', 'qoutify-estimator' ) );
	} elseif ( (string) $password !== $confirm ) {
		$errors->add( 'qtf_password_match', __( 'The passwords do not match.', 'qoutify-estimator' ) );
	}
	return $errors;
}
add_filter( 'woocommerce_process_registration_errors', 'qtf_registration_extra_checks', 10, 4 );

// Save the first name so the header can show its first letter.
function qtf_new_customer_data( $data ) {
	if ( ! empty( $_POST['first_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$first                = sanitize_text_field( wp_unslash( $_POST['first_name'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$data['first_name']   = $first;
		$data['display_name'] = $first;
		$data['nickname']     = $first;
	}
	return $data;
}
add_filter( 'woocommerce_new_customer_data', 'qtf_new_customer_data' );

function qtf_auth_redirect_value() {
	if ( empty( $_GET['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return '';
	}
	return wp_validate_redirect( esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ), '' ); // phpcs:ignore WordPress.Security.NonceVerification
}

function qtf_auth_google_button() {
	if ( ! shortcode_exists( 'nextend_social_login' ) ) {
		return '';
	}
	$html  = '<div class="qtf-auth__or"><span>' . esc_html__( 'or', 'qoutify-estimator' ) . '</span></div>';
	$html .= do_shortcode( '[nextend_social_login provider="google" redirect="' . esc_url( qtf_dashboard_url() ) . '"]' );
	return $html;
}

function qtf_auth_icon( $type ) {
	if ( 'check' === $type ) {
		$path = '<path d="M20 6 9 17l-5-5"></path>';
	} else {
		$path = '<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m3 7 9 6 9-6"></path>';
	}
	return '<div class="qtf-auth__icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg></div>';
}

function qtf_auth_login_view() {
	$redirect = qtf_auth_redirect_value();
	$username = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : ''; // phpcs:ignore WordPress.Security
	ob_start();
	?>
	<div class="qtf-auth">
		<div class="qtf-auth__card">
			<h1 class="qtf-auth__title"><?php esc_html_e( 'Welcome back', 'qoutify-estimator' ); ?></h1>
			<p class="qtf-auth__sub"><?php esc_html_e( 'Log in to your Qoutify account', 'qoutify-estimator' ); ?></p>
			<?php echo qtf_auth_notice_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php wc_print_notices(); ?>
			<form method="post" class="qtf-auth__form">
				<div class="qtf-field">
					<label for="qtf_login_user"><?php esc_html_e( 'Email', 'qoutify-estimator' ); ?></label>
					<input type="text" id="qtf_login_user" name="username" autocomplete="username" value="<?php echo esc_attr( $username ); ?>" required>
				</div>
				<?php echo qtf_password_field( 'qtf_login_pass', 'password', __( 'Password', 'qoutify-estimator' ), 'current-password', __( 'Enter your password', 'qoutify-estimator' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<div class="qtf-auth__row">
					<label class="qtf-check"><input type="checkbox" name="rememberme" value="forever"> <?php esc_html_e( 'Remember me', 'qoutify-estimator' ); ?></label>
					<a href="<?php echo esc_url( wc_lostpassword_url() ); ?>"><?php esc_html_e( 'Forgot password?', 'qoutify-estimator' ); ?></a>
				</div>
				<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
				<input type="hidden" name="redirect" value="<?php echo esc_attr( $redirect ); ?>">
				<button type="submit" class="qtf-btn qtf-btn--primary qtf-btn--block" name="login" value="Log in"><?php esc_html_e( 'Log In', 'qoutify-estimator' ); ?></button>
			</form>
			<?php echo qtf_auth_google_button(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<p class="qtf-auth__switch">
			<?php esc_html_e( "Don't have an account?", 'qoutify-estimator' ); ?>
			<a href="<?php echo esc_url( add_query_arg( 'action', 'register', qtf_account_url() ) ); ?>"><?php esc_html_e( 'Register', 'qoutify-estimator' ); ?></a>
		</p>
	</div>
	<?php
	return ob_get_clean();
}

function qtf_auth_register_view() {
	$first = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : ''; // phpcs:ignore WordPress.Security
	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : ''; // phpcs:ignore WordPress.Security
	ob_start();
	?>
	<div class="qtf-auth">
		<div class="qtf-auth__card">
			<h1 class="qtf-auth__title"><?php esc_html_e( 'Create your account', 'qoutify-estimator' ); ?></h1>
			<p class="qtf-auth__sub"><?php esc_html_e( 'Sign up to start estimating your projects', 'qoutify-estimator' ); ?></p>
			<?php wc_print_notices(); ?>
			<form method="post" class="qtf-auth__form">
				<div class="qtf-field">
					<label for="qtf_reg_first"><?php esc_html_e( 'First name', 'qoutify-estimator' ); ?></label>
					<input type="text" id="qtf_reg_first" name="first_name" autocomplete="given-name" value="<?php echo esc_attr( $first ); ?>" required>
				</div>
				<div class="qtf-field">
					<label for="qtf_reg_email"><?php esc_html_e( 'Email', 'qoutify-estimator' ); ?></label>
					<input type="email" id="qtf_reg_email" name="email" autocomplete="email" value="<?php echo esc_attr( $email ); ?>" required>
				</div>
				<?php echo qtf_password_field( 'qtf_reg_pass', 'password', __( 'Password (at least 8 characters)', 'qoutify-estimator' ), 'new-password', __( 'Create a password', 'qoutify-estimator' ), 8 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php echo qtf_password_field( 'qtf_reg_pass2', 'password2', __( 'Confirm password', 'qoutify-estimator' ), 'new-password', __( 'Re-enter your password', 'qoutify-estimator' ), 8 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
				<button type="submit" class="qtf-btn qtf-btn--primary qtf-btn--block" name="register" value="Register"><?php esc_html_e( 'Create Account', 'qoutify-estimator' ); ?></button>
			</form>
			<?php echo qtf_auth_google_button(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<p class="qtf-auth__switch">
			<?php esc_html_e( 'Already have an account?', 'qoutify-estimator' ); ?>
			<a href="<?php echo esc_url( qtf_account_url() ); ?>"><?php esc_html_e( 'Log in', 'qoutify-estimator' ); ?></a>
		</p>
	</div>
	<?php
	return ob_get_clean();
}

function qtf_auth_check_email_view() {
	$key     = isset( $_GET['k'] ) ? sanitize_key( wp_unslash( $_GET['k'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$user_id = $key ? (int) get_transient( 'qtf_ce_' . $key ) : 0;
	$user    = $user_id ? get_userdata( $user_id ) : false;
	if ( ! $user ) {
		return qtf_auth_login_view();
	}
	$sent = isset( $_GET['sent'] ) ? sanitize_key( wp_unslash( $_GET['sent'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	ob_start();
	?>
	<div class="qtf-auth">
		<div class="qtf-auth__card qtf-auth__card--center">
			<h1 class="qtf-auth__title qtf-auth__title--left"><?php esc_html_e( 'Check Your Email', 'qoutify-estimator' ); ?></h1>
			<?php echo qtf_auth_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<p class="qtf-auth__lead"><?php esc_html_e( 'Your account has been created successfully.', 'qoutify-estimator' ); ?></p>
			<p class="qtf-auth__small"><?php esc_html_e( "We've sent a verification email to", 'qoutify-estimator' ); ?></p>
			<p class="qtf-auth__email"><?php echo esc_html( $user->user_email ); ?></p>
			<p class="qtf-auth__small"><?php esc_html_e( 'Please open your inbox and click the verification link to activate your account.', 'qoutify-estimator' ); ?></p>
			<?php if ( 'ok' === $sent ) : ?>
				<div class="qtf-notice qtf-notice--success" role="status"><?php esc_html_e( 'A new verification email was sent.', 'qoutify-estimator' ); ?></div>
			<?php elseif ( 'wait' === $sent ) : ?>
				<div class="qtf-notice qtf-notice--info" role="status"><?php esc_html_e( 'Please wait a minute before asking for another email.', 'qoutify-estimator' ); ?></div>
			<?php endif; ?>
			<form method="post" class="qtf-auth__form">
				<?php wp_nonce_field( 'qtf_resend_form' ); ?>
				<input type="hidden" name="qtf_resend_key" value="<?php echo esc_attr( $key ); ?>">
				<button type="submit" class="qtf-btn qtf-btn--primary qtf-btn--block"><?php esc_html_e( 'Resend Verification Email', 'qoutify-estimator' ); ?></button>
			</form>
			<p class="qtf-auth__back"><a href="<?php echo esc_url( qtf_account_url() ); ?>"><?php esc_html_e( 'Back to Login', 'qoutify-estimator' ); ?></a></p>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

function qtf_auth_verified_view() {
	ob_start();
	?>
	<div class="qtf-auth">
		<div class="qtf-auth__card qtf-auth__card--center">
			<?php echo qtf_auth_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<h1 class="qtf-auth__title"><?php esc_html_e( 'Email Verified', 'qoutify-estimator' ); ?></h1>
			<p class="qtf-auth__lead"><?php esc_html_e( 'Your account is active. Taking you to your dashboard.', 'qoutify-estimator' ); ?></p>
			<a class="qtf-btn qtf-btn--primary qtf-btn--block" data-qtf-redirect href="<?php echo esc_url( qtf_dashboard_url() ); ?>"><?php esc_html_e( 'Go to Dashboard', 'qoutify-estimator' ); ?></a>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

function qtf_sc_auth() {
	// Inside the Elementor editor, always show the login form so the page can be designed.
	if ( qtf_is_builder_request() ) {
		return qtf_auth_login_view();
	}

	// Lost password, reset password and other WooCommerce pages keep WooCommerce's own output.
	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url() ) {
		return do_shortcode( '[woocommerce_my_account]' );
	}

	$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

	if ( is_user_logged_in() ) {
		if ( 'verified' === $action ) {
			return qtf_auth_verified_view();
		}
		return '<p class="qtf-auth__switch"><a href="' . esc_url( qtf_dashboard_url() ) . '">' . esc_html__( 'Go to your dashboard', 'qoutify-estimator' ) . '</a></p>';
	}

	if ( isset( $_POST['register'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$action = 'register';
	}

	switch ( $action ) {
		case 'register':
			return qtf_auth_register_view();
		case 'check-email':
			return qtf_auth_check_email_view();
		default:
			return qtf_auth_login_view();
	}
}
add_shortcode( 'qtf_auth', 'qtf_sc_auth' );

/**
 * [qtf_header_account] - for the public header. Logged out: Get Started button.
 * Logged in: profile image (or first letter) that opens the dashboard.
 */
function qtf_sc_header_account( $atts ) {
	$atts = shortcode_atts( array( 'label' => 'Get Started' ), $atts, 'qtf_header_account' );
	if ( is_user_logged_in() ) {
		return '<a class="qtf-header-avatar qtf-avatar" href="' . esc_url( qtf_dashboard_url() ) . '" title="' . esc_attr__( 'Dashboard', 'qoutify-estimator' ) . '" aria-label="' . esc_attr__( 'Dashboard', 'qoutify-estimator' ) . '">' . qtf_avatar_html() . '</a>';
	}
	return '<a class="qtf-btn qtf-btn--primary" href="' . esc_url( qtf_account_url() ) . '">' . esc_html( $atts['label'] ) . ' &rarr;</a>';
}
add_shortcode( 'qtf_header_account', 'qtf_sc_header_account' );
