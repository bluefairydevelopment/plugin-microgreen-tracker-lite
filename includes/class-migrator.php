<?php
/**
 * Brings rows from the old bfmtl_grows table into the tray post type.
 *
 * Microgreen Tracker Lite 1.0.0 kept grows in its own table, which the
 * paid add-on could not read. This brings them into the shared tray record.
 *
 * It runs on activation, keeps the old table until the grower says otherwise,
 * and never imports the same row twice.
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

class BFMT_Migrator {

	public const DONE_OPTION   = 'bfmt_legacy_import';
	public const IDS_OPTION    = 'bfmt_legacy_imported_ids';
	private const SOURCE_KEY   = '_bfmt_legacy_grow_id';
	private const LEGACY_TABLE = 'bfmtl_grows';

	public static function legacy_table(): string {
		global $wpdb;

		return $wpdb->prefix . self::LEGACY_TABLE;
	}

	public static function legacy_table_exists(): bool {
		global $wpdb;

		$table = self::legacy_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema check, not cacheable.
		return (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
	}

	/**
	 * How many Lite rows have not been brought across.
	 *
	 * Compared by id, not by count: a row deleted in Lite, or an imported tray
	 * trashed or deleted here, must not hide a row that is still waiting — nor
	 * report one that is not.
	 */
	public static function pending(): int {
		if ( ! self::legacy_table_exists() ) {
			return 0;
		}

		return count( array_diff( self::legacy_ids(), self::imported_ids() ) );
	}

	/**
	 * @return list<int> every id in Lite's table
	 */
	private static function legacy_ids(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- reads Lite's own table, which WordPress has no API for; one indexed column.
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i', self::legacy_table() ) ) );
	}

	/**
	 * The Lite row ids already imported, as recorded in IDS_OPTION.
	 *
	 * The record outlives the trays: a tray trashed, or deleted for good, is
	 * still "imported", so re-activating never brings it back as a duplicate.
	 * 1.1.0 kept no such record, only a meta key on each tray, so the first
	 * read rebuilds it from those trays — trashed ones included. A tray
	 * deleted for good under 1.1.0 left nothing to rebuild from, and its Lite
	 * row counts as waiting: guessing it was imported could instead lose a row
	 * that never was, and a tray that comes back can be trashed again.
	 *
	 * @return list<int>
	 */
	public static function imported_ids(): array {
		$stored = get_option( self::IDS_OPTION, null );

		if ( is_array( $stored ) ) {
			return array_map( 'intval', $stored );
		}

		$ids = [];

		$trays = get_posts( [
			'post_type'      => BFMT_Tray::POST_TYPE,
			'post_status'    => [ 'any', 'trash' ],
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => self::SOURCE_KEY,          // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- runs once, to build the record 1.1.0 did not keep.
			'meta_compare'   => 'EXISTS',
		] );

		foreach ( $trays as $tray_id ) {
			$ids[] = (int) get_post_meta( (int) $tray_id, self::SOURCE_KEY, true );
		}

		$ids = array_values( array_unique( array_filter( $ids ) ) );
		update_option( self::IDS_OPTION, $ids, false );

		return $ids;
	}

	public static function imported_count(): int {
		return count( self::imported_ids() );
	}

	/**
	 * Import every row not already imported.
	 *
	 * @return array{imported:int, skipped:int, failed:int}
	 */
	public static function run(): array {
		global $wpdb;

		$result = [ 'imported' => 0, 'skipped' => 0, 'failed' => 0 ];

		if ( ! self::legacy_table_exists() ) {
			return $result;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the import reads Lite's own table, on activation only.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id ASC', self::legacy_table() ) );

		$imported = self::imported_ids();

		foreach ( (array) $rows as $row ) {
			if ( in_array( (int) $row->id, $imported, true ) ) {
				++$result['skipped'];
				continue;
			}

			$seeded = (string) $row->seeded_date;

			// WordPress runs MySQL without NO_ZERO_DATE, so Lite stored a blank
			// or impossible date as 0000-00-00. The expected date is optional:
			// an unreadable one is dropped rather than failing the whole row.
			$tray_id = BFMT_Tray::create( [
				'variety'               => (string) $row->variety,
				'seeding_date'          => $seeded,
				'expected_harvest_date' => self::readable( (string) ( $row->expected_harvest_date ?? '' ) ),
				'tray_size'             => (string) $row->tray_size,
			] );

			if ( is_wp_error( $tray_id ) ) {
				++$result['failed'];
				continue;
			}

			// The old table stored a status; this model derives one, so the
			// status is expressed as the milestone that produced it.
			// Both are stored as dates, the form Pro reads them in.
			if ( 'harvested' === $row->status ) {
				update_post_meta( $tray_id, '_mgtrack_harvest_date', self::finished_on( $row ) );
			} elseif ( 'failed' === $row->status ) {
				update_post_meta( $tray_id, '_mgtrack_failed', self::finished_on( $row ) );
			}

			update_post_meta( $tray_id, self::SOURCE_KEY, (int) $row->id );
			$imported[] = (int) $row->id;
			++$result['imported'];

			// Recorded per row, not once at the end: an import cut off by a
			// timeout must not import these rows again on the next activation.
			update_option( self::IDS_OPTION, $imported, false );
		}

		update_option( self::DONE_OPTION, $result, false );

		return $result;
	}

	/**
	 * The date if it is a real one, otherwise ''.
	 */
	private static function readable( string $date ): string {
		return BFMT_Tray::is_date( $date ) ? $date : '';
	}

	/**
	 * The day a finished Lite row finished, as best the row can say.
	 *
	 * Lite recorded no finish date. Its updated_at changed when the row was
	 * last edited — usually the status change — so it is the closest record,
	 * unless it is the day the row was created (a row entered already
	 * finished), when the expected date says more. Then the seeded date. The
	 * answer is never before sowing and never in the future; if the seeded
	 * date itself was mistyped into the future, it is today.
	 */
	public static function finished_on( object $row ): string {
		$seeded  = (string) $row->seeded_date;
		$today   = current_time( 'Y-m-d' );
		$updated = substr( (string) ( $row->updated_at ?? '' ), 0, 10 );
		$created = substr( (string) ( $row->created_at ?? '' ), 0, 10 );

		$candidates = [
			$updated !== $created ? $updated : '',
			(string) ( $row->expected_harvest_date ?? '' ),
		];

		$date = $seeded;

		foreach ( $candidates as $candidate ) {
			if ( BFMT_Tray::is_date( $candidate ) ) {
				$date = $candidate;
				break;
			}
		}

		// Plain string comparison is date order for Y-m-d. Today is applied
		// last, so it wins even over a seeded date in the future.
		return min( max( $seeded, $date ), $today );
	}
}
