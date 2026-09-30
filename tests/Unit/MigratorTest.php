<?php
/**
 * The one-time import of Lite 1.0.0's table.
 *
 * The table name is an identifier, not a value, so 1.1.0 interpolated it and
 * silenced Plugin Check. 1.1.1 passes it through $wpdb->prepare() as %i, which
 * WordPress has supported since 6.2 (this plugin requires 6.5).
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * A $wpdb double. prepare() returns a TOKEN, not SQL, and get_var()/
 * get_results() refuse any string that is not such a token — so a query that
 * skips prepare() fails loudly instead of being scanned for afterwards.
 */
final class BFMT_Test_Wpdb {
	public string $prefix = 'wp_';
	/** @var list<array{string, array}> */
	public array $prepared = [];
	/** @var list<string> every string handed to get_var/get_results */
	public array $executed = [];
	public array $var_answers = [];
	public array $rows = [];
	public array $escaped_like = [];

	public function esc_like( string $text ): string {
		$this->escaped_like[] = $text;

		return addcslashes( $text, '_%\\' );
	}

	public function prepare( string $query, ...$args ): string {
		$this->prepared[] = [ $query, $args ];

		return 'PREPARED#' . ( count( $this->prepared ) - 1 );
	}

	private function run( string $query ): void {
		$this->executed[] = $query;

		if ( ! str_starts_with( $query, 'PREPARED#' ) ) {
			throw new LogicException( "Unprepared query reached the database: {$query}" );
		}
	}

	public function get_var( string $query ): mixed {
		$this->run( $query );

		return array_shift( $this->var_answers );
	}

	public function get_results( string $query ): array {
		$this->run( $query );

		return $this->rows;
	}

	/** @var list<int> Lite's ids, for get_col() */
	public array $ids = [];

	public function get_col( string $query ): array {
		$this->run( $query );

		return $this->ids;
	}
}

final class MigratorTest extends TestCase {

	private BFMT_Test_Wpdb $db;

	protected function setUp(): void {
		bfmt_test_reset();
		$this->db        = new BFMT_Test_Wpdb();
		$GLOBALS['wpdb'] = $this->db;
	}

	/**
	 * @return array{string, array} the prepare() call whose SQL starts with $sql
	 */
	private function prepared_starting( string $sql ): array {
		foreach ( $this->db->prepared as $call ) {
			if ( str_starts_with( $call[0], $sql ) ) {
				return $call;
			}
		}

		$this->fail( "No prepared query starting with {$sql}" );
	}

	public function test_the_table_name_is_prefixed(): void {
		$this->assertSame( 'wp_bfmtl_grows', BFMT_Migrator::legacy_table() );
	}

	public function test_the_existence_check_escapes_the_like_pattern(): void {
		$this->db->var_answers = [ 'wp_bfmtl_grows' ];

		$this->assertTrue( BFMT_Migrator::legacy_table_exists() );
		$this->assertSame( [ 'wp_bfmtl_grows' ], $this->db->escaped_like );
		$this->assertSame( [ 'SHOW TABLES LIKE %s', [ 'wp\\_bfmtl\\_grows' ] ], $this->prepared_starting( 'SHOW TABLES' ) );
	}

	public function test_a_different_table_name_is_not_taken_for_it(): void {
		$this->db->var_answers = [ 'wp_bfmtl_grows_old' ];

		$this->assertFalse( BFMT_Migrator::legacy_table_exists() );
	}

	public function test_pending_reads_lites_ids_through_an_identifier_placeholder(): void {
		$this->db->var_answers = [ 'wp_bfmtl_grows' ];
		$this->db->ids         = [ 1, 2, 3 ];
		$GLOBALS['bfmt_options']['bfmt_legacy_imported_ids'] = [];

		$this->assertSame( 3, BFMT_Migrator::pending() );
		$this->assertSame( [ 'SELECT id FROM %i', [ 'wp_bfmtl_grows' ] ], $this->prepared_starting( 'SELECT id' ) );
	}

