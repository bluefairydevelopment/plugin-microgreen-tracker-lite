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

	public static function pending(): int {
		global $wpdb;

		if ( ! self::legacy_table_exists() ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a one-time import count, not cacheable.
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', self::legacy_table() ) );

		return max( 0, $total - self::imported_count() );
	}

	public static function imported_count(): int {
		return count( get_posts( [
			'post_type'      => BFMT_Tray::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => self::SOURCE_KEY,          // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- counting what has already been imported, once.
			'meta_compare'   => 'EXISTS',
		] ) );
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a one-time import, not cacheable.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id ASC', self::legacy_table() ) );

		foreach ( (array) $rows as $row ) {
			if ( self::already_imported( (int) $row->id ) ) {
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
			++$result['imported'];
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
	 * last edited — usually the status change — so it is the closest record;
	 * the expected date is the fallback, then the seeded date. The answer is
	 * kept between the seeded date and today, so an import never records a
	 * tray finishing before it was sown or in the future.
	 */
	public static function finished_on( object $row ): string {
		$seeded = (string) $row->seeded_date;
		$today  = current_time( 'Y-m-d' );

		$candidates = [
			substr( (string) ( $row->updated_at ?? '' ), 0, 10 ),
			(string) ( $row->expected_harvest_date ?? '' ),
		];

		$date = $seeded;

		foreach ( $candidates as $candidate ) {
			if ( BFMT_Tray::is_date( $candidate ) ) {
				$date = $candidate;
				break;
			}
		}

		// Plain string comparison is date order for Y-m-d.
		return max( $seeded, min( $date, $today ) );
	}

	private static function already_imported( int $legacy_id ): bool {
		return (bool) get_posts( [
			'post_type'      => BFMT_Tray::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => self::SOURCE_KEY,   // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- keyed lookup by design; this is the idempotency check.
			'meta_value'     => $legacy_id,         // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- see above.
		] );
	}
}
