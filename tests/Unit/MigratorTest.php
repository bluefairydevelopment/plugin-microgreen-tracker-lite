<?php
/**
 * The one-time import of 1.0.0's table.
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
 * Records every prepare() and query, and answers from canned values.
 */
final class BFMT_Test_Wpdb {
	public string $prefix = 'wp_';
	/** @var list<array{string, array}> */
	public array $prepared = [];
	/** @var list<string> */
	public array $queries = [];
	public mixed $var_answers = [];
	public array $rows = [];

	public function prepare( string $query, ...$args ): string {
		$this->prepared[] = [ $query, $args ];

		return $query . ' /* ' . implode( ',', $args ) . ' */';
	}

	public function get_var( string $query ): mixed {
		$this->queries[] = $query;

		return array_shift( $this->var_answers );
	}

	public function get_results( string $query ): array {
		$this->queries[] = $query;

		return $this->rows;
	}
}

final class MigratorTest extends TestCase {

	private BFMT_Test_Wpdb $db;

	protected function setUp(): void {
		$this->db                     = new BFMT_Test_Wpdb();
		$GLOBALS['wpdb']              = $this->db;
		$GLOBALS['bfmt_options']      = [];
		$GLOBALS['bfmt_posts_result'] = [];
	}

	public function test_the_table_name_is_prefixed(): void {
		$this->assertSame( 'wp_bfmtl_grows', BFMT_Migrator::legacy_table() );
	}

	public function test_pending_counts_through_an_identifier_placeholder(): void {
		$this->db->var_answers = [ 'wp_bfmtl_grows', '12' ];

		$this->assertSame( 12, BFMT_Migrator::pending() );
		$this->assertSame( [ 'SELECT COUNT(*) FROM %i', [ 'wp_bfmtl_grows' ] ], $this->db->prepared[1] );
	}

	public function test_run_reads_through_an_identifier_placeholder(): void {
		$this->db->var_answers = [ 'wp_bfmtl_grows' ];

		BFMT_Migrator::run();

		$this->assertSame( [ 'SELECT * FROM %i ORDER BY id ASC', [ 'wp_bfmtl_grows' ] ], $this->db->prepared[1] );
	}

	public function test_no_query_interpolates_the_table_name(): void {
		$this->db->var_answers = [ 'wp_bfmtl_grows', '0', 'wp_bfmtl_grows' ];

		BFMT_Migrator::pending();
		BFMT_Migrator::run();

		foreach ( $this->db->prepared as [ $query ] ) {
			$this->assertStringNotContainsString( 'bfmtl_grows', $query );
		}
	}

	public function test_with_no_legacy_table_nothing_is_read(): void {
		$this->db->var_answers = [ null ];

		$this->assertSame( [ 'imported' => 0, 'skipped' => 0, 'failed' => 0 ], BFMT_Migrator::run() );
		$this->assertCount( 1, $this->db->queries, 'only the SHOW TABLES check ran' );
	}

	/**
	 * Idempotency: a row already carrying its legacy id is skipped, and the
	 * result is recorded.
	 */
	public function test_a_row_already_imported_is_skipped(): void {
		$this->db->var_answers        = [ 'wp_bfmtl_grows' ];
		$this->db->rows               = [ (object) [ 'id' => 3 ] ];
		$GLOBALS['bfmt_posts_result'] = [ 99 ];

		$result = BFMT_Migrator::run();

		$this->assertSame( [ 'imported' => 0, 'skipped' => 1, 'failed' => 0 ], $result );
		$this->assertSame( $result, $GLOBALS['bfmt_options']['bfmt_legacy_import'] );
	}
}
