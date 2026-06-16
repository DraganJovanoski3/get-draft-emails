<?php
/**
 * On-demand sync only — no WordPress or WooCommerce hooks registered.
 */

defined( 'ABSPATH' ) || exit;

class DOEC_Sync {

	/** @var string[] */
	private const DRAFT_STATUSES = array( 'checkout-draft', 'draft' );

	/**
	 * @return array{scanned: int, captured: int, skipped_no_email: int}
	 */
	public static function run_full_sync(): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array(
				'scanned'          => 0,
				'captured'         => 0,
				'skipped_no_email' => 0,
			);
		}

		if ( function_exists( 'wc_set_time_limit' ) ) {
			wc_set_time_limit( 300 );
		} else {
			@set_time_limit( 300 );
		}

		$stats = array(
			'scanned'          => 0,
			'captured'         => 0,
			'skipped_no_email' => 0,
		);

		$draft_total = wc_get_orders(
			array(
				'status'   => self::DRAFT_STATUSES,
				'limit'    => 1,
				'paginate' => true,
				'return'   => 'ids',
			)
		);
		$stats['scanned'] = isset( $draft_total->total ) ? (int) $draft_total->total : 0;

		$order_ids = self::get_order_ids_with_email_sql( self::DRAFT_STATUSES );

		if ( empty( $order_ids ) && $stats['scanned'] > 0 ) {
			$page     = 1;
			$per_page = 200;

			do {
				$query = wc_get_orders(
					array(
						'status'   => self::DRAFT_STATUSES,
						'limit'    => $per_page,
						'page'     => $page,
						'orderby'  => 'date',
						'order'    => 'DESC',
						'paginate' => true,
						'return'   => 'objects',
					)
				);

				if ( empty( $query->orders ) ) {
					break;
				}

				foreach ( $query->orders as $order ) {
					if ( self::capture_from_order( $order, 'abandoned' ) ) {
						++$stats['captured'];
					}
				}

				++$page;
			} while ( $page <= (int) $query->max_num_pages );
		} else {
			foreach ( $order_ids as $order_id ) {
				$order = wc_get_order( (int) $order_id );
				if ( ! $order ) {
					continue;
				}

				if ( self::capture_from_order( $order, 'abandoned' ) ) {
					++$stats['captured'];
				}
			}
		}

		$abandoned_ids = self::get_order_ids_with_email_sql( array( 'pending', 'failed', 'cancelled' ) );
		foreach ( $abandoned_ids as $order_id ) {
			$order = wc_get_order( (int) $order_id );
			if ( ! $order || $order->is_paid() ) {
				continue;
			}

			if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ), true ) ) {
				continue;
			}

			if ( self::capture_from_order( $order, 'abandoned' ) ) {
				++$stats['captured'];
			}
		}

		$stats['skipped_no_email'] = max( 0, $stats['scanned'] - count( $order_ids ) );

		return $stats;
	}

	/**
	 * @param string[] $statuses Order statuses without wc- prefix.
	 * @return int[]
	 */
	private static function get_order_ids_with_email_sql( array $statuses ): array {
		global $wpdb;

		$statuses = array_filter( array_map( 'sanitize_key', $statuses ) );
		if ( empty( $statuses ) ) {
			return array();
		}

		$hpos_statuses   = array();
		$legacy_statuses = array();
		foreach ( $statuses as $status ) {
			$hpos_statuses[]   = $status;
			$legacy_statuses[] = 'wc-' . $status;
		}

		$ids = array();

		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {

			$orders_table        = $wpdb->prefix . 'wc_orders';
			$addresses_table     = $wpdb->prefix . 'wc_order_addresses';
			$status_placeholders = implode( ', ', array_fill( 0, count( $hpos_statuses ), '%s' ) );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = "SELECT DISTINCT o.id
				FROM {$orders_table} o
				LEFT JOIN {$addresses_table} a ON o.id = a.order_id AND a.address_type = 'billing'
				WHERE o.type = 'shop_order'
				AND o.status IN ({$status_placeholders})
				AND (
					(o.billing_email IS NOT NULL AND o.billing_email != '')
					OR (a.email IS NOT NULL AND a.email != '')
				)";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$ids = $wpdb->get_col( $wpdb->prepare( $sql, ...$hpos_statuses ) );
		} else {
			$status_placeholders = implode( ', ', array_fill( 0, count( $legacy_statuses ), '%s' ) );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = "SELECT DISTINCT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_billing_email'
				WHERE p.post_type = 'shop_order'
				AND p.post_status IN ({$status_placeholders})
				AND pm.meta_value IS NOT NULL AND pm.meta_value != ''";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$ids = $wpdb->get_col( $wpdb->prepare( $sql, ...$legacy_statuses ) );
		}

		return array_map( 'intval', array_filter( $ids ?: array() ) );
	}

	private static function capture_from_order( WC_Order $order, string $status ): bool {
		$email = self::resolve_order_email( $order );
		if ( ! $email ) {
			return false;
		}

		$lead_id = DOEC_Database::instance()->upsert_lead(
			array(
				'email'       => $email,
				'first_name'  => $order->get_billing_first_name(),
				'last_name'   => $order->get_billing_last_name(),
				'phone'       => $order->get_billing_phone(),
				'order_id'    => $order->get_id(),
				'order_total' => (float) $order->get_total(),
				'currency'    => $order->get_currency(),
				'item_count'  => $order->get_item_count(),
				'status'      => $status,
			)
		);

		return $lead_id > 0;
	}

	private static function resolve_order_email( WC_Order $order ): string {
		$candidates = array(
			$order->get_billing_email(),
			(string) $order->get_meta( '_billing_email', true ),
			(string) $order->get_meta( 'billing_email', true ),
		);

		$customer_id = (int) $order->get_customer_id();
		if ( $customer_id > 0 ) {
			$user = get_user_by( 'id', $customer_id );
			if ( $user && is_email( $user->user_email ) ) {
				$candidates[] = $user->user_email;
			}
		}

		foreach ( $candidates as $email ) {
			$email = sanitize_email( $email );
			if ( $email && is_email( $email ) ) {
				return $email;
			}
		}

		return '';
	}
}
