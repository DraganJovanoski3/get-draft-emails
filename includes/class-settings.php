<?php
/**
 * Plugin settings and master on/off switch.
 */

defined( 'ABSPATH' ) || exit;

class DOEC_Settings {

	const OPTION_AUTO_CAPTURE = 'doec_auto_capture';
	const OPTION_PAUSED       = 'doec_paused';

	/** @var DOEC_Settings|null */
	private static $instance = null;

	public static function instance(): DOEC_Settings {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_doec_save_settings', array( $this, 'handle_save' ) );
	}

	public static function is_paused(): bool {
		return 'yes' === get_option( self::OPTION_PAUSED, 'no' );
	}

	public static function is_auto_capture_enabled(): bool {
		if ( self::is_paused() ) {
			return false;
		}

		return 'yes' === get_option( self::OPTION_AUTO_CAPTURE, 'no' );
	}

	public function handle_save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'draft-orders-get-email-customers' ) );
		}

		check_admin_referer( 'doec_save_settings' );

		$paused       = isset( $_POST['doec_paused'] ) ? 'yes' : 'no';
		$auto_capture = isset( $_POST['doec_auto_capture'] ) ? 'yes' : 'no';

		if ( 'yes' === $paused ) {
			$auto_capture = 'no';
			wp_clear_scheduled_hook( 'doec_sync_draft_orders' );
		}

		update_option( self::OPTION_PAUSED, $paused );
		update_option( self::OPTION_AUTO_CAPTURE, $auto_capture );

		if ( 'yes' === $auto_capture && ! wp_next_scheduled( 'doec_sync_draft_orders' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'doec_sync_draft_orders' );
		}

		if ( 'no' === $auto_capture ) {
			wp_clear_scheduled_hook( 'doec_sync_draft_orders' );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=doec-draft-emails&settings_saved=1' ) );
		exit;
	}
}