	/**
	 * By id, not by count: a row deleted in Lite must not hide one still
	 * waiting, and an imported tray trashed here must not look un-imported.
	 */
	public function test_pending_compares_ids_not_counts(): void {
		$this->db->var_answers = [ 'wp_bfmtl_grows' ];
		$this->db->ids         = [ 2, 3, 6 ];              // Lite row 1 was deleted in Lite; 6 is new.
		$GLOBALS['bfmt_options']['bfmt_legacy_imported_ids'] = [ 1, 2, 3, 4 ];

		$this->assertSame( 1, BFMT_Migrator::pending() );
	}

	public function test_pending_is_zero_when_everything_is_imported_even_if_the_trays_are_gone(): void {
		$this->db->var_answers = [ 'wp_bfmtl_grows' ];
		$this->db->ids         = [ 1, 2 ];
		$GLOBALS['bfmt_options']['bfmt_legacy_imported_ids'] = [ 1, 2 ];
		$GLOBALS['bfmt_posts_result'] = [];                // no trays left at all

		$this->assertSame( 0, BFMT_Migrator::pending() );
	}

	public function test_pending_is_zero_with_no_legacy_table(): void {
		$this->db->var_answers = [ null ];

		$this->assertSame( 0, BFMT_Migrator::pending() );
		$this->assertCount( 1, $this->db->executed, 'only the existence check ran' );
	}

	/**
	 * 1.1.0 kept no record, only a meta key per tray; the first read rebuilds
	 * the record from every such tray, trashed ones included, and stores it.
	 */
	public function test_the_imported_record_is_rebuilt_once_from_trays_including_trashed_ones(): void {
		$GLOBALS['bfmt_posts_result'] = [ 21, 22 ];
		$GLOBALS['bfmt_meta'][21]['_bfmt_legacy_grow_id'] = 7;
		$GLOBALS['bfmt_meta'][22]['_bfmt_legacy_grow_id'] = 9;

		$this->assertSame( [ 7, 9 ], BFMT_Migrator::imported_ids() );

		$args = bfmt_test_calls( 'get_posts' )[0][0];
		$this->assertSame( [ 'any', 'trash' ], $args['post_status'] );
		$this->assertSame( '_bfmt_legacy_grow_id', $args['meta_key'] );
		$this->assertSame( [ 7, 9 ], $GLOBALS['bfmt_options']['bfmt_legacy_imported_ids'] );

		BFMT_Migrator::imported_ids();
		$this->assertCount( 1, bfmt_test_calls( 'get_posts' ), 'read from the record after that' );
		$this->assertSame( 2, BFMT_Migrator::imported_count() );
	}

	public function test_run_reads_through_an_identifier_placeholder(): void {
		$this->db->var_answers = [ 'wp_bfmtl_grows' ];

		BFMT_Migrator::run();

		$this->assertSame( [ 'SELECT * FROM %i ORDER BY id ASC', [ 'wp_bfmtl_grows' ] ], $this->prepared_starting( 'SELECT *' ) );
	}

	/**
	 * The double throws on any string that did not come out of prepare(), so
	 * this fails for an interpolated query in either method. Positive control:
	 * the double does throw on a raw string.
	 */
	public function test_every_query_that_reaches_the_database_was_prepared(): void {
		try {
			$this->db->get_var( 'SELECT COUNT(*) FROM `wp_bfmtl_grows`' );
			$this->fail( 'the double accepted an unprepared query' );
		} catch ( LogicException ) {
			// Expected: the instrument can fail.
		}

		$this->db->executed    = [];
		$this->db->var_answers = [ 'wp_bfmtl_grows', 'wp_bfmtl_grows' ];
		$GLOBALS['bfmt_options']['bfmt_legacy_imported_ids'] = [];

		BFMT_Migrator::pending();
		BFMT_Migrator::run();

		// pending(): existence check + id list; run(): existence check + select.
		$this->assertCount( 4, $this->db->executed );
	}

	public function test_with_no_legacy_table_nothing_is_read(): void {
		$this->db->var_answers = [ null ];

		$this->assertSame( [ 'imported' => 0, 'skipped' => 0, 'failed' => 0 ], BFMT_Migrator::run() );
		$this->assertCount( 1, $this->db->executed );
		$this->assertSame( [], bfmt_test_calls( 'update_option' ), 'nothing recorded when there was nothing to import' );
	}

