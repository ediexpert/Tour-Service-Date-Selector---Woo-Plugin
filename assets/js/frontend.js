/**
 * Tour Service Date Selector — Frontend JavaScript
 *
 * Namespace: window.TSDS
 *
 * Responsibilities:
 *  - Initialise Flatpickr inline calendar on product pages
 *  - Build time slot dropdowns from schedule data
 *  - Validate fields before add-to-cart
 *  - Handle variable product variation events (found_variation, reset_data)
 *  - Be idempotent — safe to call TSDS.init() multiple times
 *
 * @package TSDS
 */

/* global tsdsData, flatpickr, jQuery */

( function ( $, tsdsData ) {
	'use strict';

	window.TSDS = window.TSDS || {};

	/**
	 * Internal state.
	 */
	const state = {
		calendar: null,      // Flatpickr instance
		currentServiceType: tsdsData.serviceType || 'open_dated',
		currentSchedule:    tsdsData.schedule     || [],
		currentDisabled:    tsdsData.disabledWeekdays || [],
		initialized: false,
	};

	// ───────────────────────────────────────────────
	// Utility helpers
	// ───────────────────────────────────────────────

	/**
	 * Get the schedule entry for a given Date object.
	 *
	 * @param {Date}   date     JS Date.
	 * @param {Array}  schedule TSDS schedule array.
	 * @return {Object|null}
	 */
	function getDaySchedule( date, schedule ) {
		const dow = date.getDay(); // 0 = Sunday
		return schedule.find( function ( s ) { return s.index === dow; } ) || null;
	}

	/**
	 * Build H:i time slots between start and end with 15-min steps.
	 *
	 * @param {string} start "HH:MM"
	 * @param {string} end   "HH:MM"
	 * @return {string[]}
	 */
	function buildTimeSlots( start, end ) {
		const slots = [];
		const [ sh, sm ] = start.split( ':' ).map( Number );
		const [ eh, em ] = end.split( ':' ).map( Number );
		let current = sh * 60 + sm;
		const endMin = eh * 60 + em;

		while ( current <= endMin ) {
			const h = String( Math.floor( current / 60 ) ).padStart( 2, '0' );
			const m = String( current % 60 ).padStart( 2, '0' );
			slots.push( h + ':' + m );
			current += 15;
		}

		return slots;
	}

	/**
	 * Populate the time dropdown for a given date.
	 *
	 * @param {Date|null} date
	 */
	function populateTimeSelect( date ) {
		const $select = $( '#tsds-booking-time' );
		if ( ! $select.length ) {
			return;
		}

		$select.empty().append(
			$( '<option>' ).val( '' ).text( tsdsData.i18n.selectTime )
		);

		if ( ! date ) {
			return;
		}

		const daySchedule = getDaySchedule( date, state.currentSchedule );
		if ( ! daySchedule || ! daySchedule.enabled ) {
			return;
		}

		const slots = buildTimeSlots( daySchedule.start, daySchedule.end );
		slots.forEach( function ( slot ) {
			$select.append( $( '<option>' ).val( slot ).text( slot ) );
		} );
	}

	// ───────────────────────────────────────────────
	// Calendar init / destroy
	// ───────────────────────────────────────────────

	/**
	 * Destroy existing Flatpickr instance if present.
	 */
	function destroyCalendar() {
		if ( state.calendar ) {
			state.calendar.destroy();
			state.calendar = null;
		}
	}

	/**
	 * Initialise (or re-initialise) Flatpickr inline calendar.
	 *
	 * @param {int[]} disabledWeekdays Array of disabled weekday indices (0–6).
	 */
	function initCalendar( disabledWeekdays ) {
		destroyCalendar();

		const container = document.getElementById( 'tsds-calendar-container' );
		if ( ! container ) {
			return;
		}

		// Reset hidden date input.
		const dateInput = document.getElementById( 'tsds-booking-date' );
		if ( dateInput ) {
			dateInput.value = '';
		}

		state.calendar = flatpickr( container, {
			inline: true,
			minDate: 'today',
			dateFormat: 'Y-m-d',
			// Disable weekdays not in the schedule.
			disable: [
				function ( date ) {
					return disabledWeekdays.indexOf( date.getDay() ) !== -1;
				},
			],
			onChange: function ( selectedDates, dateStr ) {
				if ( dateInput ) {
					dateInput.value = dateStr;
				}
				clearError( 'date' );

				if ( state.currentServiceType === 'date_time' ) {
					populateTimeSelect( selectedDates[0] || null );
				}

				// Dispatch custom event for extensibility.
				const event = new CustomEvent( 'tsdsDateChanged', {
					bubbles: true,
					detail: { date: dateStr, dateObject: selectedDates[0] },
				} );
				document.dispatchEvent( event );
			},
		} );
	}

	// ───────────────────────────────────────────────
	// Show / hide UI sections
	// ───────────────────────────────────────────────

	function showDateFields() {
		$( '.tsds-date-field-group' ).show();
	}

	function hideDateFields() {
		$( '.tsds-date-field-group' ).hide();
		const dateInput = document.getElementById( 'tsds-booking-date' );
		if ( dateInput ) {
			dateInput.value = '';
		}
		destroyCalendar();
	}

	function showTimeFields() {
		$( '#tsds-time-field-group' ).show();
	}

	function hideTimeFields() {
		$( '#tsds-time-field-group' ).hide();
		const $select = $( '#tsds-booking-time' );
		if ( $select.length ) {
			$select.val( '' );
		}
	}

	/**
	 * Apply a service type — show/hide fields and (re)init calendar.
	 *
	 * @param {string} serviceType  'open_dated' | 'date_only' | 'date_time'
	 * @param {Array}  schedule     TSDS schedule array.
	 * @param {int[]}  disabledWeekdays
	 */
	function applyServiceType( serviceType, schedule, disabledWeekdays ) {
		state.currentServiceType  = serviceType;
		state.currentSchedule     = schedule;
		state.currentDisabled     = disabledWeekdays;

		const $wrapper = $( '.tsds-booking-wrapper' );

		if ( serviceType === 'open_dated' ) {
			$wrapper.hide();
			hideDateFields();
			hideTimeFields();
			destroyCalendar();
			return;
		}

		$wrapper.show();

		if ( serviceType === 'date_only' ) {
			showDateFields();
			hideTimeFields();
			initCalendar( disabledWeekdays );
		} else if ( serviceType === 'date_time' ) {
			showDateFields();
			showTimeFields();
			initCalendar( disabledWeekdays );
		}
	}

	// ───────────────────────────────────────────────
	// Error handling
	// ───────────────────────────────────────────────

	function showError( type, message ) {
		const id = 'tsds-' + type + '-error';
		const $el = $( '#' + id );
		if ( $el.length ) {
			$el.text( message ).show();
		}
	}

	function clearError( type ) {
		const id = 'tsds-' + type + '-error';
		$( '#' + id ).text( '' ).hide();
	}

	function clearAllErrors() {
		clearError( 'date' );
		clearError( 'time' );
	}

	// ───────────────────────────────────────────────
	// Validation
	// ───────────────────────────────────────────────

	/**
	 * Validate booking fields before form submit.
	 *
	 * @return {boolean} True if valid.
	 */
	function validateFields() {
		const serviceType = state.currentServiceType;

		if ( serviceType === 'open_dated' ) {
			return true;
		}

		clearAllErrors();
		let valid = true;

		const date = ( document.getElementById( 'tsds-booking-date' ) || {} ).value || '';
		if ( ! date ) {
			showError( 'date', tsdsData.i18n.selectDate );
			valid = false;
		} else {
			// Validate weekday.
			const dateObj  = new Date( date + 'T00:00:00' );
			const dow      = dateObj.getDay();
			const disabled = state.currentDisabled || [];
			if ( disabled.indexOf( dow ) !== -1 ) {
				showError( 'date', tsdsData.i18n.invalidDate );
				valid = false;
			}
		}

		if ( serviceType === 'date_time' ) {
			const time = ( document.getElementById( 'tsds-booking-time' ) || {} ).value || '';
			if ( ! time ) {
				showError( 'time', tsdsData.i18n.selectTime );
				valid = false;
			} else if ( date ) {
				// Validate time range.
				const dateObj     = new Date( date + 'T00:00:00' );
				const daySchedule = getDaySchedule( dateObj, state.currentSchedule );
				if ( daySchedule && daySchedule.enabled ) {
					if ( time < daySchedule.start || time > daySchedule.end ) {
						showError( 'time', tsdsData.i18n.invalidTime );
						valid = false;
					}
				}
			}
		}

		return valid;
	}

	// ───────────────────────────────────────────────
	// Variable product variation events
	// ───────────────────────────────────────────────

	function bindVariationEvents() {
		const $form = $( 'form.variations_form' );
		if ( ! $form.length ) {
			return;
		}

		// Variation found / shown.
		$form.on( 'found_variation.tsds show_variation.tsds', function ( event, variation ) {
			const variationId = variation.variation_id;
			const varData = ( tsdsData.variations || {} )[ variationId ];

			if ( varData ) {
				applyServiceType( varData.serviceType, varData.schedule, varData.disabledWeekdays );
			} else {
				// Fall back to parent settings.
				applyServiceType(
					tsdsData.serviceType,
					tsdsData.schedule,
					tsdsData.disabledWeekdays
				);
			}
		} );

		// Variation reset.
		$form.on( 'reset_data.tsds', function () {
			applyServiceType(
				tsdsData.serviceType,
				tsdsData.schedule,
				tsdsData.disabledWeekdays
			);
		} );
	}

	// ───────────────────────────────────────────────
	// Add-to-cart validation intercept
	// ───────────────────────────────────────────────

	function bindAddToCartValidation() {
		// Use event delegation on the form for compatibility with AJAX themes.
		$( document ).on( 'click.tsds', '.single_add_to_cart_button', function ( e ) {
			const $wrapper = $( '.tsds-booking-wrapper' );

			// Only intercept if booking wrapper is present and visible.
			if ( ! $wrapper.length || ! $wrapper.is( ':visible' ) ) {
				return;
			}

			if ( ! validateFields() ) {
				e.preventDefault();
				e.stopImmediatePropagation();

				// Scroll to the first visible error.
				const $firstError = $( '.tsds-error:visible' ).first();
				if ( $firstError.length ) {
					$( 'html, body' ).animate(
						{ scrollTop: $firstError.offset().top - 80 },
						300
					);
				}
			}
		} );
	}

	// ───────────────────────────────────────────────
	// Public API
	// ───────────────────────────────────────────────

	/**
	 * Initialise — idempotent.
	 */
	TSDS.init = function () {
		if ( state.initialized ) {
			return;
		}
		state.initialized = true;

		// Apply initial service type.
		applyServiceType(
			tsdsData.serviceType,
			tsdsData.schedule,
			tsdsData.disabledWeekdays
		);

		// Variable product events.
		if ( tsdsData.productType === 'variable' ) {
			bindVariationEvents();
		}

		// Validate before add-to-cart.
		bindAddToCartValidation();
	};

	/**
	 * Re-init (for AJAX / Quick View contexts).
	 */
	TSDS.reinit = function () {
		state.initialized = false;
		TSDS.init();
	};

	// ───────────────────────────────────────────────
	// Bootstrap
	// ───────────────────────────────────────────────

	$( document ).ready( function () {
		TSDS.init();
	} );

	// Re-init after WooCommerce AJAX product updates.
	$( document ).on( 'wc_fragments_refreshed wc_cart_button_updated', function () {
		TSDS.init();
	} );

} )( jQuery, window.tsdsData || {} );
