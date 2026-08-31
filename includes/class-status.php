<?php
/**
 * A tray's status, derived rather than stored.
 *
 * The free plugin knows three states. Pro replaces the answer through the
 * bfmt_tray_status filter with its seven-state milestone model — it does not
 * re-implement the harvested and failed checks, which live here once.
 *
 * Status has never been a stored value in this data model. Deriving it means a
 * tray cannot disagree with itself: there is no status column to fall out of
 * step with the milestone that actually happened.
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

class BFMT_Status {

	public const ACTIVE    = 'active';
	public const HARVESTED = 'harvested';
	public const FAILED    = 'failed';

	/**
	 * Meta keys that decide the free plugin's three states.
	 *
	 * These are 1.3.3's keys, so a farm upgrading from the paid plugin alone
	 * keeps every status it already had.
	 */
	private const HARVEST_KEY = '_mgtrack_harvest_date';
	private const FAILED_KEY  = '_mgtrack_failed';

	public static function get( int $tray_id ): string {
		$status = self::ACTIVE;

		if ( get_post_meta( $tray_id, self::HARVEST_KEY, true ) ) {
			$status = self::HARVESTED;
		} elseif ( get_post_meta( $tray_id, self::FAILED_KEY, true ) ) {
			$status = self::FAILED;
		}

		/**
		 * Filter a tray's status.
		 *
		 * Pro returns one of its seven milestone states here. Anything it
		 * returns is treated as the status; label() falls back to the raw value
		 * for a state this plugin does not know about.
		 *
		 * @param string $status
		 * @param int    $tray_id
		 */
		return (string) apply_filters( 'bfmt_tray_status', $status, $tray_id );
	}

	/**
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return (array) apply_filters( 'bfmt_status_labels', [
			self::ACTIVE    => __( 'Active', 'bluefairy-microgreen-tracker' ),
			self::HARVESTED => __( 'Harvested', 'bluefairy-microgreen-tracker' ),
			self::FAILED    => __( 'Failed', 'bluefairy-microgreen-tracker' ),
		] );
	}

	public static function label( string $status ): string {
		return self::labels()[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) );
	}

	/**
	 * @return array<string, string> status => "colour;background:colour"
	 */
	public static function colours(): array {
		return (array) apply_filters( 'bfmt_status_colours', [
			self::ACTIVE    => '#166534;background:#dcfce7',
			self::HARVESTED => '#1e40af;background:#dbeafe',
			self::FAILED    => '#991b1b;background:#fee2e2',
		] );
	}

	public static function badge( string $status ): string {
		$colour = self::colours()[ $status ] ?? '#374151;background:#f3f4f6';

		return sprintf(
			'<span style="%s">%s</span>',
			esc_attr( 'color:' . $colour . ';padding:2px 8px;border-radius:3px;font-size:.8em;font-weight:600;display:inline-block;' ),
			esc_html( self::label( $status ) )
		);
	}
}
