<?php
/**
 * Date handling — the defect that reported a save which never happened.
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TrayDateTest extends TestCase {

	protected function setUp(): void {
		bfmt_test_reset();
	}

	public function test_a_real_date_is_accepted(): void {
		$this->assertTrue( BFMT_Tray::is_date( '2026-03-14' ) );
	}

	public function test_a_leap_day_that_exists_is_accepted(): void {
		$this->assertTrue( BFMT_Tray::is_date( '2028-02-29' ) );
	}

	/**
	 * The old code checked the shape with a regex alone. 2026-02-30 has the
	 * right shape and is not a day, so it passed validation, reached a DATE
	 * column and was rejected there — after the user had been told it saved.
	 */
	public function test_a_day_that_does_not_exist_is_refused(): void {
		$this->assertFalse( BFMT_Tray::is_date( '2026-02-30' ) );
	}

	public function test_a_month_that_does_not_exist_is_refused(): void {
		$this->assertFalse( BFMT_Tray::is_date( '2026-13-01' ) );
	}

	public function test_a_leap_day_in_a_common_year_is_refused(): void {
		$this->assertFalse( BFMT_Tray::is_date( '2026-02-29' ) );
	}

	public function test_the_american_ordering_is_refused_rather_than_reinterpreted(): void {
		// Guessing at 03/14/2026 would silently record the wrong day for
		// anyone whose locale writes dates the other way round.
		$this->assertFalse( BFMT_Tray::is_date( '03/14/2026' ) );
	}

	public function test_an_empty_string_is_not_a_date(): void {
		$this->assertFalse( BFMT_Tray::is_date( '' ) );
	}

	public function test_a_date_with_a_time_is_refused(): void {
		$this->assertFalse( BFMT_Tray::is_date( '2026-03-14 09:00:00' ) );
	}
}