	public function test_a_row_already_imported_is_skipped(): void {
		$this->db->var_answers        = [ 'wp_bfmtl_grows' ];
		$this->db->rows               = [ (object) [ 'id' => 3 ] ];
		$GLOBALS['bfmt_options']['bfmt_legacy_imported_ids'] = [ 3 ];

		$result = BFMT_Migrator::run();

		$this->assertSame( [ 'imported' => 0, 'skipped' => 1, 'failed' => 0 ], $result );
		$this->assertSame( [], bfmt_test_calls( 'wp_insert_post' ) );
		$this->assertSame( $result, $GLOBALS['bfmt_options']['bfmt_legacy_import'] );
	}

	/**
	 * The happy path: each row becomes a tray, its stored status becomes the
	 * milestone that produced it, and the legacy id is written so a second
	 * activation skips it.
	 */
	public function test_rows_are_imported_with_their_status_as_a_milestone(): void {
		$this->db->var_answers = [ 'wp_bfmtl_grows' ];
		$this->db->rows        = [
			(object) [ 'id' => 1, 'variety' => 'Kale', 'seeded_date' => '2026-01-02', 'expected_harvest_date' => '2026-01-10', 'tray_size' => '10x20', 'status' => 'harvested' ],
			(object) [ 'id' => 2, 'variety' => 'Pea', 'seeded_date' => '2026-01-03', 'expected_harvest_date' => null, 'tray_size' => '', 'status' => 'failed' ],
			(object) [ 'id' => 3, 'variety' => 'Radish', 'seeded_date' => '2026-01-04', 'expected_harvest_date' => '', 'tray_size' => 'Half', 'status' => 'active' ],
		];

		$result = BFMT_Migrator::run();

		$this->assertSame( [ 'imported' => 3, 'skipped' => 0, 'failed' => 0 ], $result );
		$this->assertSame( [ 1, 2, 3 ], $GLOBALS['bfmt_options']['bfmt_legacy_imported_ids'] );
		$this->assertSame( $result, $GLOBALS['bfmt_options']['bfmt_legacy_import'] );

		[ $kale, $pea, $radish ] = [ 100, 101, 102 ];

		$this->assertSame( '2026-01-10', $GLOBALS['bfmt_meta'][ $kale ]['_mgtrack_harvest_date'] );
		$this->assertArrayNotHasKey( '_mgtrack_failed', $GLOBALS['bfmt_meta'][ $kale ] );
		// A date, as Pro stores it. No updated_at here, so the expected date,
		// else the seeded date.
		$this->assertSame( '2026-01-03', $GLOBALS['bfmt_meta'][ $pea ]['_mgtrack_failed'] );
		$this->assertArrayNotHasKey( '_mgtrack_harvest_date', $GLOBALS['bfmt_meta'][ $pea ] );
		$this->assertArrayNotHasKey( '_mgtrack_harvest_date', $GLOBALS['bfmt_meta'][ $radish ] );
		$this->assertArrayNotHasKey( '_mgtrack_failed', $GLOBALS['bfmt_meta'][ $radish ] );

		$this->assertSame( 1, $GLOBALS['bfmt_meta'][ $kale ]['_bfmt_legacy_grow_id'] );
		$this->assertSame( 2, $GLOBALS['bfmt_meta'][ $pea ]['_bfmt_legacy_grow_id'] );
		$this->assertSame( 3, $GLOBALS['bfmt_meta'][ $radish ]['_bfmt_legacy_grow_id'] );
		$this->assertSame( '2026-01-02', $GLOBALS['bfmt_meta'][ $kale ]['_mgtrack_seeding_date'] );
	}

	/**
	 * A harvested row with no expected date falls back to the seeded date, so
	 * the tray still reads as harvested.
	 */
	public function test_a_harvested_row_without_a_date_uses_the_seeded_date(): void {
		$this->db->var_answers = [ 'wp_bfmtl_grows' ];
		$this->db->rows        = [ (object) [ 'id' => 9, 'variety' => 'Kale', 'seeded_date' => '2026-02-01', 'expected_harvest_date' => '', 'tray_size' => '', 'status' => 'harvested' ] ];

		BFMT_Migrator::run();

		$this->assertSame( '2026-02-01', $GLOBALS['bfmt_meta'][100]['_mgtrack_harvest_date'] );
	}

