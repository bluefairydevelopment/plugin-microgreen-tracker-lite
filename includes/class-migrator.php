<?php
/**
 * Brings rows from the old bfmtl_grows table into the tray post type.
 *
 * 1.0.0 of the free plugin kept grows in its own table while the paid plugin
 * kept them as posts, and neither knew the other existed — so buying the paid
 * plugin showed an empty tracker, and then deleting the free one dropped the
 * table. This is the one-way door out of that.
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

			$tray_id = BFMT_Tray::create( [
				'variety'               => (string) $row->variety,
				'seeding_date'          => (string) $row->seeded_date,
				'expected_harvest_date' => (string) ( $row->expected_harvest_date ?? '' ),
				'tray_size'             => (string) $row->tray_size,
			] );

			if ( is_wp_error( $tray_id ) ) {
				++$result['failed'];
				continue;
			}

			// The old table stored a status; this model derives one, so the
			// status is expressed as the milestone that produced it.
			if ( 'harvested' === $row->status ) {
				update_post_meta( $tray_id, '_mgtrack_harvest_date', (string) ( $row->expected_harvest_date ?: $row->seeded_date ) );
			} elseif ( 'failed' === $row->status ) {
				update_post_meta( $tray_id, '_mgtrack_failed', '1' );
			}

			update_post_meta( $tray_id, self::SOURCE_KEY, (int) $row->id );
			++$result['imported'];
		}

		update_option( self::DONE_OPTION, $result, false );

		return $result;
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
