<?php
/**
 * Uninstall script — runs when the plugin is deleted.
 *
 * Removes all plugin-created post meta from the database.
 *
 * @package INTSDS
 */

// If uninstall not called from WordPress, exit.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Perform the uninstall cleanup.
 *
 * Wrapped in a function so its variables stay out of the global scope.
 */
function intsds_uninstall_cleanup(): void {
	$delete_data = get_option( 'intsds_delete_data_on_uninstall', 'no' );

	if ( 'yes' !== $delete_data ) {
		$network_delete_data = get_site_option( 'intsds_delete_data_on_uninstall', 'no' );

		if ( 'yes' !== $network_delete_data ) {
			return;
		}
	}

	global $wpdb;

	// Remove plugin-created post meta.
	$meta_keys = array(
		'_intsds_service_type',
		'_intsds_weekly_schedule',
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
		'intsds_booking_date',
		'intsds_booking_time',
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
		'intsds_date_format',
		'intsds_date_label',
		'intsds_date_error',
		'intsds_delete_data_on_uninstall',
	);

	foreach ( $option_keys as $option_key ) {
		delete_option( $option_key );
		delete_site_option( $option_key );
	}
}

intsds_uninstall_cleanup();