	public function test_a_row_the_tray_refuses_is_counted_as_failed_not_imported(): void {
		$this->db->var_answers = [ 'wp_bfmtl_grows' ];
		$this->db->rows        = [ (object) [ 'id' => 4, 'variety' => 'Kale', 'seeded_date' => '2026-02-30', 'expected_harvest_date' => '', 'tray_size' => '', 'status' => 'active' ] ];

		$this->assertSame( [ 'imported' => 0, 'skipped' => 0, 'failed' => 1 ], BFMT_Migrator::run() );
		$this->assertSame( [], bfmt_test_calls( 'wp_insert_post' ) );
	}

	/**
	 * WordPress runs MySQL without NO_ZERO_DATE, so Lite stored a blank or
	 * impossible expected date as 0000-00-00. The row still imports; only the
	 * optional date is dropped.
	 */
	public function test_a_zero_expected_date_does_not_lose_the_row(): void {
		$this->db->var_answers = [ 'wp_bfmtl_grows' ];
		$this->db->rows        = [ (object) [ 'id' => 5, 'variety' => 'Kale', 'seeded_date' => '2026-02-01', 'expected_harvest_date' => '0000-00-00', 'tray_size' => '', 'status' => 'active' ] ];

		$this->assertSame( [ 'imported' => 1, 'skipped' => 0, 'failed' => 0 ], BFMT_Migrator::run() );
		$this->assertSame( '', $GLOBALS['bfmt_meta'][100]['_mgtrack_expected_harvest_date'] );
	}

	/**
	 * Lite's updated_at changed when the status did, so it is the best record
	 * of when a tray finished — ahead of the expected date.
	 */
	public function test_a_finished_row_uses_its_last_update_as_the_finish_date(): void {
		$row = (object) [ 'seeded_date' => '2026-02-01', 'expected_harvest_date' => '2026-02-10', 'updated_at' => '2026-02-12 08:30:00' ];

		$this->assertSame( '2026-02-12', BFMT_Migrator::finished_on( $row ) );
	}

	public function test_without_an_update_time_the_expected_date_then_the_seeded_date_is_used(): void {
		$this->assertSame( '2026-02-10', BFMT_Migrator::finished_on( (object) [ 'seeded_date' => '2026-02-01', 'expected_harvest_date' => '2026-02-10', 'updated_at' => '0000-00-00 00:00:00' ] ) );
		$this->assertSame( '2026-02-01', BFMT_Migrator::finished_on( (object) [ 'seeded_date' => '2026-02-01', 'expected_harvest_date' => null ] ) );
	}

	/**
	 * Never before it was sown, never in the future (today is 2026-09-30 in the
	 * stubs).
	 */
	public function test_the_finish_date_is_kept_between_sowing_and_today(): void {
		$this->assertSame( '2026-09-30', BFMT_Migrator::finished_on( (object) [ 'seeded_date' => '2026-09-01', 'expected_harvest_date' => '2027-01-01', 'updated_at' => '' ] ) );
		$this->assertSame( '2026-09-01', BFMT_Migrator::finished_on( (object) [ 'seeded_date' => '2026-09-01', 'expected_harvest_date' => '', 'updated_at' => '2026-08-01 00:00:00' ] ) );
	}

	public function test_a_failed_row_with_an_expected_date_records_its_update_day(): void {
		$this->db->var_answers = [ 'wp_bfmtl_grows' ];
		$this->db->rows        = [ (object) [ 'id' => 6, 'variety' => 'Pea', 'seeded_date' => '2026-03-01', 'expected_harvest_date' => '2026-03-10', 'updated_at' => '2026-03-05 10:00:00', 'tray_size' => '', 'status' => 'failed' ] ];

		BFMT_Migrator::run();

		$this->assertSame( '2026-03-05', $GLOBALS['bfmt_meta'][100]['_mgtrack_failed'] );
	}

