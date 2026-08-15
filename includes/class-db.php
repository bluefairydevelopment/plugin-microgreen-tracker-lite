<?php
/**
 * Database table management for BlueFairy Microgreen Tracker Lite.
 *
 * @package BlueFairy\MicrogreenTrackerLite
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

class BFMTL_DB {

	const DB_VERSION     = '1.0.0';
	const DB_VERSION_KEY = 'bfmtl_db_version';
	const TABLE_SUFFIX   = 'bfmtl_grows';

	/**
	 * Return the full table name including the wpdb prefix.
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Create or upgrade the table. Safe to call on every activation
	 * and on plugins_loaded when the stored version differs.
	 */
	public static function install(): void {
		global $wpdb;

		$table      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			variety VARCHAR(100) NOT NULL,
			tray_size VARCHAR(50) NOT NULL DEFAULT '',
			seeded_date DATE NOT NULL,
			expected_harvest_date DATE DEFAULT NULL,
			status ENUM('active','harvested','failed') NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY status (status),
			KEY seeded_date (seeded_date)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_KEY, self::DB_VERSION, false );
	}

	/**
	 * Check whether a schema upgrade is needed and run it.
	 * Called on plugins_loaded.
	 */
	public static function maybe_upgrade(): void {
		$installed = get_option( self::DB_VERSION_KEY, '' );
		if ( $installed !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Drop the table and remove the version option.
	 * Called only from uninstall.php.
	 */
	public static function uninstall(): void {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is safe (built from prefix + constant).
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		delete_option( self::DB_VERSION_KEY );
	}

	// -------------------------------------------------------------------------
	// CRUD helpers
	// -------------------------------------------------------------------------

	/**
	 * Return all grow records, newest seeded date first.
	 *
	 * @return array<int, object>
	 */
	public static function get_all(): array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY seeded_date DESC, id DESC" ) ?: [];
	}

	/**
	 * Return a single grow record by ID, or null if not found.
	 */
	public static function get_by_id( int $id ): ?object {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
		return $row ?: null;
	}

	/**
	 * Insert a new grow record.
	 *
	 * @param array<string, mixed> $data
	 * @return int Inserted row ID, or 0 on failure.
	 */
	public static function insert( array $data ): int {
		global $wpdb;
		$result = $wpdb->insert(
			self::table_name(),
			self::sanitize_row( $data ),
			self::formats( $data )
		);
		return $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Update an existing grow record.
	 *
	 * @param int                  $id
	 * @param array<string, mixed> $data
	 * @return bool
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;
		$result = $wpdb->update(
			self::table_name(),
			self::sanitize_row( $data ),
			[ 'id' => $id ],
			self::formats( $data ),
			[ '%d' ]
		);
		return $result !== false;
	}

	/**
	 * Delete a grow record by ID.
	 */
	public static function delete( int $id ): bool {
		global $wpdb;
		$result = $wpdb->delete(
			self::table_name(),
			[ 'id' => $id ],
			[ '%d' ]
		);
		return $result !== false;
	}

	/**
	 * Delete multiple rows by their IDs.
	 *
	 * @param int[] $ids
	 */
	public static function delete_bulk( array $ids ): int {
		if ( empty( $ids ) ) {
			return 0;
		}
		global $wpdb;
		$table       = self::table_name();
		$ids         = array_map( 'absint', $ids );
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids ) );
		return (int) $result;
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Strip unknown keys and sanitize values for a DB write.
	 *
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private static function sanitize_row( array $data ): array {
		$allowed = [ 'variety', 'tray_size', 'seeded_date', 'expected_harvest_date', 'status' ];
		$clean   = [];
		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			$clean[ $key ] = $data[ $key ];
		}
		return $clean;
	}

	/**
	 * Return wpdb format specifiers for the supplied data array.
	 *
	 * @param array<string, mixed> $data
	 * @return string[]
	 */
	private static function formats( array $data ): array {
		$map = [
			'variety'               => '%s',
			'tray_size'             => '%s',
			'seeded_date'           => '%s',
			'expected_harvest_date' => '%s',
			'status'                => '%s',
		];
		$formats = [];
		foreach ( array_keys( $data ) as $key ) {
			$formats[] = $map[ $key ] ?? '%s';
		}
		return $formats;
	}
}
