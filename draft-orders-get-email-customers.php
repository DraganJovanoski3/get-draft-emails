<?php
/**
 * Plugin Name: Draft Orders – Get Customer Emails
 * Plugin URI:  https://github.com/DraganJovanoski3/get-draft-emails
 * Description: Capture email addresses from WooCommerce draft/checkout-draft orders for abandoned checkout email marketing. View and export leads from the admin.
 * Version:     1.0.2
 * Author:      Custom
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 10.0
 * Text Domain: draft-orders-get-email-customers
 * License:     GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'DOEC_VERSION', '1.0.2' );
define( 'DOEC_PLUGIN_FILE', __FILE__ );
define( 'DOEC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DOEC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declare compatibility with WooCommerce features (HPOS, block checkout).
 *
 * @see https://developer.woocommerce.com/docs/features/high-performance-order-storage/recipe-book/
 */
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DOEC_PLUGIN_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', DOEC_PLUGIN_FILE, true );
	}
);

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
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		require_once DOEC_PLUGIN_DIR . 'includes/class-database.php';
		require_once DOEC_PLUGIN_DIR . 'includes/class-capture.php';
		require_once DOEC_PLUGIN_DIR . 'includes/class-admin.php';
		require_once DOEC_PLUGIN_DIR . 'includes/class-export.php';

		DOEC_Database::instance();
		DOEC_Capture::instance();
		DOEC_Admin::instance();
		DOEC_Export::instance();
	}

	public function activate(): void {
		require_once DOEC_PLUGIN_DIR . 'includes/class-database.php';
		require_once DOEC_PLUGIN_DIR . 'includes/class-capture.php';
		DOEC_Database::create_table();

		if ( ! wp_next_scheduled( 'doec_sync_draft_orders' ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', 'doec_sync_draft_orders' );
		}
	}

	public function deactivate(): void {
		wp_clear_scheduled_hook( 'doec_sync_draft_orders' );
	}

	public function woocommerce_missing_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Draft Orders – Get Customer Emails requires WooCommerce to be installed and active.', 'draft-orders-get-email-customers' )
		);
	}
}

DOEC_Plugin::instance();
