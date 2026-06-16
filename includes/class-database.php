<?php
/**
 * Database layer for captured draft-order leads.
 */

defined( 'ABSPATH' ) || exit;

class DOEC_Database {

	/** @var DOEC_Database|null */
	private static $instance = null;

	public static function instance(): DOEC_Database {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'doec_leads';
	}

	public static function create_table(): void {
		global $wpdb;

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(255) NOT NULL,
			first_name varchar(100) DEFAULT '',
			last_name varchar(100) DEFAULT '',
			phone varchar(50) DEFAULT '',
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			order_total decimal(12,2) DEFAULT 0.00,
			currency varchar(10) DEFAULT '',
			item_count int(11) DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'abandoned',
			captured_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY email (email),
			KEY order_id (order_id),
			KEY status (status),
			KEY captured_at (captured_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public function upsert_lead( array $data ): int {
		global $wpdb;

		$table    = self::table_name();
		$order_id = (int) ( $data['order_id'] ?? 0 );
		$email    = sanitize_email( (string) ( $data['email'] ?? '' ) );

		if ( ! $email || ! is_email( $email ) ) {
			return 0;
		}

		$now = current_time( 'mysql' );

		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, status FROM {$table} WHERE order_id = %d LIMIT 1",
				$order_id
			)
		);

		$row = array(
			'email'       => $email,
			'first_name'  => sanitize_text_field( (string) ( $data['first_name'] ?? '' ) ),
			'last_name'   => sanitize_text_field( (string) ( $data['last_name'] ?? '' ) ),
			'phone'       => sanitize_text_field( (string) ( $data['phone'] ?? '' ) ),
			'order_id'    => $order_id,
			'order_total' => (float) ( $data['order_total'] ?? 0 ),
			'currency'    => sanitize_text_field( (string) ( $data['currency'] ?? '' ) ),
			'item_count'  => (int) ( $data['item_count'] ?? 0 ),
			'status'      => sanitize_key( (string) ( $data['status'] ?? 'abandoned' ) ),
			'updated_at'  => $now,
		);

		if ( $existing ) {
			if ( 'converted' === $existing->status ) {
				$row['status'] = 'converted';
			}

			$wpdb->update( $table, $row, array( 'id' => (int) $existing->id ) );
			return (int) $existing->id;
		}

		$row['captured_at'] = $now;
		$wpdb->insert( $table, $row );

		return (int) $wpdb->insert_id;
	}

	public function mark_converted_by_order( int $order_id ): void {
		global $wpdb;

		$wpdb->update(
			self::table_name(),
			array(
				'status'     => 'converted',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'order_id' => $order_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array{items: array<int,object>, total: int}
	 */
	public function get_leads( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'status'   => '',
			'search'   => '',
			'per_page' => 20,
			'page'     => 1,
			'orderby'  => 'captured_at',
			'order'    => 'DESC',
		);

		$args   = wp_parse_args( $args, $defaults );
		$table  = self::table_name();
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( $args['status'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]  = '(email LIKE %s OR first_name LIKE %s OR last_name LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) ) : $wpdb->get_var( $count_sql ) );

		$allowed_orderby = array( 'captured_at', 'email', 'order_total', 'status' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'captured_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$per_page = max( 1, (int) $args['per_page'] );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		$query_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$query_params = array_merge( $params, array( $per_page, $offset ) );

		$items = $wpdb->get_results( $wpdb->prepare( $query_sql, ...$query_params ) );

		return array(
			'items' => $items ?: array(),
			'total' => $total,
		);
	}

	/**
	 * @return array<string,int>
	 */
	public function get_counts(): array {
		global $wpdb;

		$table = self::table_name();
		$rows  = $wpdb->get_results( "SELECT status, COUNT(*) AS cnt FROM {$table} GROUP BY status", ARRAY_A );

		$counts = array(
			'abandoned' => 0,
			'converted' => 0,
			'total'     => 0,
		);

		foreach ( $rows as $row ) {
			$status = $row['status'] ?? '';
			$cnt    = (int) ( $row['cnt'] ?? 0 );
			if ( isset( $counts[ $status ] ) ) {
				$counts[ $status ] = $cnt;
			}
			$counts['total'] += $cnt;
		}

		return $counts;
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array<int,object>
	 */
	public function get_leads_for_export( array $args = array() ): array {
		$args['per_page'] = 10000;
		$args['page']     = 1;
		return $this->get_leads( $args )['items'];
	}

	public function delete_lead( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );
	}
}
