<?php
/**
 * Admin UI for viewing captured draft-order leads.
 */

defined( 'ABSPATH' ) || exit;

class DOEC_Admin {

	/** @var DOEC_Admin|null */
	private static $instance = null;

	public static function instance(): DOEC_Admin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_doec_delete_lead', array( $this, 'handle_delete_lead' ) );
		add_action( 'admin_post_doec_sync_now', array( $this, 'handle_sync_now' ) );
	}

	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Draft Order Emails', 'draft-orders-get-email-customers' ),
			__( 'Draft Order Emails', 'draft-orders-get-email-customers' ),
			'manage_woocommerce',
			'doec-draft-emails',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'woocommerce_page_doec-draft-emails' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'doec-admin',
			DOEC_PLUGIN_URL . 'assets/admin.css',
			array(),
			DOEC_VERSION
		);
	}

	public function handle_delete_lead(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'draft-orders-get-email-customers' ) );
		}

		check_admin_referer( 'doec_delete_lead' );

		$id = isset( $_GET['lead_id'] ) ? (int) $_GET['lead_id'] : 0;
		if ( $id ) {
			DOEC_Database::instance()->delete_lead( $id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=doec-draft-emails&deleted=1' ) );
		exit;
	}

	public function handle_sync_now(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'draft-orders-get-email-customers' ) );
		}

		check_admin_referer( 'doec_sync_now' );
		DOEC_Capture::instance()->sync_existing_drafts();

		wp_safe_redirect( admin_url( 'admin.php?page=doec-draft-emails&synced=1' ) );
		exit;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

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

		$counts     = DOEC_Database::instance()->get_counts();
		$total      = $result['total'];
		$total_pages = (int) ceil( $total / 20 );

		include DOEC_PLUGIN_DIR . 'templates/admin-page.php';
	}
}
