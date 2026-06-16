/**
 * Tour Service Date Selector — Admin JavaScript
 *
 * Handles:
 *  - Show / hide Weekly Schedule based on Service Type dropdown
 *  - Same behaviour for variation-level fields
 *
 * @package TSDS
 */

/* global jQuery, tsdsAdmin */

( function ( $ ) {
	'use strict';

	// ───────────────────────────────────────────────
	// Product-level Tab panel
	// ───────────────────────────────────────────────

	function initProductPanel() {
		const $serviceType = $( '#tsds_service_type' );
		const $schedule    = $( '#tsds-weekly-schedule' );

		if ( ! $serviceType.length ) {
			return;
		}

		function toggleSchedule() {
			if ( $serviceType.val() === 'open_dated' ) {
				$schedule.slideUp( 200 );
			} else {
				$schedule.slideDown( 200 );
			}
		}

		// Initial state.
		toggleSchedule();

		$serviceType.on( 'change.tsds', toggleSchedule );
	}

	// ───────────────────────────────────────────────
	// Variation-level fields
	// ───────────────────────────────────────────────

	function initVariationPanels() {
		// Delegate to handle variations added dynamically.
		$( document ).on(
			'change.tsds',
			'.tsds-variation-service-type',
			function () {
				const $select   = $( this );
				const $row      = $select.closest( '.tsds-variation-settings' );
				const $schedule = $row.find( '.tsds-variation-schedule' );
				const val       = $select.val();

				if ( val === '' || val === 'open_dated' ) {
					$schedule.slideUp( 200 );
				} else {
					$schedule.slideDown( 200 );
				}
			}
		);
	}

	// ───────────────────────────────────────────────
	// Bootstrap
	// ───────────────────────────────────────────────

	$( document ).ready( function () {
		initProductPanel();
		initVariationPanels();

		// WooCommerce fires this when variation panels are loaded/reloaded.
		$( document ).on( 'woocommerce_variations_loaded woocommerce_variations_added', function () {
			initVariationPanels();
		} );
	} );

} )( jQuery );
