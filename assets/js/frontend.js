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
		calendars: [],       // Flatpickr instances
		currentServiceType: tsdsData.serviceType || 'open_dated',
		currentDateFormat: tsdsData.displayDateFormat || 'F j, Y',
		currentSchedule:    tsdsData.schedule     || [],
		currentDisabled:    tsdsData.disabledWeekdays || [],
		initialized: false,
	};

	/**
	 * Resolve date input placeholder from service type.
	 *
	 * @param {string} serviceType
	 * @return {string}
	 */
	function getDatePlaceholder( serviceType ) {
		if ( serviceType === 'date_time' ) {
			return tsdsData.i18n.selectDateTime || 'Please select a date and time.';
		}

		return tsdsData.i18n.selectDate || 'Please select a date.';
	}

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
	 * Populate the time dropdown for a given date across all forms.
	 *
	 * @param {Date|null} date
	 */
	function populateTimeSelect( date ) {
		const $selects = $( '.tsds-time-select' );
		if ( ! $selects.length ) {
			return;
		}

		$selects.empty().append(
			$( '<option>' ).val( '' ).text( tsdsData.i18n.selectTime || '— Select time —' )
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
			$selects.append( $( '<option>' ).val( slot ).text( slot ) );
		} );
	}

	/**
	 * Update selected date preview text across all layouts.
	 *
	 * @param {Date|null} dateObj
	 * @param {string}    dateStr
	 */
	function updateSelectedDatePreview( dateObj, dateStr ) {
		const $previews = $( '.tsds-selected-date' );
		if ( ! $previews.length ) {
			return;
		}

		if ( ! dateObj ) {
			$previews.text( '' ).hide();
			return;
		}

		let formatted = dateStr || '';

		let formatFn = null;
		if ( state.calendars && state.calendars.length ) {
			const activeFp = state.calendars.find( function ( fp ) { return fp && typeof fp.formatDate === 'function'; } );
			if ( activeFp ) {
				formatFn = activeFp.formatDate.bind( activeFp );
			}
		}
		if ( ! formatFn && typeof flatpickr !== 'undefined' && typeof flatpickr.formatDate === 'function' ) {
			formatFn = flatpickr.formatDate;
		}

		if ( formatFn ) {
			formatted = formatFn( dateObj, state.currentDateFormat );
		}

		$previews.text( ( tsdsData.i18n.selectedDate || 'Selected Date:' ) + ' ' + formatted ).show();
	}

	// ───────────────────────────────────────────────
	// Calendar init / destroy
	// ───────────────────────────────────────────────

	/**
	 * Destroy existing Flatpickr instances if present.
	 */
	function destroyCalendars() {
		if ( state.calendars && state.calendars.length ) {
			state.calendars.forEach( function ( fp ) {
				if ( fp && typeof fp.destroy === 'function' ) {
					fp.destroy();
				}
			} );
		}
		state.calendars = [];
	}

	/**
	 * Initialise Flatpickr on a single date input.
	 *
	 * @param {HTMLElement} calendarInput
	 * @param {int[]}       disabledWeekdays
	 */
	function initSingleCalendar( calendarInput, disabledWeekdays ) {
		if ( ! calendarInput || calendarInput._flatpickr ) {
			return;
		}

		calendarInput.setAttribute( 'placeholder', getDatePlaceholder( state.currentServiceType ) );

		const fpInstance = flatpickr( calendarInput, {
			minDate: 'today',
			// Display the human-readable format directly in the visible input.
			// No altInput — one input, one source of truth, no hidden/cloned confusion.
			dateFormat: state.currentDateFormat,
			allowInput: false,
			clickOpens: true,
			// Append to body so the calendar floats above any theme drawer/modal
			// (CommerceKit StickyAddToCart uses overflow:hidden on its container).
			appendTo: document.body,
			disable: [
				function ( date ) {
					return disabledWeekdays.indexOf( date.getDay() ) !== -1;
				},
			],
			onChange: function ( selectedDates, dateStr ) {
				// Derive Y-m-d for cart/server — dateStr is in display format.
				const ymd = selectedDates[0]
					? flatpickr.formatDate( selectedDates[0], 'Y-m-d' )
					: '';

				// Update all hidden booking inputs with the machine-readable value.
				$( 'input[name="tsds_booking_date"]' ).val( ymd );

				// Synchronize selected date to all other Flatpickr instances on the page.
				$( '.tsds-date-input' ).each( function () {
					if ( this._flatpickr && this._flatpickr !== fpInstance && typeof this._flatpickr.setDate === 'function' ) {
						this._flatpickr.setDate( selectedDates[0] || null, false );
					}
				} );

				clearError( 'date' );

				if ( state.currentServiceType === 'date_time' ) {
					populateTimeSelect( selectedDates[0] || null );
				}

				// Dispatch custom event for extensibility.
				const event = new CustomEvent( 'tsdsDateChanged', {
					bubbles: true,
					detail: { date: ymd, dateObject: selectedDates[0] },
				} );
				document.dispatchEvent( event );
			},
			onReady: function ( _selectedDates, _dateStr, instance ) {
				// Tag the calendar so our CSS applies even when it's on <body>.
				if ( instance.calendarContainer ) {
					instance.calendarContainer.classList.add( 'tsds-calendar' );
				}
			},
		} );

		if ( fpInstance ) {
			state.calendars.push( fpInstance );

			// If another input has already set a date, bind it to this instance too
			const currentVal = $( 'input[name="tsds_booking_date"]' ).val();
			if ( currentVal ) {
				const parts = currentVal.split( '-' );
				if ( parts.length === 3 ) {
					const dateObj = new Date( parseInt( parts[0], 10 ), parseInt( parts[1], 10 ) - 1, parseInt( parts[2], 10 ) );
					fpInstance.setDate( dateObj, false );
				}
			}
		}
	}

	/**
	 * Initialise (or re-initialise) Flatpickr popups.
	 *
	 * @param {int[]} disabledWeekdays Array of disabled weekday indices (0–6).
	 */
	function initCalendar( disabledWeekdays ) {
		destroyCalendars();

		$( '.tsds-date-input' ).each( function () {
			initSingleCalendar( this, disabledWeekdays );
		} );

		$( 'input[name="tsds_booking_date"]' ).val( '' );
		updateSelectedDatePreview( null, '' );
	}

	// ───────────────────────────────────────────────
	// Show / hide UI sections
	// ───────────────────────────────────────────────

	function showDateFields() {
		$( '.tsds-date-field-group' ).show();
	}

	function hideDateFields() {
		$( '.tsds-date-field-group' ).hide();
		$( 'input[name="tsds_booking_date"]' ).val( '' );
		updateSelectedDatePreview( null, '' );
		destroyCalendars();
	}

	function showTimeFields() {
		$( '.tsds-time-field-group' ).show();
	}

	function hideTimeFields() {
		$( '.tsds-time-field-group' ).hide();
		const $selects = $( '.tsds-time-select' );
		if ( $selects.length ) {
			$selects.val( '' );
		}
	}

	/**
	 * Apply a service type — show/hide fields and (re)init calendar.
	 *
	 * @param {string} serviceType      'open_dated' | 'date_only' | 'date_time'
	 * @param {Array}  schedule         TSDS schedule array.
	 * @param {int[]}  disabledWeekdays Disabled weekday indices.
	 */
	function applyServiceType( serviceType, schedule, disabledWeekdays ) {
		state.currentServiceType  = serviceType;
		state.currentSchedule     = schedule;
		state.currentDisabled     = disabledWeekdays;
		state.currentDateFormat   = tsdsData.displayDateFormat || 'F j, Y';

		const $wrapper = $( '.tsds-booking-wrapper' );

		if ( serviceType === 'open_dated' ) {
			$wrapper.hide();
			hideDateFields();
			hideTimeFields();
			destroyCalendars();
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
		const className = 'tsds-' + type + '-error';
		const $errors = $( '.' + className );
		if ( $errors.length ) {
			$errors.text( message ).show();
		}
	}

	function clearError( type ) {
		const className = 'tsds-' + type + '-error';
		$( '.' + className ).text( '' ).hide();
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

		const date = $( 'input[name="tsds_booking_date"]' ).filter( function () {
			return !! this.value;
		} ).first().val() || '';

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
			const time = $( '.tsds-time-select' ).filter( function () {
				return !! this.value;
			} ).first().val() || '';

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
		// Delegate to the whole field-group so any click inside opens the calendar.
		// This covers:
		//  - The original .tsds-date-input (pre-Flatpickr or fresh clones).
		//  - The visible altInput created by Flatpickr (class flatpickr-input),
		//    whose event listeners are NOT copied when a theme clones the DOM for
		//    a sticky/mobile drawer.
		//  - The "change date" scenario where _flatpickr already exists but the
		//    old code returned without opening it.
		$( document ).on( 'click.tsds', '.tsds-date-field-group', function ( e ) {
			// Ignore clicks that land inside the Flatpickr calendar popup itself.
			if ( $( e.target ).closest( '.flatpickr-calendar' ).length ) {
				return;
			}

			const originalInput = $( this ).find( '.tsds-date-input' )[0];
			if ( ! originalInput ) {
				return;
			}

			// Already initialised — open it regardless (handles cloned drawers
			// and the user wanting to change a previously chosen date).
			if ( originalInput._flatpickr ) {
				if ( typeof originalInput._flatpickr.open === 'function' ) {
					originalInput._flatpickr.open();
				}
				return;
			}

			// First interaction on this input — initialise then open.
			initSingleCalendar( originalInput, state.currentDisabled || [] );
			if ( originalInput._flatpickr && typeof originalInput._flatpickr.open === 'function' ) {
				originalInput._flatpickr.open();
			}
		} );

		// Propagate choice across duplicate time dropdowns
		$( document ).on( 'change.tsds', '.tsds-time-select', function () {
			$( '.tsds-time-select' ).val( $( this ).val() || '' );
			clearError( 'time' );
		} );

		function isBookingUIActive() {
			const $wrapper = $( '.tsds-booking-wrapper' );
			return $wrapper.length && $wrapper.is( ':visible' );
		}

		function syncBookingFieldsToForm( $form ) {
			if ( ! $form || ! $form.length ) {
				return;
			}

			const dateVal = $( 'input[name="tsds_booking_date"]' ).filter( function () { return !! this.value; } ).first().val() || '';
			const timeVal = $( '.tsds-time-select' ).filter( function () { return !! this.value; } ).first().val() || '';
			const nonceVal = ( $( 'input[name="tsds_nonce"]' ).first().val() || '' );

			if ( nonceVal ) {
				let $nonce = $form.find( 'input[name="tsds_nonce"]' );
				if ( ! $nonce.length ) {
					$nonce = $( '<input type="hidden" name="tsds_nonce" />' ).appendTo( $form );
				}
				$nonce.val( nonceVal );
			}

			let $date = $form.find( 'input[name="tsds_booking_date"]' );
			if ( ! $date.length ) {
				$date = $( '<input type="hidden" name="tsds_booking_date" />' ).appendTo( $form );
			}
			$date.val( dateVal );

			let $time = $form.find( 'input[name="tsds_booking_time"]' );
			if ( ! $time.length ) {
				$time = $( '<input type="hidden" name="tsds_booking_time" />' ).appendTo( $form );
			}
			$time.val( timeVal );
		}

		function scrollToFirstError() {
			const $firstError = $( '.tsds-error:visible' ).first();
			if ( $firstError.length ) {
				$( 'html, body' ).animate(
					{ scrollTop: $firstError.offset().top - 80 },
					300
				);
			}
		}

		// Validate on button click for classic product forms/themes.
		$( document ).on( 'click.tsds', '.single_add_to_cart_button', function ( e ) {
			if ( ! isBookingUIActive() ) {
				return;
			}

			const $form = $( this ).closest( 'form.cart' );
			syncBookingFieldsToForm( $form );

			if ( ! validateFields() ) {
				e.preventDefault();
				e.stopImmediatePropagation();
				scrollToFirstError();
			}
		} );

		// Validate on form submit for sticky bars / popups that bypass button click handlers.
		$( document ).on( 'submit.tsds', 'form.cart', function ( e ) {
			if ( ! isBookingUIActive() ) {
				return;
			}

			const $form = $( this );
			syncBookingFieldsToForm( $form );

			if ( ! validateFields() ) {
				e.preventDefault();
				e.stopImmediatePropagation();
				scrollToFirstError();
			}
		} );

		// Ensure booking payload exists for AJAX add-to-cart integrations.
		$( document.body ).on( 'adding_to_cart.tsds', function ( _event, $button, data ) {
			if ( ! isBookingUIActive() || ! data ) {
				return;
			}

			if ( ! validateFields() ) {
				return;
			}

			data.tsds_booking_date = $( 'input[name="tsds_booking_date"]' ).filter( function () { return !! this.value; } ).first().val() || '';
			data.tsds_booking_time = $( '.tsds-time-select' ).filter( function () { return !! this.value; } ).first().val() || '';
			data.tsds_nonce = ( $( 'input[name="tsds_nonce"]' ).first().val() || '' );
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
