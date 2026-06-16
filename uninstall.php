<?php
/**
 * Uninstall cleanup.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

wp_clear_scheduled_hook( 'doec_sync_draft_orders' );

delete_option( 'doec_auto_capture' );
delete_option( 'doec_paused' );

$table = $wpdb->prefix . 'doec_leads';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
