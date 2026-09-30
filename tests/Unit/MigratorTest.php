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

	public function test_pending_counts_through_an_identifier_placeholder(): void {
		$this->db->var_answers = [ 'wp_bfmtl_grows', '12' ];

		$this->assertSame( 12, BFMT_Migrator::pending() );
		$this->assertSame( [ 'SELECT COUNT(*) FROM %i', [ 'wp_bfmtl_grows' ] ], $this->prepared_starting( 'SELECT COUNT' ) );
	}

	public function test_pending_subtracts_what_was_already_imported(): void {
		$this->db->var_answers        = [ 'wp_bfmtl_grows', '5' ];
		$GLOBALS['bfmt_posts_result'] = [ 11, 12 ];

		$this->assertSame( 3, BFMT_Migrator::pending() );
	}

	public function test_pending_is_never_negative(): void {
		$this->db->var_answers        = [ 'wp_bfmtl_grows', '5' ];
		$GLOBALS['bfmt_posts_result'] = range( 1, 7 );

		$this->assertSame( 0, BFMT_Migrator::pending() );
	}

	public function test_pending_is_zero_with_no_legacy_table(): void {
		$this->db->var_answers = [ null ];

		$this->assertSame( 0, BFMT_Migrator::pending() );
		$this->assertCount( 1, $this->db->executed, 'only the existence check ran' );
	}

	public function test_imported_count_asks_for_trays_carrying_the_legacy_id(): void {
		$GLOBALS['bfmt_posts_result'] = [ 1, 2, 3 ];

		$this->assertSame( 3, BFMT_Migrator::imported_count() );

		$args = bfmt_test_calls( 'get_posts' )[0][0];
		$this->assertSame( 'mgtrack_tray', $args['post_type'] );
		$this->assertSame( '_bfmt_legacy_grow_id', $args['meta_key'] );
		$this->assertSame( 'EXISTS', $args['meta_compare'] );
		$this->assertSame( 'any', $args['post_status'] );
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
		$this->db->var_answers = [ 'wp_bfmtl_grows', '0', 'wp_bfmtl_grows' ];

		BFMT_Migrator::pending();
		BFMT_Migrator::run();

		// pending(): existence check + count; run(): existence check + select.
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
		$GLOBALS['bfmt_posts_result'] = [ 99 ];

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
		$this->assertSame( $result, $GLOBALS['bfmt_options']['bfmt_legacy_import'] );

		[ $kale, $pea, $radish ] = [ 100, 101, 102 ];

		$this->assertSame( '2026-01-10', $GLOBALS['bfmt_meta'][ $kale ]['_mgtrack_harvest_date'] );
		$this->assertArrayNotHasKey( '_mgtrack_failed', $GLOBALS['bfmt_meta'][ $kale ] );
		// A date, as Pro stores it: the expected date, else the seeded date.
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
}
