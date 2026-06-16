<?php
/**
 * CSV export for captured leads.
 */

defined( 'ABSPATH' ) || exit;

class DOEC_Export {

	/** @var DOEC_Export|null */
	private static $instance = null;

	public static function instance(): DOEC_Export {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_doec_export_csv', array( $this, 'export_csv' ) );
	}

	public function export_csv(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'draft-orders-get-email-customers' ) );
		}

		check_admin_referer( 'doec_export_csv' );

		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		$leads = DOEC_Database::instance()->get_leads_for_export(
			array(
				'status' => $status,
				'search' => $search,
			)
		);

		$filename = 'draft-order-emails-' . gmdate( 'Y-m-d' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			exit;
		}

		// UTF-8 BOM for Excel compatibility.
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		fputcsv(
			$output,
			array(
				'Email',
				'First Name',
				'Last Name',
				'Phone',
				'Order ID',
				'Order Total',
				'Currency',
				'Items',
				'Status',
				'Captured At',
			)
		);

		foreach ( $leads as $lead ) {
			fputcsv(
				$output,
				array(
					$lead->email,
					$lead->first_name,
					$lead->last_name,
					$lead->phone,
					$lead->order_id,
					$lead->order_total,
					$lead->currency,
					$lead->item_count,
					$lead->status,
					$lead->captured_at,
				)
			);
		}

		fclose( $output );
		exit;
	}
}