	public function test_a_seeded_date_mistyped_into_the_future_still_finishes_no_later_than_today(): void {
		$this->assertSame( '2026-09-30', BFMT_Migrator::finished_on( (object) [ 'seeded_date' => '2027-03-01', 'expected_harvest_date' => '', 'updated_at' => '' ] ) );
	}

	/**
	 * A row entered already finished has updated_at = created_at, the day it
	 * was typed in; the expected date says more about when it finished.
	 */
	public function test_an_update_on_the_creation_day_defers_to_the_expected_date(): void {
		$row = (object) [ 'seeded_date' => '2026-02-01', 'expected_harvest_date' => '2026-02-10', 'created_at' => '2026-05-01 09:00:00', 'updated_at' => '2026-05-01 09:00:00' ];

		$this->assertSame( '2026-02-10', BFMT_Migrator::finished_on( $row ) );
	}

	public function test_every_lite_field_reaches_the_tray(): void {
		$this->db->var_answers = [ 'wp_bfmtl_grows' ];
		$this->db->rows        = [ (object) [ 'id' => 8, 'variety' => 'Red Cabbage', 'seeded_date' => '2026-04-01', 'expected_harvest_date' => '2026-04-11', 'tray_size' => 'Half tray', 'status' => 'active' ] ];

		BFMT_Migrator::run();

		$this->assertSame( [ 100, 'Red Cabbage', 'mgtrack_seed', false ], bfmt_test_calls( 'wp_set_object_terms' )[0] );
		$this->assertSame( '2026-04-01', $GLOBALS['bfmt_meta'][100]['_mgtrack_seeding_date'] );
		$this->assertSame( '2026-04-11', $GLOBALS['bfmt_meta'][100]['_mgtrack_expected_harvest_date'] );
		$this->assertSame( 'Half tray', $GLOBALS['bfmt_meta'][100]['_mgtrack_tray_size'] );
	}

	/**
	 * A tray trashed or deleted after import stays imported: reactivating
	 * does not bring it back as a duplicate.
	 */
	public function test_a_row_whose_tray_was_deleted_is_not_imported_again(): void {
		$GLOBALS['bfmt_options']['bfmt_legacy_imported_ids'] = [ 1 ];
		$GLOBALS['bfmt_posts_result'] = [];
		$this->db->var_answers = [ 'wp_bfmtl_grows' ];
		$this->db->rows        = [
			(object) [ 'id' => 1, 'variety' => 'Kale', 'seeded_date' => '2026-01-02', 'expected_harvest_date' => '', 'tray_size' => '', 'status' => 'active' ],
			(object) [ 'id' => 2, 'variety' => 'Pea', 'seeded_date' => '2026-01-03', 'expected_harvest_date' => '', 'tray_size' => '', 'status' => 'active' ],
		];

		$this->assertSame( [ 'imported' => 1, 'skipped' => 1, 'failed' => 0 ], BFMT_Migrator::run() );
		$this->assertCount( 1, bfmt_test_calls( 'wp_insert_post' ) );
		$this->assertSame( [ 1, 2 ], $GLOBALS['bfmt_options']['bfmt_legacy_imported_ids'] );
	}

	public function test_a_row_that_fails_to_import_is_not_recorded_as_imported(): void {
		$GLOBALS['bfmt_options']['bfmt_legacy_imported_ids'] = [];
		$this->db->var_answers = [ 'wp_bfmtl_grows' ];
		$this->db->rows        = [ (object) [ 'id' => 4, 'variety' => 'Kale', 'seeded_date' => '0000-00-00', 'expected_harvest_date' => '', 'tray_size' => '', 'status' => 'active' ] ];

		BFMT_Migrator::run();

		$this->assertSame( [], $GLOBALS['bfmt_options']['bfmt_legacy_imported_ids'] );
	}

