<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dashboard pages use their own template: new header, sidebar menu, no public header or footer.
 * The page content in WordPress is ignored; the view is chosen by the page slug.
 */
function qtf_dashboard_template( $template ) {
	if ( qtf_is_dashboard_request() && is_user_logged_in() && ! qtf_is_builder_request() ) {
		return QTF_DIR . 'templates/dashboard.php';
	}
	return $template;
}
add_filter( 'template_include', 'qtf_dashboard_template', 99 );
