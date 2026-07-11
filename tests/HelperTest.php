<?php
/**
 * Unit tests for INTSDS\Helper.
 *
 * WordPress functions are stubbed via Brain Monkey; no WordPress runtime or DB.
 *
 * @package INTSDS
 */

declare( strict_types=1 );

namespace INTSDS\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use INTSDS\Helper;
use PHPUnit\Framework\TestCase;

final class HelperTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Common WordPress function stubs used across Helper methods.
		Functions\stubs(
			array(
				'__'                  => static function ( $text ) {
					return $text;
				},
				'esc_html__'          => static function ( $text ) {
					return $text;
				},
				'sanitize_text_field' => static function ( $str ) {
					return is_string( $str ) ? trim( (string) preg_replace( '/[\r\n\t ]+/', ' ', $str ) ) : '';
				},
				'wp_unslash'          => static function ( $value ) {
					return $value;
				},
				'wp_timezone_string'  => 'UTC',
			)
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ── Helpers ──────────────────────────────────────────────

	/** Build a fully-enabled weekly schedule with uniform times. */
	private function schedule( string $start = '09:00', string $end = '16:00', bool $enabled = true ): array {
		$days = array( 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday' );
		$out  = array();
		foreach ( $days as $d ) {
			$out[ $d ] = array(
				'enabled' => $enabled,
				'start'   => $start,
				'end'     => $end,
			);
		}
		return $out;
	}

	/** Fixed UTC instant. */
	private function utc( string $utc ): \DateTimeImmutable {
		return new \DateTimeImmutable( $utc, new \DateTimeZone( 'UTC' ) );
	}

	private function stubPostMeta( array $map ): void {
		Functions\when( 'get_post_meta' )->alias(
			static function ( $id, $key, $single = false ) use ( $map ) {
				return $map[ $key ] ?? '';
			}
		);
	}

	// ── time_to_minutes ─────────────────────────────────────

	/** @dataProvider provideTimes */
	public function test_time_to_minutes( string $input, ?int $expected ): void {
		$this->assertSame( $expected, Helper::time_to_minutes( $input ) );
	}

	public function provideTimes(): array {
		return array(
			'midnight'   => array( '00:00', 0 ),
			'nine30'     => array( '09:30', 570 ),
			'end of day' => array( '23:59', 1439 ),
			'single h'   => array( '9:00', null ),
			'garbage'    => array( 'abc', null ),
			'empty'      => array( '', null ),
		);
	}

	// ── get_weekday_index ───────────────────────────────────

	public function test_get_weekday_index(): void {
		$this->assertSame( 0, Helper::get_weekday_index( '2021-01-03' ) ); // Sunday
		$this->assertSame( 1, Helper::get_weekday_index( '2021-01-04' ) ); // Monday
		$this->assertSame( 6, Helper::get_weekday_index( '2021-01-09' ) ); // Saturday
		$this->assertFalse( Helper::get_weekday_index( 'not-a-date' ) );
	}

	// ── timezone_object ─────────────────────────────────────

	public function test_timezone_object_iana(): void {
		$this->assertSame( 'Asia/Dubai', Helper::timezone_object( 'Asia/Dubai' )->getName() );
	}

	public function test_timezone_object_manual_offsets(): void {
		$ref = new \DateTimeImmutable( '2020-01-01 00:00:00' );
		$this->assertSame( 14400, Helper::timezone_object( 'UTC+4' )->getOffset( $ref ) );
		$this->assertSame( 19800, Helper::timezone_object( 'UTC+5.5' )->getOffset( $ref ) );
		$this->assertSame( -10800, Helper::timezone_object( 'UTC-3' )->getOffset( $ref ) );
	}

	public function test_timezone_object_invalid_falls_back(): void {
		Functions\when( 'wp_timezone' )->justReturn( new \DateTimeZone( 'UTC' ) );
		$this->assertSame( 'UTC', Helper::timezone_object( 'Totally/Bogus' )->getName() );
	}

	// ── now_in_timezone (injected instant) ──────────────────

	public function test_now_in_timezone(): void {
		$instant = $this->utc( '2026-07-15 12:00:00' ); // 16:00 in Dubai, 08:00 in New York (EDT)

		$dubai = Helper::now_in_timezone( 'Asia/Dubai', $instant );
		$this->assertSame( '2026-07-15', $dubai['date'] );
		$this->assertSame( 16 * 60, $dubai['minutes'] );

		$ny = Helper::now_in_timezone( 'America/New_York', $instant );
		$this->assertSame( '2026-07-15', $ny['date'] );
		$this->assertSame( 8 * 60, $ny['minutes'] );
	}

	// ── is_past_cutoff ──────────────────────────────────────

	public function test_cutoff_none_blocks_only_past_dates(): void {
		$now = $this->utc( '2026-07-15 12:00:00' );
		$sch = $this->schedule();

		$this->assertTrue( Helper::is_past_cutoff( '2026-07-14', $sch, 'UTC', Helper::CUTOFF_NONE, 0, 0, 0, $now ), 'past date' );
		$this->assertFalse( Helper::is_past_cutoff( '2026-07-15', $sch, 'UTC', Helper::CUTOFF_NONE, 0, 0, 0, $now ), 'today' );
		$this->assertFalse( Helper::is_past_cutoff( '2026-07-16', $sch, 'UTC', Helper::CUTOFF_NONE, 0, 0, 0, $now ), 'future' );
	}

	public function test_cutoff_end_with_hours_lead(): void {
		// Europe/Berlin (CEST = UTC+2). End 16:00, lead 2h -> closes 14:00 Berlin.
		$sch = $this->schedule( '09:00', '16:00' );
		$d   = '2026-07-15';

		$this->assertFalse( Helper::is_past_cutoff( $d, $sch, 'Europe/Berlin', Helper::CUTOFF_END, 0, 2, 0, $this->utc( '2026-07-15 11:59:00' ) ), '13:59 open' );
		$this->assertTrue( Helper::is_past_cutoff( $d, $sch, 'Europe/Berlin', Helper::CUTOFF_END, 0, 2, 0, $this->utc( '2026-07-15 12:00:00' ) ), '14:00 closed' );
		$this->assertTrue( Helper::is_past_cutoff( $d, $sch, 'Europe/Berlin', Helper::CUTOFF_END, 0, 2, 0, $this->utc( '2026-07-15 12:01:00' ) ), '14:01 closed' );
	}

	public function test_cutoff_start_with_days_lead(): void {
		// Start 09:00, 3 days advance. Must book by 2026-07-12 09:00 Berlin.
		$sch = $this->schedule( '09:00', '16:00' );
		$d   = '2026-07-15';

		$this->assertFalse( Helper::is_past_cutoff( $d, $sch, 'Europe/Berlin', Helper::CUTOFF_START, 3, 0, 0, $this->utc( '2026-07-12 06:59:00' ) ), '08:59 open' );
		$this->assertTrue( Helper::is_past_cutoff( $d, $sch, 'Europe/Berlin', Helper::CUTOFF_START, 3, 0, 0, $this->utc( '2026-07-12 07:00:00' ) ), '09:00 closed' );
		$this->assertFalse( Helper::is_past_cutoff( $d, $sch, 'Europe/Berlin', Helper::CUTOFF_START, 3, 0, 0, $this->utc( '2026-07-10 10:00:00' ) ), '5 days out open' );
	}

	public function test_cutoff_minutes_lead(): void {
		// End 16:00, lead 1h30m -> closes 14:30 Berlin.
		$sch = $this->schedule( '09:00', '16:00' );
		$d   = '2026-07-15';

		$this->assertFalse( Helper::is_past_cutoff( $d, $sch, 'Europe/Berlin', Helper::CUTOFF_END, 0, 1, 30, $this->utc( '2026-07-15 12:29:00' ) ), '14:29 open' );
		$this->assertTrue( Helper::is_past_cutoff( $d, $sch, 'Europe/Berlin', Helper::CUTOFF_END, 0, 1, 30, $this->utc( '2026-07-15 12:30:00' ) ), '14:30 closed' );
	}

	public function test_cutoff_dubai_example(): void {
		// Asia/Dubai (UTC+4, no DST). End 16:00, no lead -> closes 16:00 Dubai.
		$sch = $this->schedule( '09:00', '16:00' );
		$d   = '2026-07-11';

		$this->assertTrue( Helper::is_past_cutoff( $d, $sch, 'Asia/Dubai', Helper::CUTOFF_END, 0, 0, 0, $this->utc( '2026-07-11 12:00:00' ) ), '16:00 Dubai closed' );
		$this->assertFalse( Helper::is_past_cutoff( $d, $sch, 'Asia/Dubai', Helper::CUTOFF_END, 0, 0, 0, $this->utc( '2026-07-11 11:00:00' ) ), '15:00 Dubai open' );
	}

	public function test_cutoff_invalid_reference_time_is_not_blocked(): void {
		$sch = $this->schedule( '', '' ); // malformed times
		$this->assertFalse(
			Helper::is_past_cutoff( '2026-07-20', $sch, 'UTC', Helper::CUTOFF_END, 0, 0, 0, $this->utc( '2026-07-15 12:00:00' ) )
		);
	}

	// ── get_cutoff / get_cutoff_lead ────────────────────────

	/** @dataProvider provideCutoffMeta */
	public function test_get_cutoff( $stored, string $expected ): void {
		$this->stubPostMeta( array( Helper::META_CUTOFF => $stored ) );
		$this->assertSame( $expected, Helper::get_cutoff( 5 ) );
	}

	public function provideCutoffMeta(): array {
		return array(
			array( 'start', 'start' ),
			array( 'end', 'end' ),
			array( 'garbage', 'none' ),
			array( '', 'none' ),
		);
	}

	public function test_get_cutoff_lead_clamps(): void {
		$this->stubPostMeta(
			array(
				Helper::META_CUTOFF_DAYS    => -5,
				Helper::META_CUTOFF_HOURS   => 99,
				Helper::META_CUTOFF_MINUTES => 200,
			)
		);
		$this->assertSame( array( 'days' => 0, 'hours' => 23, 'minutes' => 59 ), Helper::get_cutoff_lead( 5 ) );
	}

	public function test_get_cutoff_lead_minutes(): void {
		$this->stubPostMeta(
			array(
				Helper::META_CUTOFF_DAYS    => 2,
				Helper::META_CUTOFF_HOURS   => 1,
				Helper::META_CUTOFF_MINUTES => 30,
			)
		);
		$this->assertSame( 2 * 1440 + 60 + 30, Helper::get_cutoff_lead_minutes( 5 ) );
	}

	// ── get_timezone_string ─────────────────────────────────

	public function test_get_timezone_string_uses_meta_then_default(): void {
		$this->stubPostMeta( array( Helper::META_TIMEZONE => 'Asia/Dubai' ) );
		$this->assertSame( 'Asia/Dubai', Helper::get_timezone_string( 5 ) );

		$this->stubPostMeta( array( Helper::META_TIMEZONE => '' ) );
		$this->assertSame( 'UTC', Helper::get_timezone_string( 5 ) ); // wp_timezone_string() stub
	}

	// ── sanitizers ──────────────────────────────────────────

	/** @dataProvider provideDates */
	public function test_sanitize_date( string $input, string $expected ): void {
		$this->assertSame( $expected, Helper::sanitize_date( $input ) );
	}

	public function provideDates(): array {
		return array(
			'valid'        => array( '2026-07-15', '2026-07-15' ),
			'wrong format' => array( '2026/07/15', '' ),
			'not a date'   => array( 'hello', '' ),
			'empty'        => array( '', '' ),
		);
	}

	/** @dataProvider provideSanTimes */
	public function test_sanitize_time( string $input, string $expected ): void {
		$this->assertSame( $expected, Helper::sanitize_time( $input ) );
	}

	public function provideSanTimes(): array {
		return array(
			'valid'    => array( '09:30', '09:30' ),
			'single h' => array( '9:30', '' ),
			'garbage'  => array( 'ab:cd', '' ),
			'empty'    => array( '', '' ),
		);
	}

	public function test_sanitize_date_format(): void {
		$this->assertSame( 'Y-m-d', Helper::sanitize_date_format( 'Y-m-d' ) );
		$this->assertSame( Helper::DEFAULT_DATE_FORMAT, Helper::sanitize_date_format( 'evil-format' ) );
	}

	public function test_sanitize_label_trims(): void {
		$this->assertSame( 'Book Now', Helper::sanitize_label( '  Book Now  ' ) );
	}

	/** @dataProvider provideYesNo */
	public function test_sanitize_yes_no( string $input, string $expected ): void {
		$this->assertSame( $expected, Helper::sanitize_yes_no( $input ) );
	}

	public function provideYesNo(): array {
		return array(
			array( 'yes', 'yes' ),
			array( 'YES', 'yes' ),
			array( 'no', 'no' ),
			array( 'maybe', 'no' ),
			array( '', 'no' ),
		);
	}

	// ── schedule helpers ────────────────────────────────────

	public function test_normalize_schedule_defaults(): void {
		$sched = Helper::normalize_schedule( array() );
		$this->assertCount( 7, $sched );
		$this->assertFalse( $sched['monday']['enabled'] );
		$this->assertSame( '09:00', $sched['monday']['start'] );
		$this->assertSame( '17:00', $sched['monday']['end'] );
	}

	public function test_normalize_schedule_merges_input(): void {
		$sched = Helper::normalize_schedule(
			array( 'monday' => array( 'enabled' => '1', 'start' => '08:00', 'end' => '12:00' ) )
		);
		$this->assertTrue( $sched['monday']['enabled'] );
		$this->assertSame( '08:00', $sched['monday']['start'] );
		$this->assertSame( '12:00', $sched['monday']['end'] );
		$this->assertFalse( $sched['tuesday']['enabled'] );
	}

	public function test_is_date_available(): void {
		$sched                       = $this->schedule( '09:00', '17:00', false );
		$sched['sunday']['enabled']  = true;
		$this->assertTrue( Helper::is_date_available( '2021-01-03', $sched ) );  // Sunday
		$this->assertFalse( Helper::is_date_available( '2021-01-04', $sched ) ); // Monday
		$this->assertFalse( Helper::is_date_available( 'bad-date', $sched ) );
	}

	public function test_is_time_available(): void {
		$sched = $this->schedule( '09:00', '17:00' ); // all days enabled
		$this->assertTrue( Helper::is_time_available( '2021-01-04', '10:00', $sched ) );
		$this->assertFalse( Helper::is_time_available( '2021-01-04', '08:00', $sched ) );
		$this->assertFalse( Helper::is_time_available( '2021-01-04', '18:00', $sched ) );

		$disabled = $this->schedule( '09:00', '17:00', false );
		$this->assertFalse( Helper::is_time_available( '2021-01-04', '10:00', $disabled ) );
	}

	public function test_disabled_weekday_indices(): void {
		$sched                        = $this->schedule( '09:00', '17:00', true );
		$sched['sunday']['enabled']   = false;
		$sched['saturday']['enabled'] = false;
		$this->assertSame( array( 0, 6 ), Helper::disabled_weekday_indices( $sched ) );
	}

	public function test_schedule_for_js_shape(): void {
		$js = Helper::schedule_for_js( $this->schedule() );
		$this->assertCount( 7, $js );
		$this->assertSame( 0, $js[0]['index'] );
		$this->assertSame( 'sunday', $js[0]['day'] );
		$this->assertArrayHasKey( 'enabled', $js[0] );
		$this->assertArrayHasKey( 'start', $js[0] );
		$this->assertArrayHasKey( 'end', $js[0] );
	}

	// ── service types / options / defaults ──────────────────

	public function test_service_types_excludes_date_time(): void {
		$this->assertSame( array( 'open_dated', 'date_only' ), Helper::service_types() );
		$this->assertNotContains( 'date_time', Helper::service_types() );
	}

	public function test_service_type_labels_excludes_date_time(): void {
		$labels = Helper::service_type_labels();
		$this->assertSame( array( 'open_dated', 'date_only' ), array_keys( $labels ) );
	}

	public function test_cutoff_options_keys(): void {
		$this->assertSame( array( 'none', 'start', 'end' ), array_keys( Helper::cutoff_options() ) );
	}

	public function test_translatable_defaults(): void {
		$this->assertSame( 'Select Date', Helper::default_date_label() );
		$this->assertSame( 'Please select a date.', Helper::default_date_error() );
	}
}
