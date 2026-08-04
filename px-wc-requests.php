<?php
/**
 * Plugin Name: Pixeler Woo Requests
 * Plugin URI: https://pixeler.sk
 * Description: Universal customer-request system for WooCommerce — withdrawal from contract and warranty claims. Requests stored as a CPT with custom statuses, configurable fields (including IBAN), emails and admin UI. Type/config driven and portable between eshops.
 * Version: 1.3.1
 * Author: Roman Kraiger | Pixeler s. r. o.
 * Author URI: https://pixeler.sk
 * Requires Plugins: woocommerce
 * Requires PHP: 8.0
 * Text Domain: px-wc-requests
 * Domain Path: /languages
 *
 * @package Pixeler\Requests
 */

defined( 'ABSPATH' ) || exit;

define( 'PXER_VERSION', '1.3.1' );
define( 'PXER_FILE', __FILE__ );
define( 'PXER_PATH', plugin_dir_path( __FILE__ ) );
define( 'PXER_URL', plugin_dir_url( __FILE__ ) );
define( 'PXER_TEMPLATE_PATH', PXER_PATH . 'templates/' );

/**
 * Boot the plugin once all plugins (incl. WooCommerce) are loaded.
 */
function pxer_bootstrap(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', static function () {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'Pixeler Woo Requests requires the WooCommerce plugin to be active.', 'px-wc-requests' );
			echo '</p></div>';
		} );

		return;
	}

	require_once PXER_PATH . 'inc/helpers.php';
	require_once PXER_PATH . 'inc/CustomPostStatus.php';
	require_once PXER_PATH . 'inc/RequestTypes.php';
	require_once PXER_PATH . 'inc/FieldSchema.php';
	require_once PXER_PATH . 'inc/Eligibility.php';
	require_once PXER_PATH . 'inc/Security.php';
	require_once PXER_PATH . 'inc/PrivateFiles.php';
	require_once PXER_PATH . 'inc/TemplateLoader.php';
	require_once PXER_PATH . 'inc/Request.php';
	require_once PXER_PATH . 'inc/RequestPostType.php';
	require_once PXER_PATH . 'inc/RequestNotes.php';
	require_once PXER_PATH . 'inc/RequestController.php';
	require_once PXER_PATH . 'inc/Emails.php';
	require_once PXER_PATH . 'inc/EmptyEmailsBridge.php';
	require_once PXER_PATH . 'inc/Settings.php';
	require_once PXER_PATH . 'inc/ProductSettings.php';
	require_once PXER_PATH . 'inc/MyAccount.php';
	require_once PXER_PATH . 'inc/Privacy.php';
	require_once PXER_PATH . 'inc/RestApi.php';
	require_once PXER_PATH . 'inc/Migrator.php';
	require_once PXER_PATH . 'inc/Shortcodes.php';

	require_once PXER_PATH . 'inc/Plugin.php';
	\Pixeler\Requests\Plugin::instance()->run();
}
add_action( 'plugins_loaded', 'pxer_bootstrap', 11 );

// Updates from GitHub. Deliberately outside pxer_bootstrap(): the plugin does
// nothing without WooCommerce, but it must always be updatable.
require_once PXER_PATH . 'inc/Updater.php';
add_action( 'init', 'pxer_init_updater' );

/**
 * Activation: register types + statuses, then flush rewrite rules.
 */
register_activation_hook( __FILE__, static function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	require_once PXER_PATH . 'inc/helpers.php';
	require_once PXER_PATH . 'inc/CustomPostStatus.php';
	require_once PXER_PATH . 'inc/RequestTypes.php';
	require_once PXER_PATH . 'inc/FieldSchema.php';
	require_once PXER_PATH . 'inc/TemplateLoader.php';
	require_once PXER_PATH . 'inc/Request.php';
	require_once PXER_PATH . 'inc/RequestPostType.php';
	require_once PXER_PATH . 'inc/PrivateFiles.php';
	require_once PXER_PATH . 'inc/MyAccount.php';

	\Pixeler\Requests\RequestPostType::register_post_type();
	\Pixeler\Requests\RequestPostType::register_statuses();
	\Pixeler\Requests\MyAccount::register_endpoint();
	\Pixeler\Requests\PrivateFiles::protect_dir();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, static function () {
	flush_rewrite_rules();
} );
