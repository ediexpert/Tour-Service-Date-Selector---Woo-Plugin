/**
 * Tour Service Date Selector — Frontend JavaScript
 *
 * Namespace: window.INTSDS
 *
 * Responsibilities:
 *  - Initialise Flatpickr inline calendar on product pages
 *  - Build time slot dropdowns from schedule data
 *  - Validate fields before add-to-cart
 *  - Handle variable product variation events (found_variation, reset_data)
 *  - Be idempotent — safe to call INTSDS.init() multiple times
 *
 * @package INTSDS
 */

/* global intsdsData, flatpickr, jQuery */

( function ( $, intsdsData ) {
	'use strict';

	window.INTSDS = window.INTSDS || {};

	/**
	 * Internal state.
	 */
	const state = {
		calendars: [],       // Flatpickr instances
		currentServiceType: intsdsData.serviceType || 'open_dated',
		currentDateFormat: intsdsData.displayDateFormat || 'F j, Y',
		currentSchedule:    intsdsData.schedule     || [],
		currentDisabled:    intsdsData.disabledWeekdays || [],
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
			return intsdsData.i18n.selectDateTime || 'Please select a date and time.';
		}

		// Use the configurable label (e.g. 'Select Date') as the placeholder,
		// not the error message string.
		return intsdsData.i18n.dateLabel || 'Select Date';
	}

	// ───────────────────────────────────────────────
	// Utility helpers
	// ───────────────────────────────────────────────

	/**
	 * Get the schedule entry for a given Date object.
	 *
	 * @param {Date}   date     JS Date.
	 * @param {Array}  schedule INTSDS schedule array.
	 * @return {Object|null}
	 */
	function getDaySchedule( date, schedule ) {
		const dow = date.getDay(); // 0 = Sunday
		return schedule.find( function ( s ) { return s.index === dow; } ) || null;
	}

	// ───────────────────────────────────────────────
	// Timezone-aware availability (product-level cutoff)
	// ───────────────────────────────────────────────

	// Wall-clock time (ms) when this script initialised. Used to advance the
	// server-provided "now in product timezone" while the visitor stays on the page.
	const INTSDS_LOAD_MS = Date.now();

	function intsdsPad2( n ) { return ( n < 10 ? '0' : '' ) + n; }

	function intsdsDateToYmd( d ) {
		return d.getFullYear() + '-' + intsdsPad2( d.getMonth() + 1 ) + '-' + intsdsPad2( d.getDate() );
	}

	function intsdsAddDaysYmd( ymd, days ) {
		const p = ymd.split( '-' );
		const d = new Date( parseInt( p[0], 10 ), parseInt( p[1], 10 ) - 1, parseInt( p[2], 10 ) );
		d.setDate( d.getDate() + days );
		return intsdsDateToYmd( d );
	}

	function intsdsTimeToMinutes( t ) {
		const m = /^(\d{2}):(\d{2})$/.exec( t || '' );
		return m ? ( parseInt( m[1], 10 ) * 60 + parseInt( m[2], 10 ) ) : null;
	}

	/**
	 * Current date/time in the product timezone, advanced by the time the
	 * visitor has spent on the page (keeps the cutoff fresh without a reload).
	 *
	 * @return {Object|null} { date: 'YYYY-MM-DD', minutes: int, cutoff: string }
	 */
	function intsdsTzState() {
		const info = intsdsData.nowTz;
		if ( ! info || ! info.date ) {
			return null;
		}
		let minutes = ( parseInt( info.minutes, 10 ) || 0 ) + Math.floor( ( Date.now() - INTSDS_LOAD_MS ) / 60000 );
		let date    = info.date;
		while ( minutes >= 1440 ) {
			minutes -= 1440;
			date = intsdsAddDaysYmd( date, 1 );
		}
		return {
			date:        date,
			minutes:     minutes,
			cutoff:      intsdsData.cutoff || 'none',
			leadMinutes: parseInt( intsdsData.cutoffLeadMinutes, 10 ) || 0,
		};
	}

	// Absolute minutes since the Unix epoch for a wall-clock (date, minute-of-day) pair.
	function intsdsAbsMin( ymd, minutes ) {
		const p    = ymd.split( '-' );
		const days = Math.round( Date.UTC( parseInt( p[0], 10 ), parseInt( p[1], 10 ) - 1, parseInt( p[2], 10 ) ) / 86400000 );
		return days * 1440 + minutes;
	}

	/**
	 * Whether a calendar date should be disabled: closed weekday, a past date,
	 * or today after its cutoff — all evaluated in the product timezone.
	 *
	 * @param {Date}   date
	 * @param {Array}  schedule
	 * @param {int[]}  disabledWeekdays
	 * @param {Object} tzState
	 * @return {boolean}
	 */
	function intsdsIsDateBlocked( date, schedule, disabledWeekdays, tzState ) {
		if ( disabledWeekdays.indexOf( date.getDay() ) !== -1 ) {
			return true;
		}
		if ( tzState ) {
			const ymd = intsdsDateToYmd( date );

			// Past date in the product timezone.
			if ( ymd < tzState.date ) {
				return true;
			}

			// Advance-notice cutoff: closed once now >= ( date's ref time − lead ).
			if ( tzState.cutoff && tzState.cutoff !== 'none' ) {
				const day = getDaySchedule( date, schedule );
				if ( day ) {
					const refMin = intsdsTimeToMinutes( tzState.cutoff === 'start' ? day.start : day.end );
					if ( refMin !== null ) {
						const deadlineAbs = intsdsAbsMin( ymd, refMin ) - ( tzState.leadMinutes || 0 );
						const nowAbs      = intsdsAbsMin( tzState.date, tzState.minutes );
						if ( nowAbs >= deadlineAbs ) {
							return true;
						}
					}
				}
			}
		}
		return false;
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
		const $selects = $( '.intsds-time-select' );
		if ( ! $selects.length ) {
			return;
		}

		$selects.empty().append(
			$( '<option>' ).val( '' ).text( intsdsData.i18n.selectTime || '— Select time —' )
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
		const $previews = $( '.intsds-selected-date' );
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

		$previews.text( ( intsdsData.i18n.selectedDate || 'Selected Date:' ) + ' ' + formatted ).show();
	}

	// ───────────────────────────────────────────────
	// Calendar init / destroy
	// ───────────────────────────────────────────────

	/**
	 * Destroy existing Flatpickr instances if present.
	 *
	 * IMPORTANT: use `delete element._flatpickr` — never set it to null.
	 * Flatpickr internally does `if (element._flatpickr) element._flatpickr.destroy()`
	 * before creating a new instance. If the property is null rather than
	 * absent, that call throws "Cannot read properties of null (reading 'destroy')"
	 * and the new calendar is never created. Deleting the property makes the
	 * check evaluate to false so Flatpickr skips the re-destroy path cleanly.
	 */
	function destroyCalendars() {
		// Sweep all date inputs first — catches instances created outside of
		// state.calendars (e.g. by a theme sticky/drawer clone).
		$( '.intsds-date-input' ).each( function () {
			const fp = this._flatpickr;
			if ( fp ) {
				try {
					if ( typeof fp.destroy === 'function' ) {
						fp.destroy();
					}
				} catch ( e ) {
					// Already destroyed — swallow and continue.
				}
				delete this._flatpickr;
			}
		} );

		// Also clean up anything tracked in state that may not be on a visible input.
		if ( state.calendars && state.calendars.length ) {
			state.calendars.forEach( function ( fp ) {
				if ( fp ) {
					try {
						const el = fp.element || null;
						if ( typeof fp.destroy === 'function' ) {
							fp.destroy();
						}
						if ( el && '_flatpickr' in el ) {
							delete el._flatpickr;
						}
					} catch ( e ) {
						// Swallow — already destroyed.
					}
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
		if ( ! calendarInput ) {
			return;
		}

		// If a live instance already exists, open it and bail — no need to reinit.
		// A live instance always has a calendarContainer; a destroyed/stale one won't.
		if ( calendarInput._flatpickr && calendarInput._flatpickr.calendarContainer ) {
			return;
		}

		// Stale reference without a calendarContainer — destroy cleanly before reinit.
		if ( calendarInput._flatpickr ) {
			try {
				if ( typeof calendarInput._flatpickr.destroy === 'function' ) {
					calendarInput._flatpickr.destroy();
				}
			} catch ( e ) {
				// Swallow.
			}
			delete calendarInput._flatpickr;
		}

		// Clear any stale display value so Flatpickr starts with a blank input.
		// (Prevents re-parsing an old human-readable date from a previous selection.)
		calendarInput.value = '';
		calendarInput.setAttribute( 'placeholder', getDatePlaceholder( state.currentServiceType ) );

		const tzState = intsdsTzState();

		const fpInstance = flatpickr( calendarInput, {
			minDate: tzState ? tzState.date : 'today',
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
					return intsdsIsDateBlocked( date, state.currentSchedule, disabledWeekdays, tzState );
				},
			],
			onChange: function ( selectedDates, dateStr ) {
				// Derive Y-m-d for cart/server — dateStr is in display format.
				const ymd = selectedDates[0]
					? flatpickr.formatDate( selectedDates[0], 'Y-m-d' )
					: '';

				// Update all hidden booking inputs with the machine-readable value.
				$( 'input[name="intsds_booking_date"]' ).val( ymd );

				// Synchronize selected date to all other Flatpickr instances on the page.
				$( '.intsds-date-input' ).each( function () {
					if ( this._flatpickr && this._flatpickr !== fpInstance && typeof this._flatpickr.setDate === 'function' ) {
						this._flatpickr.setDate( selectedDates[0] || null, false );
					}
				} );

				clearError( 'date' );

				if ( state.currentServiceType === 'date_time' ) {
					populateTimeSelect( selectedDates[0] || null );
				}

				// Dispatch custom event for extensibility.
				const event = new CustomEvent( 'intsdsDateChanged', {
					bubbles: true,
					detail: { date: ymd, dateObject: selectedDates[0] },
				} );
				document.dispatchEvent( event );
			},
			onReady: function ( _selectedDates, _dateStr, instance ) {
				// Tag the calendar so our CSS applies even when it's on <body>.
				if ( instance.calendarContainer ) {
					instance.calendarContainer.classList.add( 'intsds-calendar' );
				}
			},
		} );

		if ( fpInstance ) {
			state.calendars.push( fpInstance );

			// If another input has already set a date, bind it to this instance too
			const currentVal = $( 'input[name="intsds_booking_date"]' ).val();
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

		// Reset booking data first so initSingleCalendar pre-populate only fires
		// for the drawer sync case (where the hidden input was set by a previous
		// user selection), not on variation change where we want a clean slate.
		$( 'input[name="intsds_booking_date"]' ).val( '' );
		updateSelectedDatePreview( null, '' );

		$( '.intsds-date-input' ).each( function () {
			initSingleCalendar( this, disabledWeekdays );
		} );
	}

	// ───────────────────────────────────────────────
	// Show / hide UI sections
	// ───────────────────────────────────────────────

	function showDateFields() {
		$( '.intsds-date-field-group' ).show();
	}

	function hideDateFields() {
		$( '.intsds-date-field-group' ).hide();
		$( 'input[name="intsds_booking_date"]' ).val( '' );
		updateSelectedDatePreview( null, '' );
		destroyCalendars();
	}

	function showTimeFields() {
		$( '.intsds-time-field-group' ).show();
	}

	function hideTimeFields() {
		$( '.intsds-time-field-group' ).hide();
		const $selects = $( '.intsds-time-select' );
		if ( $selects.length ) {
			$selects.val( '' );
		}
	}

	/**
	 * Apply a service type — show/hide fields and (re)init calendar.
	 *
	 * @param {string} serviceType      'open_dated' | 'date_only' | 'date_time'
	 * @param {Array}  schedule         INTSDS schedule array.
	 * @param {int[]}  disabledWeekdays Disabled weekday indices.
	 */
	function applyServiceType( serviceType, schedule, disabledWeekdays ) {
		state.currentServiceType  = serviceType;
		state.currentSchedule     = schedule;
		state.currentDisabled     = disabledWeekdays;
		state.currentDateFormat   = intsdsData.displayDateFormat || 'F j, Y';

		const $wrapper = $( '.intsds-booking-wrapper' );

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
		const className = 'intsds-' + type + '-error';
		const $errors = $( '.' + className );
		if ( $errors.length ) {
			$errors.text( message ).show();
		}
	}

	function clearError( type ) {
		const className = 'intsds-' + type + '-error';
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

		const date = $( 'input[name="intsds_booking_date"]' ).filter( function () {
			return !! this.value;
		} ).first().val() || '';

		if ( ! date ) {
			showError( 'date', intsdsData.i18n.selectDate );
			valid = false;
		} else {
			// Validate weekday, past dates, and the daily cutoff (product timezone).
			const dateObj  = new Date( date + 'T00:00:00' );
			const disabled = state.currentDisabled || [];
			if ( intsdsIsDateBlocked( dateObj, state.currentSchedule, disabled, intsdsTzState() ) ) {
				showError( 'date', intsdsData.i18n.invalidDate );
				valid = false;
			}
		}

		if ( serviceType === 'date_time' ) {
			const time = $( '.intsds-time-select' ).filter( function () {
				return !! this.value;
			} ).first().val() || '';

			if ( ! time ) {
				showError( 'time', intsdsData.i18n.selectTime );
				valid = false;
			} else if ( date ) {
				// Validate time range.
				const dateObj     = new Date( date + 'T00:00:00' );
				const daySchedule = getDaySchedule( dateObj, state.currentSchedule );
				if ( daySchedule && daySchedule.enabled ) {
					if ( time < daySchedule.start || time > daySchedule.end ) {
						showError( 'time', intsdsData.i18n.invalidTime );
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
		$form.on( 'found_variation.ints show_variation.ints', function ( event, variation ) {
			const variationId = variation.variation_id;
			const varData = ( intsdsData.variations || {} )[ variationId ];

			if ( varData ) {
				applyServiceType( varData.serviceType, varData.schedule, varData.disabledWeekdays );
			} else {
				// Fall back to parent settings.
				applyServiceType(
					intsdsData.serviceType,
					intsdsData.schedule,
					intsdsData.disabledWeekdays
				);
			}
		} );

		// Variation reset.
		$form.on( 'reset_data.ints', function () {
			applyServiceType(
				intsdsData.serviceType,
				intsdsData.schedule,
				intsdsData.disabledWeekdays
			);
		} );
	}

	// ───────────────────────────────────────────────
	// Add-to-cart validation intercept
	// ───────────────────────────────────────────────

	function bindAddToCartValidation() {
		// Delegate to the whole field-group so any click inside opens the calendar.
		// This covers:
		//  - The original .intsds-date-input (pre-Flatpickr or fresh clones).
		//  - The visible altInput created by Flatpickr (class flatpickr-input),
		//    whose event listeners are NOT copied when a theme clones the DOM for
		//    a sticky/mobile drawer.
		//  - The "change date" scenario where _flatpickr already exists but the
		//    old code returned without opening it.
		$( document ).on( 'click.ints', '.intsds-date-field-group', function ( e ) {
			// Ignore clicks that land inside the Flatpickr calendar popup itself.
			if ( $( e.target ).closest( '.flatpickr-calendar' ).length ) {
				return;
			}

			const originalInput = $( this ).find( '.intsds-date-input' )[0];
			if ( ! originalInput ) {
				return;
			}

			// Already initialised — open it regardless (handles cloned drawers
			// and the user wanting to change a previously chosen date).
			// Guard against stale/destroyed instances: a live Flatpickr always
			// has a calendarContainer; a destroyed one may not.
			if ( originalInput._flatpickr ) {
				const fp = originalInput._flatpickr;
				if ( typeof fp.open === 'function' && fp.calendarContainer ) {
					fp.open();
					return;
				}
				// Stale/destroyed instance — delete the property (never null it)
				// so Flatpickr's internal guard doesn't crash on re-init.
				try {
					if ( typeof fp.destroy === 'function' ) { fp.destroy(); }
				} catch ( e ) { /* already gone */ }
				delete originalInput._flatpickr;
			}

			// First interaction on this input — initialise then open.
			initSingleCalendar( originalInput, state.currentDisabled || [] );
			if ( originalInput._flatpickr && typeof originalInput._flatpickr.open === 'function' ) {
				originalInput._flatpickr.open();
			}
		} );

		// Propagate choice across duplicate time dropdowns
		$( document ).on( 'change.ints', '.intsds-time-select', function () {
			$( '.intsds-time-select' ).val( $( this ).val() || '' );
			clearError( 'time' );
		} );

		function isBookingUIActive() {
			const $wrapper = $( '.intsds-booking-wrapper' );
			return $wrapper.length && $wrapper.is( ':visible' );
		}

		function syncBookingFieldsToForm( $form ) {
			if ( ! $form || ! $form.length ) {
				return;
			}

			const dateVal = $( 'input[name="intsds_booking_date"]' ).filter( function () { return !! this.value; } ).first().val() || '';
			const timeVal = $( '.intsds-time-select' ).filter( function () { return !! this.value; } ).first().val() || '';
			const nonceVal = ( $( 'input[name="intsds_nonce"]' ).first().val() || '' );

			if ( nonceVal ) {
				let $nonce = $form.find( 'input[name="intsds_nonce"]' );
				if ( ! $nonce.length ) {
					$nonce = $( '<input type="hidden" name="intsds_nonce" />' ).appendTo( $form );
				}
				$nonce.val( nonceVal );
			}

			let $date = $form.find( 'input[name="intsds_booking_date"]' );
			if ( ! $date.length ) {
				$date = $( '<input type="hidden" name="intsds_booking_date" />' ).appendTo( $form );
			}
			$date.val( dateVal );

			let $time = $form.find( 'input[name="intsds_booking_time"]' );
			if ( ! $time.length ) {
				$time = $( '<input type="hidden" name="intsds_booking_time" />' ).appendTo( $form );
			}
			$time.val( timeVal );
		}

		function scrollToFirstError() {
			const $firstError = $( '.intsds-error:visible' ).first();
			if ( $firstError.length ) {
				$( 'html, body' ).animate(
					{ scrollTop: $firstError.offset().top - 80 },
					300
				);
			}
		}

		// Validate on button click for classic product forms/themes.
		$( document ).on( 'click.ints', '.single_add_to_cart_button', function ( e ) {
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
		$( document ).on( 'submit.ints', 'form.cart', function ( e ) {
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
		$( document.body ).on( 'adding_to_cart.ints', function ( _event, $button, data ) {
			if ( ! isBookingUIActive() || ! data ) {
				return;
			}

			if ( ! validateFields() ) {
				return;
			}

			data.intsds_booking_date = $( 'input[name="intsds_booking_date"]' ).filter( function () { return !! this.value; } ).first().val() || '';
			data.intsds_booking_time = $( '.intsds-time-select' ).filter( function () { return !! this.value; } ).first().val() || '';
			data.intsds_nonce = ( $( 'input[name="intsds_nonce"]' ).first().val() || '' );
		} );
	}

	// ───────────────────────────────────────────────
	// Public API
	// ───────────────────────────────────────────────

	/**
	 * Initialise — idempotent.
	 */
	INTSDS.init = function () {
		if ( state.initialized ) {
			return;
		}
		state.initialized = true;

		// Apply initial service type.
		applyServiceType(
			intsdsData.serviceType,
			intsdsData.schedule,
			intsdsData.disabledWeekdays
		);

		// Variable product events.
		if ( intsdsData.productType === 'variable' ) {
			bindVariationEvents();
		}

		// Validate before add-to-cart.
		bindAddToCartValidation();
	};

	/**
	 * Re-init (for AJAX / Quick View contexts).
	 */
	INTSDS.reinit = function () {
		state.initialized = false;
		INTSDS.init();
	};

	// ───────────────────────────────────────────────
	// Bootstrap
	// ───────────────────────────────────────────────

	$( document ).ready( function () {
		INTSDS.init();
	} );

	// Re-init after WooCommerce AJAX product updates.
	$( document ).on( 'wc_fragments_refreshed wc_cart_button_updated', function () {
		INTSDS.init();
	} );

} )( jQuery, window.intsdsData || {} );
