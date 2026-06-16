<?php
/**
 * Uninstall script — runs when the plugin is deleted.
 *
 * Removes all plugin-created post meta from the database.
 *
 * @package TSDS
 */

// If uninstall not called from WordPress, exit.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Only remove data if the user has opted in via a plugin option (best practice).
// For now we always clean up to be GDPR-friendly.
$meta_keys = array(
	'_tsds_service_type',
	'_tsds_weekly_schedule',
);

foreach ( $meta_keys as $meta_key ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->delete(
		$wpdb->postmeta,
		array( 'meta_key' => $meta_key ), // phpcs:ignore WordPress.DB.SlowDBQuery
		array( '%s' )
	);
}

// Also remove order item meta.
$order_meta_keys = array(
	'tsds_booking_date',
	'tsds_booking_time',
);

foreach ( $order_meta_keys as $meta_key ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->delete(
		$wpdb->prefix . 'woocommerce_order_itemmeta',
		array( 'meta_key' => $meta_key ), // phpcs:ignore WordPress.DB.SlowDBQuery
		array( '%s' )
	);
}
