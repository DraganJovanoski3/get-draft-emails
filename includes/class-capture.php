<?php
/**
 * Capture emails from WooCommerce draft orders.
 */

defined( 'ABSPATH' ) || exit;

class DOEC_Capture {

	/** @var DOEC_Capture|null */
	private static $instance = null;

	/** @var string[] */
	private const DRAFT_STATUSES = array( 'checkout-draft', 'draft' );

	public static function instance(): DOEC_Capture {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'maybe_capture_from_order' ), 20, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( $this, 'maybe_capture_from_order_object' ), 20, 1 );
		add_action( 'woocommerce_update_order', array( $this, 'on_order_updated' ), 20, 2 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_status_changed' ), 10, 4 );

		add_action( 'doec_sync_draft_orders', array( $this, 'sync_existing_drafts' ) );

		if ( ! wp_next_scheduled( 'doec_sync_draft_orders' ) ) {
			wp_schedule_event( time(), 'hourly', 'doec_sync_draft_orders' );
		}
	}

	/**
	 * Classic checkout hook.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $data     Posted checkout data.
	 */
	public function maybe_capture_from_order( int $order_id, array $data ): void {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$this->capture_order_if_draft( $order );
		}
	}

	/**
	 * Block checkout hook.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function maybe_capture_from_order_object( WC_Order $order ): void {
		$this->capture_order_if_draft( $order );
	}

	/**
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order object.
	 */
	public function on_order_updated( int $order_id, WC_Order $order ): void {
		if ( $this->is_draft_status( $order->get_status() ) ) {
			$this->capture_order_if_draft( $order );
			return;
		}

		if ( $order->is_paid() || in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ), true ) ) {
			DOEC_Database::instance()->mark_converted_by_order( $order_id );
		}
	}

	/**
	 * @param int    $order_id   Order ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 * @param WC_Order $order    Order object.
	 */
	public function on_status_changed( int $order_id, string $old_status, string $new_status, WC_Order $order ): void {
		if ( $this->is_draft_status( $new_status ) ) {
			$this->capture_order_if_draft( $order );
			return;
		}

		if ( $this->is_draft_status( $old_status ) && ! $this->is_draft_status( $new_status ) ) {
			// Customer submitted checkout or completed payment — exclude from abandoned list.
			if ( in_array( $new_status, array( 'pending', 'processing', 'completed', 'on-hold' ), true ) || $order->is_paid() ) {
				DOEC_Database::instance()->mark_converted_by_order( $order_id );
				return;
			}

			// Failed/cancelled after draft — keep as abandoned for recovery emails.
			$this->capture_from_order( $order, 'abandoned' );
		}
	}

	public function sync_existing_drafts(): void {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return;
		}

		$orders = wc_get_orders(
			array(
				'status'   => self::DRAFT_STATUSES,
				'limit'    => 100,
				'orderby'  => 'date',
				'order'    => 'DESC',
				'return'   => 'objects',
			)
		);

		foreach ( $orders as $order ) {
			$this->capture_order_if_draft( $order );
		}
	}

	private function is_draft_status( string $status ): bool {
		return in_array( $status, self::DRAFT_STATUSES, true );
	}

	private function capture_order_if_draft( WC_Order $order ): void {
		if ( ! $this->is_draft_status( $order->get_status() ) ) {
			return;
		}

		$this->capture_from_order( $order, 'abandoned' );
	}

	private function capture_from_order( WC_Order $order, string $status ): void {
		$email = $order->get_billing_email();
		if ( ! $email || ! is_email( $email ) ) {
			return;
		}

		DOEC_Database::instance()->upsert_lead(
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
	}
}
