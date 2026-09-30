<?php
/**
 * The three states the free plugin derives, and the seam Pro replaces them at.
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StatusTest extends TestCase {

	protected function setUp(): void {
		bfmt_test_reset();
		$GLOBALS['bfmt_types']   = [ 1 => 'mgtrack_tray' ];
	}

	public function test_a_new_tray_is_active(): void {
		$this->assertSame( 'active', BFMT_Status::get( 1 ) );
	}

	public function test_a_harvest_date_makes_it_harvested(): void {
		update_post_meta( 1, '_mgtrack_harvest_date', '2026-03-20' );

		$this->assertSame( 'harvested', BFMT_Status::get( 1 ) );
	}

	public function test_a_failure_flag_makes_it_failed(): void {
		update_post_meta( 1, '_mgtrack_failed', '1' );

		$this->assertSame( 'failed', BFMT_Status::get( 1 ) );
	}

	/**
	 * Harvested wins: a tray that was cut is not also a failure, whatever
	 * order the two milestones were recorded in.
	 */
	public function test_harvested_beats_failed(): void {
		update_post_meta( 1, '_mgtrack_failed', '1' );
		update_post_meta( 1, '_mgtrack_harvest_date', '2026-03-20' );

		$this->assertSame( 'harvested', BFMT_Status::get( 1 ) );
	}

	/**
	 * Status is never stored, so there is no column to fall out of step with
	 * the milestone that actually happened.
	 */
	public function test_status_is_not_written_anywhere(): void {
		BFMT_Status::get( 1 );

		$this->assertSame( [], $GLOBALS['bfmt_meta'][1] ?? [] );
	}

	/**
	 * This is the seam that makes Pro an extension rather than a second
	 * implementation: it answers the filter, and the base keeps deciding the
	 * two ends of the cycle.
	 */
	public function test_an_add_on_can_replace_the_answer(): void {
		$GLOBALS['bfmt_filters']['bfmt_tray_status'] = static fn( string $s, int $id ): string => 'germinating';

		$this->assertSame( 'germinating', BFMT_Status::get( 1 ) );
	}

	public function test_an_unknown_state_still_gets_a_readable_label(): void {
		$this->assertSame( 'Germinating', BFMT_Status::label( 'germinating' ) );
	}

	public function test_a_known_state_uses_its_own_label(): void {
		$this->assertSame( 'Harvested', BFMT_Status::label( 'harvested' ) );
	}
}
