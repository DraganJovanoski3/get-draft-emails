<?php
/**
 * Plugin Name: Draft Email Collector
 * Plugin URI:  https://github.com/DraganJovanoski3/get-draft-emails
 * Description: Export customer emails from WooCommerce draft orders. Manual sync only by default — no background hooks until you turn it on.
 * Version:     1.1.0
 * Author:      Custom
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: draft-orders-get-email-customers
 * License:     GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'DOEC_VERSION', '1.1.0' );
define( 'DOEC_PLUGIN_FILE', __FILE__ );
define( 'DOEC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DOEC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin bootstrap.
 */
final class DOEC_Plugin {

	/** @var DOEC_Plugin|null */
	private static $instance = null;

	public static function instance(): DOEC_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		register_activation_hook( DOEC_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( DOEC_PLUGIN_FILE, array( $this, 'deactivate' ) );
	}

	public function init(): void {
		require_once DOEC_PLUGIN_DIR . 'includes/class-settings.php';
		require_once DOEC_PLUGIN_DIR . 'includes/class-database.php';
		require_once DOEC_PLUGIN_DIR . 'includes/class-capture.php';
		require_once DOEC_PLUGIN_DIR . 'includes/class-admin.php';
		require_once DOEC_PLUGIN_DIR . 'includes/class-export.php';

		DOEC_Settings::instance();
		DOEC_Database::instance();
		DOEC_Capture::instance();
		DOEC_Admin::instance();
		DOEC_Export::instance();

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
		}
	}

	public function activate(): void {
		require_once DOEC_PLUGIN_DIR . 'includes/class-database.php';
		DOEC_Database::create_table();

		if ( false === get_option( 'doec_auto_capture', false ) ) {
			add_option( 'doec_auto_capture', 'no' );
		}

		if ( false === get_option( 'doec_paused', false ) ) {
			add_option( 'doec_paused', 'no' );
		}
	}

	public function deactivate(): void {
		wp_clear_scheduled_hook( 'doec_sync_draft_orders' );
	}

	public function woocommerce_missing_notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'tools_page_doec-draft-emails' !== $screen->id ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'WooCommerce is not active. Install WooCommerce to sync draft order emails.', 'draft-orders-get-email-customers' )
		);
	}
}

DOEC_Plugin::instance();
