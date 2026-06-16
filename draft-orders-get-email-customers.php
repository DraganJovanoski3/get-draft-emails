<?php
/**
 * Plugin Name: Draft Email Collector
 * Plugin URI:  https://github.com/DraganJovanoski3/get-draft-emails
 * Description: Standalone tool to export emails from WooCommerce draft orders. Zero hooks into checkout or other plugins — only runs when you click Sync.
 * Version:     1.2.0
 * Author:      Custom
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: draft-orders-get-email-customers
 * License:     GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'DOEC_VERSION', '1.2.0' );
define( 'DOEC_PLUGIN_FILE', __FILE__ );
define( 'DOEC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DOEC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Minimal bootstrap — admin UI only. No WooCommerce hooks on normal page loads.
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
		register_activation_hook( DOEC_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( DOEC_PLUGIN_FILE, array( $this, 'deactivate' ) );

		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_doec_sync_now', array( $this, 'handle_sync' ) );
		add_action( 'admin_post_doec_delete_lead', array( $this, 'handle_delete_lead' ) );
		add_action( 'admin_post_doec_export_csv', array( $this, 'handle_export' ) );
	}

	public function activate(): void {
		self::load_database();
		DOEC_Database::create_table();
	}

	public function deactivate(): void {
		wp_clear_scheduled_hook( 'doec_sync_draft_orders' );
		delete_option( 'doec_auto_capture' );
		delete_option( 'doec_paused' );
	}

	public function register_admin_menu(): void {
		add_management_page(
			__( 'Draft Order Emails', 'draft-orders-get-email-customers' ),
			__( 'Draft Order Emails', 'draft-orders-get-email-customers' ),
			'manage_woocommerce',
			'doec-draft-emails',
			array( $this, 'render_admin_page' )
		);
	}

	public function enqueue_admin_assets( string $hook ): void {
		if ( 'tools_page_doec-draft-emails' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'doec-admin', DOEC_PLUGIN_URL . 'assets/admin.css', array(), DOEC_VERSION );
	}

	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		self::load_database();

		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$page   = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

		$result = DOEC_Database::instance()->get_leads(
			array(
				'status'   => $status,
				'search'   => $search,
				'page'     => $page,
				'per_page' => 20,
			)
		);

		$counts      = DOEC_Database::instance()->get_counts();
		$total       = $result['total'];
		$total_pages = (int) ceil( $total / 20 );

		include DOEC_PLUGIN_DIR . 'templates/admin-page.php';
	}

	public function handle_sync(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'draft-orders-get-email-customers' ) );
		}

		check_admin_referer( 'doec_sync_now' );

		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=doec-draft-emails&wc_missing=1' ) );
			exit;
		}

		self::load_sync();
		$stats = DOEC_Sync::run_full_sync();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => 'doec-draft-emails',
					'synced'        => 1,
					'doec_scanned'  => $stats['scanned'],
					'doec_captured' => $stats['captured'],
					'doec_skipped'  => $stats['skipped_no_email'],
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_delete_lead(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'draft-orders-get-email-customers' ) );
		}

		check_admin_referer( 'doec_delete_lead' );

		self::load_database();

		$id = isset( $_GET['lead_id'] ) ? (int) $_GET['lead_id'] : 0;
		if ( $id ) {
			DOEC_Database::instance()->delete_lead( $id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=doec-draft-emails&deleted=1' ) );
		exit;
	}

	public function handle_export(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'draft-orders-get-email-customers' ) );
		}

		check_admin_referer( 'doec_export_csv' );

		self::load_export();

		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		DOEC_Export::output_csv(
			array(
				'status' => $status,
				'search' => $search,
			)
		);
	}

	/**
	 * Order edit URL — only called from admin template during render.
	 */
	public static function get_order_edit_url( int $order_id ): string {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return \Automattic\WooCommerce\Utilities\OrderUtil::get_order_admin_edit_url( $order_id );
		}

		return admin_url( 'post.php?post=' . $order_id . '&action=edit' );
	}

	private static function load_database(): void {
		require_once DOEC_PLUGIN_DIR . 'includes/class-database.php';
		DOEC_Database::instance();
	}

	private static function load_sync(): void {
		self::load_database();
		require_once DOEC_PLUGIN_DIR . 'includes/class-sync.php';
	}

	private static function load_export(): void {
		self::load_database();
		require_once DOEC_PLUGIN_DIR . 'includes/class-export.php';
	}
}

DOEC_Plugin::instance();
