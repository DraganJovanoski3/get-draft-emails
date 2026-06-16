<?php
/**
 * CSV export for captured leads.
 */

defined( 'ABSPATH' ) || exit;

class DOEC_Export {

	/**
	 * @param array<string,mixed> $args
	 */
	public static function output_csv( array $args = array() ): void {
		$leads = DOEC_Database::instance()->get_leads_for_export( $args );

		$filename = 'draft-order-emails-' . gmdate( 'Y-m-d' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			exit;
		}

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
