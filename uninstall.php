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

$delete_data = get_option( 'tsds_delete_data_on_uninstall', 'no' );

if ( 'yes' !== $delete_data ) {
	$network_delete_data = get_site_option( 'tsds_delete_data_on_uninstall', 'no' );

	if ( 'yes' !== $network_delete_data ) {
		return;
	}
}

global $wpdb;

// Remove plugin data only when the uninstall cleanup setting is enabled.
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

// Remove plugin settings options.
$option_keys = array(
	'tsds_date_format',
	'tsds_date_label',
	'tsds_date_error',
	'tsds_delete_data_on_uninstall',
);

foreach ( $option_keys as $option_key ) {
	delete_option( $option_key );
	delete_site_option( $option_key );
}