	/**
	 * An import cut off partway (a timeout on a long Lite history) has
	 * recorded every row it finished, so the next activation skips them.
	 */
	public function test_an_import_cut_off_partway_keeps_what_it_finished(): void {
		$GLOBALS['bfmt_options']['bfmt_legacy_imported_ids'] = [];
		$this->db->var_answers = [ 'wp_bfmtl_grows' ];
		$this->db->rows        = [
			(object) [ 'id' => 1, 'variety' => 'Kale', 'seeded_date' => '2026-01-02', 'expected_harvest_date' => '', 'tray_size' => '', 'status' => 'active' ],
			(object) [ 'id' => 2, 'variety' => 'Pea', 'seeded_date' => '2026-01-03', 'expected_harvest_date' => '', 'tray_size' => '', 'status' => 'active' ],
		];
		// Row 2's insert dies the way a timeout would.
		$GLOBALS['bfmt_throw_on_insert'] = 2;
		try {
			BFMT_Migrator::run();
			$this->fail( 'the import was expected to be cut off' );
		} catch ( RuntimeException ) {
			// Expected.
		}
		$GLOBALS['bfmt_throw_on_insert'] = 0;

		$this->assertSame( [ 1 ], $GLOBALS['bfmt_options']['bfmt_legacy_imported_ids'] );

		$this->db->var_answers = [ 'wp_bfmtl_grows' ];
		$this->assertSame( [ 'imported' => 1, 'skipped' => 1, 'failed' => 0 ], BFMT_Migrator::run() );
	}

	/**
	 * 1.1.0 kept no record. A tray it imported and the grower then deleted
	 * for good must not come back when 1.1.1 rebuilds the record.
	 */
	public function test_rebuilding_after_110_counts_deleted_trays_as_imported(): void {
		$GLOBALS['bfmt_options']['bfmt_legacy_import'] = [ 'imported' => 3, 'skipped' => 0, 'failed' => 1 ];
		$GLOBALS['bfmt_posts_result']                  = [ 30 ];
		$GLOBALS['bfmt_meta'][30]['_bfmt_legacy_grow_id'] = 3;
		$this->db->var_answers = [ 'wp_bfmtl_grows' ];
		$this->db->rows        = [
			(object) [ 'id' => 1, 'seeded_date' => '2026-01-01', 'expected_harvest_date' => null ],          // deleted by the grower
			(object) [ 'id' => 2, 'seeded_date' => '0000-00-00', 'expected_harvest_date' => null ],          // 1.1.0 refused it
			(object) [ 'id' => 3, 'seeded_date' => '2026-01-03', 'expected_harvest_date' => '2026-01-10' ],  // still has its tray
			(object) [ 'id' => 4, 'seeded_date' => '2026-01-04', 'expected_harvest_date' => '0000-00-00' ],  // 1.1.0 refused it too
		];

		$this->assertSame( [ 3, 1 ], BFMT_Migrator::imported_ids() );
		$this->assertSame( [ 'SELECT id, seeded_date, expected_harvest_date FROM %i WHERE id <= %d', [ 'wp_bfmtl_grows', 3 ] ], $this->prepared_starting( 'SELECT id, seeded_date' ) );
	}

	public function test_a_fresh_rebuild_with_no_110_import_adds_nothing_from_lite(): void {
		$GLOBALS['bfmt_posts_result'] = [];

		$this->assertSame( [], BFMT_Migrator::imported_ids() );
		$this->assertSame( [], $this->db->executed, 'Lite is not read without a 1.1.0 import' );
	}

	/**
	 * Without 1.1.0's summary there is no evidence 1.1.0 imported anything
	 * beyond the surviving trays, so Lite is not read and nothing is assumed.
	 */
	public function test_without_a_110_summary_only_surviving_trays_are_recorded(): void {
		$GLOBALS['bfmt_posts_result']                     = [ 30 ];
		$GLOBALS['bfmt_meta'][30]['_bfmt_legacy_grow_id'] = 3;
		$this->db->var_answers = [ 'wp_bfmtl_grows' ];
		$this->db->rows        = [ (object) [ 'id' => 1, 'seeded_date' => '2026-01-01', 'expected_harvest_date' => null ] ];

		$this->assertSame( [ 3 ], BFMT_Migrator::imported_ids() );
		$this->assertSame( [], $this->db->executed );
	}
}
