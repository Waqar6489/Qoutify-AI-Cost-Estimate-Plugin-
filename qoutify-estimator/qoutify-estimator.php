<?php
/**
 * Plugin Name: Qoutify Estimator
 * Description: Qoutify account pages, dashboard, credits and AI estimation tool.
 * Version: 0.5.0
 * Requires PHP: 7.4
 * Text Domain: qoutify-estimator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'QTF_VERSION', '0.5.0' );
define( 'QTF_FILE', __FILE__ );
define( 'QTF_DIR', plugin_dir_path( __FILE__ ) );
define( 'QTF_URL', plugin_dir_url( __FILE__ ) );

require_once QTF_DIR . 'includes/db.php';
require_once QTF_DIR . 'includes/migrations.php';
require_once QTF_DIR . 'includes/settings.php';
require_once QTF_DIR . 'includes/icons.php';
require_once QTF_DIR . 'includes/pricing.php';
require_once QTF_DIR . 'includes/roles.php';
require_once QTF_DIR . 'includes/helpers.php';
require_once QTF_DIR . 'includes/fields.php';
require_once QTF_DIR . 'includes/estimates.php';
require_once QTF_DIR . 'includes/credits.php';
require_once QTF_DIR . 'includes/purchase.php';
require_once QTF_DIR . 'includes/reviews.php';
require_once QTF_DIR . 'includes/calculator.php';
require_once QTF_DIR . 'includes/ai.php';
require_once QTF_DIR . 'includes/jobs.php';
require_once QTF_DIR . 'includes/verification.php';
require_once QTF_DIR . 'includes/access.php';
require_once QTF_DIR . 'includes/auth.php';
require_once QTF_DIR . 'includes/template.php';
require_once QTF_DIR . 'includes/wizard.php';
require_once QTF_DIR . 'includes/views.php';
require_once QTF_DIR . 'includes/settings-actions.php';
require_once QTF_DIR . 'includes/assets.php';
require_once QTF_DIR . 'includes/admin.php';

register_activation_hook( __FILE__, 'qtf_activate' );
