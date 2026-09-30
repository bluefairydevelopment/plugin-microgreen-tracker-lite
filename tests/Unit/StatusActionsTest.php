<?php
/**
 * Finishing a tray from the free plugin.
 *
 * Until 1.1.1 nothing in the free plugin could set either end of the cycle, so
 * every tray logged here stayed Active. These write the same milestones Pro
 * records, which is what makes a tray finished here read as finished there.
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StatusActionsTest extends TestCase {

	protected function setUp(): void {
		bfmt_test_reset();
		$GLOBALS['bfmt_types'][1] = 'mgtrack_tray';
		$GLOBALS['bfmt_types'][2] = 'post';
	}

	public function test_mark_harvested_records_the_date_and_reads_harvested(): void {
		$this->assertTrue( BFMT_Status::mark_harvested( 1, '2026-09-30' ) );

		$this->assertSame( '2026-09-30', $GLOBALS['bfmt_meta'][1]['_mgtrack_harvest_date'] );
		$this->assertSame( 'harvested', BFMT_Status::get( 1 ) );
		$this->assertTrue( BFMT_Status::is_finished( 1 ) );
	}

	/**
	 * A harvest clears a failure, so undoing the harvest later does not
	 * resurrect a failure nobody can see.
	 */
	public function test_a_harvest_clears_an_earlier_failure(): void {
		update_post_meta( 1, '_mgtrack_failed', '1' );

		BFMT_Status::mark_harvested( 1, '2026-09-30' );

		$this->assertArrayNotHasKey( '_mgtrack_failed', $GLOBALS['bfmt_meta'][1] );
		$this->assertSame( [ [ 1, '_mgtrack_failed' ] ], bfmt_test_calls( 'delete_post_meta' ) );
	}

	public function test_mark_harvested_refuses_a_bad_date_or_a_non_tray(): void {
		$this->assertFalse( BFMT_Status::mark_harvested( 1, '2026-02-30' ) );
		$this->assertFalse( BFMT_Status::mark_harvested( 2, '2026-09-30' ) );
		$this->assertSame( [], bfmt_test_calls( 'update_post_meta' ) );
	}

	public function test_mark_failed_flags_the_tray(): void {
		$this->assertTrue( BFMT_Status::mark_failed( 1 ) );

		$this->assertSame( '1', $GLOBALS['bfmt_meta'][1]['_mgtrack_failed'] );
		$this->assertSame( 'failed', BFMT_Status::get( 1 ) );
	}

	public function test_a_harvested_tray_cannot_be_marked_failed(): void {
		update_post_meta( 1, '_mgtrack_harvest_date', '2026-09-01' );

		$this->assertFalse( BFMT_Status::mark_failed( 1 ) );
		$this->assertArrayNotHasKey( '_mgtrack_failed', $GLOBALS['bfmt_meta'][1] );
	}

	public function test_mark_failed_refuses_a_non_tray(): void {
		$this->assertFalse( BFMT_Status::mark_failed( 2 ) );
		$this->assertSame( [], bfmt_test_calls( 'update_post_meta' ) );
	}

	public function test_reopen_puts_either_end_back_to_growing(): void {
		update_post_meta( 1, '_mgtrack_harvest_date', '2026-09-01' );
		update_post_meta( 1, '_mgtrack_failed', '1' );

		$this->assertTrue( BFMT_Status::reopen( 1 ) );
		$this->assertSame( 'active', BFMT_Status::get( 1 ) );
		$this->assertFalse( BFMT_Status::is_finished( 1 ) );
	}

	public function test_reopen_refuses_a_non_tray(): void {
		$this->assertFalse( BFMT_Status::reopen( 2 ) );
		$this->assertSame( [], bfmt_test_calls( 'delete_post_meta' ) );
	}

	public function test_is_finished_for_each_state(): void {
		$GLOBALS['bfmt_types'][3] = 'mgtrack_tray';
		update_post_meta( 1, '_mgtrack_harvest_date', '2026-09-01' );
		update_post_meta( 3, '_mgtrack_failed', '1' );

		$this->assertTrue( BFMT_Status::is_finished( 1 ) );
		$this->assertTrue( BFMT_Status::is_finished( 3 ) );
		$this->assertFalse( BFMT_Status::is_finished( 4 ) );
	}
}
