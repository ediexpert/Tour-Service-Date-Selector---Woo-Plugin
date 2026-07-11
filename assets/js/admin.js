/**
 * Tour Service Date Selector — Admin JavaScript
 *
 * Handles:
 *  - Show / hide Weekly Schedule based on Service Type dropdown
 *  - Same behaviour for variation-level fields
 *
 * @package INTSDS
 */

/* global jQuery, intsdsAdmin */

( function ( $ ) {
	'use strict';

	// ───────────────────────────────────────────────
	// Product-level Tab panel
	// ───────────────────────────────────────────────

	function initProductPanel() {
		const $serviceType = $( '#intsds_service_type' );
		const $schedule    = $( '#intsds-weekly-schedule' );

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

		const $cutoff     = $( '#intsds_cutoff' );
		const $cutoffLead = $( '.intsds-cutoff-lead' );

		function toggleCutoffLead() {
			if ( $cutoff.val() === 'none' ) {
				$cutoffLead.slideUp( 200 );
			} else {
				$cutoffLead.slideDown( 200 );
			}
		}

		// Initial state.
		toggleSchedule();
		toggleCutoffLead();

		$serviceType.on( 'change.ints', toggleSchedule );
		$cutoff.on( 'change.ints', toggleCutoffLead );
	}

	// ───────────────────────────────────────────────
	// Variation-level fields
	// ───────────────────────────────────────────────

	function initVariationPanels() {
		// Delegate to handle variations added dynamically.
		$( document ).on(
			'change.ints',
			'.intsds-variation-service-type',
			function () {
				const $select   = $( this );
				const $row      = $select.closest( '.intsds-variation-settings' );
				const $schedule = $row.find( '.intsds-variation-schedule' );
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
