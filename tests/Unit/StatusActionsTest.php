<?php
/**
 * Finishing a tray from the free plugin.
 *
 * Until 1.1.1 nothing in the free plugin could set either end of the cycle, so
 * every tray logged here stayed Active. These write the milestones Pro reads,
 * in the form Pro reads them: a harvest DATE and a failure DATE. Pro works a
 * tray's days and cost out from those dates, so a bare "1" would show a closed
 * date of "1" and tens of thousands of negative days in Pro's archive.
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
		$GLOBALS['bfmt_types'][3] = 'mgtrack_tray';
		$GLOBALS['bfmt_statuses'][3] = 'trash';
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
		update_post_meta( 1, '_mgtrack_failed', '2026-09-20' );

		BFMT_Status::mark_harvested( 1, '2026-09-30' );

		$this->assertArrayNotHasKey( '_mgtrack_failed', $GLOBALS['bfmt_meta'][1] );
		$this->assertSame( [ [ 1, '_mgtrack_failed' ] ], bfmt_test_calls( 'delete_post_meta' ) );
	}

	/**
	 * A second click or a stale tab is success, and does NOT move the date
	 * already recorded (Pro's "average days to harvest" reads it).
	 */
	public function test_harvesting_again_is_success_and_keeps_the_first_date(): void {
		BFMT_Status::mark_harvested( 1, '2026-09-01' );

		$this->assertTrue( BFMT_Status::mark_harvested( 1, '2026-09-30' ) );
		$this->assertSame( '2026-09-01', $GLOBALS['bfmt_meta'][1]['_mgtrack_harvest_date'] );
		$this->assertCount( 1, bfmt_test_calls( 'update_post_meta' ) );
	}

	public function test_mark_harvested_refuses_a_bad_date_a_non_tray_or_a_trashed_tray(): void {
		$this->assertFalse( BFMT_Status::mark_harvested( 1, '2026-02-30' ) );
		$this->assertFalse( BFMT_Status::mark_harvested( 1, '2026-09-30 12:00:00' ) );
		$this->assertFalse( BFMT_Status::mark_harvested( 2, '2026-09-30' ) );
		$this->assertFalse( BFMT_Status::mark_harvested( 3, '2026-09-30' ) );
		$this->assertSame( [], bfmt_test_calls( 'update_post_meta' ) );
	}

	public function test_mark_failed_records_the_date_not_a_flag(): void {
		$this->assertTrue( BFMT_Status::mark_failed( 1, '2026-09-30' ) );

		$this->assertSame( '2026-09-30', $GLOBALS['bfmt_meta'][1]['_mgtrack_failed'] );
		$this->assertTrue( BFMT_Tray::is_date( $GLOBALS['bfmt_meta'][1]['_mgtrack_failed'] ) );
		$this->assertSame( 'failed', BFMT_Status::get( 1 ) );
	}

	public function test_failing_again_is_success_and_keeps_the_first_date(): void {
		BFMT_Status::mark_failed( 1, '2026-09-01' );

		$this->assertTrue( BFMT_Status::mark_failed( 1, '2026-09-30' ) );
		$this->assertSame( '2026-09-01', $GLOBALS['bfmt_meta'][1]['_mgtrack_failed'] );
	}

	public function test_a_harvested_tray_cannot_be_marked_failed(): void {
		update_post_meta( 1, '_mgtrack_harvest_date', '2026-09-01' );

		$this->assertFalse( BFMT_Status::mark_failed( 1, '2026-09-30' ) );
		$this->assertArrayNotHasKey( '_mgtrack_failed', $GLOBALS['bfmt_meta'][1] );
	}

	public function test_mark_failed_refuses_a_bad_date_a_non_tray_or_a_trashed_tray(): void {
		$this->assertFalse( BFMT_Status::mark_failed( 1, 'today' ) );
		$this->assertFalse( BFMT_Status::mark_failed( 2, '2026-09-30' ) );
		$this->assertFalse( BFMT_Status::mark_failed( 3, '2026-09-30' ) );
		$this->assertSame( [], bfmt_test_calls( 'update_post_meta' ) );
	}

	public function test_reopen_puts_either_end_back_to_growing(): void {
		update_post_meta( 1, '_mgtrack_harvest_date', '2026-09-01' );
		update_post_meta( 1, '_mgtrack_failed', '2026-08-30' );

		$this->assertTrue( BFMT_Status::reopen( 1 ) );
		$this->assertSame( 'active', BFMT_Status::get( 1 ) );
		$this->assertFalse( BFMT_Status::is_finished( 1 ) );
	}

	public function test_reopen_refuses_a_non_tray_or_a_trashed_tray(): void {
		$this->assertFalse( BFMT_Status::reopen( 2 ) );
		$this->assertFalse( BFMT_Status::reopen( 3 ) );
		$this->assertSame( [], bfmt_test_calls( 'delete_post_meta' ) );
	}

	public function test_is_finished_for_each_state(): void {
		$GLOBALS['bfmt_types'][4] = 'mgtrack_tray';
		update_post_meta( 1, '_mgtrack_harvest_date', '2026-09-01' );
		update_post_meta( 4, '_mgtrack_failed', '2026-09-01' );

		$this->assertTrue( BFMT_Status::is_finished( 1 ) );
		$this->assertTrue( BFMT_Status::is_finished( 4 ) );
		$this->assertFalse( BFMT_Status::is_finished( 5 ) );
	}

	public function test_pros_finish_records_block_reopening_even_when_empty(): void {
		$this->assertSame( '', BFMT_Status::reopen_blocker( 1 ) );

		// Existence, not value: Pro writes an empty reason and a '0' yield.
		foreach ( [ '_mgtrack_harvest_date_by', '_mgtrack_failed_by', '_mgtrack_yield_weight', '_mgtrack_sale_price', '_mgtrack_failed_reason' ] as $key ) {
			$GLOBALS['bfmt_meta'] = [ 1 => [ $key => '' ] ];

			$this->assertSame( 'pro', BFMT_Status::reopen_blocker( 1 ), $key );
		}
	}

	public function test_a_slot_alone_blocks_nothing_while_it_is_free(): void {
		$GLOBALS['bfmt_meta'][1]      = [ '_mgtrack_location' => '1A3' ];
		$GLOBALS['bfmt_posts_result'] = [];

		$this->assertSame( '', BFMT_Status::reopen_blocker( 1 ) );
	}

	/**
	 * Pro's occupancy rule: another published tray in the same slot with
	 * neither a harvest nor a failure.
	 */
	public function test_a_slot_another_growing_tray_uses_blocks_reopening(): void {
		$GLOBALS['bfmt_meta'][1]      = [ '_mgtrack_location' => '1A3' ];
		$GLOBALS['bfmt_posts_result'] = [ 9 ];

		$this->assertSame( 'slot', BFMT_Status::reopen_blocker( 1 ) );

		$args = bfmt_test_calls( 'get_posts' )[0][0];
		$this->assertArrayNotHasKey( 'post__not_in', $args );
		$this->assertSame( 'publish', $args['post_status'] );
		$this->assertSame( [ 'key' => '_mgtrack_location', 'value' => '1A3' ], $args['meta_query'][0] );
		$this->assertSame( [ 'key' => '_mgtrack_harvest_date', 'compare' => 'NOT EXISTS' ], $args['meta_query'][1] );
		$this->assertSame( [ 'key' => '_mgtrack_failed', 'compare' => 'NOT EXISTS' ], $args['meta_query'][2] );
	}

	public function test_the_slot_query_is_one_row_with_every_clause_required(): void {
		$GLOBALS['bfmt_meta'][1]      = [ '_mgtrack_location' => '1A3' ];
		$GLOBALS['bfmt_posts_result'] = [];

		BFMT_Status::reopen_blocker( 1 );

		$args = bfmt_test_calls( 'get_posts' )[0][0];
		$this->assertSame( 'AND', $args['meta_query']['relation'] );
		$this->assertSame( 2, $args['posts_per_page'] );
		$this->assertSame( 'mgtrack_tray', $args['post_type'] );
	}

	public function test_pro_evidence_is_reported_ahead_of_a_taken_slot(): void {
		$GLOBALS['bfmt_meta'][1]      = [ '_mgtrack_location' => '1A3', '_mgtrack_failed_by' => '1' ];
		$GLOBALS['bfmt_posts_result'] = [ 2 ];

		$this->assertSame( 'pro', BFMT_Status::reopen_blocker( 1 ) );
	}

	/**
	 * The query can return the tray itself (the stub cannot filter); it is
	 * never counted as the occupant of its own slot.
	 */
	public function test_the_tray_itself_does_not_occupy_its_own_slot(): void {
		$GLOBALS['bfmt_meta'][1]      = [ '_mgtrack_location' => '1A3' ];
		$GLOBALS['bfmt_posts_result'] = [ 1 ];

		$this->assertSame( '', BFMT_Status::reopen_blocker( 1 ) );

		$GLOBALS['bfmt_posts_result'] = [ '1', '9' ];
		$this->assertSame( 'slot', BFMT_Status::reopen_blocker( 1 ) );
	}
}
