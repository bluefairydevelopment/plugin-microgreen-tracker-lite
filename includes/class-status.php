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
	 * Whether a status action may touch this tray: a tray, and not in the trash.
	 *
	 * A nonce'd link from before the tray was trashed stays valid for a day;
	 * it must not quietly change a record nobody can see.
	 */
	private static function actionable( int $tray_id ): bool {
		return BFMT_Tray::is_tray( $tray_id ) && 'trash' !== get_post_status( $tray_id );
	}

	/**
	 * Record that a tray was cut, on $date.
	 *
	 * Writes the milestone Pro records — the harvest DATE under the key Pro
	 * reads — so a tray harvested here reads as harvested there. A harvest
	 * clears a failure: harvested beats failed in get() anyway, and a leftover
	 * flag would resurface if the harvest were undone.
	 *
	 * Already harvested is success without a write: a second click, or a stale
	 * tab, must not move the recorded harvest date.
	 */
	public static function mark_harvested( int $tray_id, string $date ): bool {
		if ( ! self::actionable( $tray_id ) || ! BFMT_Tray::is_date( $date ) ) {
			return false;
		}

		if ( get_post_meta( $tray_id, self::HARVEST_KEY, true ) ) {
			return true;
		}

		delete_post_meta( $tray_id, self::FAILED_KEY );

		return false !== update_post_meta( $tray_id, self::HARVEST_KEY, $date );
	}

	/**
	 * Record that a tray failed, on $date.
	 *
	 * Pro stores the failure as the date it happened and works a tray's days
	 * and cost out from it, so this stores a date too, never a bare flag.
	 * Refused on a harvested tray: it was cut. Already failed is success
	 * without a write.
	 */
	public static function mark_failed( int $tray_id, string $date ): bool {
		if ( ! self::actionable( $tray_id ) || ! BFMT_Tray::is_date( $date ) || get_post_meta( $tray_id, self::HARVEST_KEY, true ) ) {
			return false;
		}

		if ( get_post_meta( $tray_id, self::FAILED_KEY, true ) ) {
			return true;
		}

		return false !== update_post_meta( $tray_id, self::FAILED_KEY, $date );
	}

	/**
	 * Put a harvested or failed tray back to growing — the undo for both.
	 */
	public static function reopen( int $tray_id ): bool {
		if ( ! self::actionable( $tray_id ) ) {
			return false;
		}

		delete_post_meta( $tray_id, self::HARVEST_KEY );
		delete_post_meta( $tray_id, self::FAILED_KEY );

		return true;
	}

	/**
	 * Whether Pro has recorded more about this tray than a status.
	 *
	 * A rack slot, a harvest with its yield or sale, a failure with its reason,
	 * or a milestone Pro stamped with who recorded it. Reopening such a tray
	 * here would leave those behind — an old yield in the reports, or two
	 * trays in one slot — so it is not offered even with Pro deactivated.
	 *
	 * Pro writes a reason key even when the reason is empty, so the check is
	 * whether the key exists, not whether it has a value.
	 */
	public static function has_pro_record( int $tray_id ): bool {
		foreach ( [ '_mgtrack_location', '_mgtrack_yield_weight', '_mgtrack_sale_price', '_mgtrack_failed_reason', '_mgtrack_harvest_date_by', '_mgtrack_failed_by' ] as $key ) {
			if ( metadata_exists( 'post', $tray_id, $key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a tray has reached either end of its cycle.
	 */
	public static function is_finished( int $tray_id ): bool {
		return (bool) get_post_meta( $tray_id, self::HARVEST_KEY, true )
			|| (bool) get_post_meta( $tray_id, self::FAILED_KEY, true );
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
	 * Text and background colour for each status.
	 *
	 * The value is "text;background:background", the shape Pro 1.4.0 already
	 * returns through the filter. Changing it would break an add-on already on
	 * customers' sites, so the shape stays and stylesheet() parses it.
	 *
	 * @return array<string, string> status => "colour;background:colour"
	 */
	public static function colours(): array {
		return (array) apply_filters( 'bfmt_status_colours', [
			self::ACTIVE    => '#166534;background:#dcfce7',
			self::HARVESTED => '#1e40af;background:#dbeafe',
			self::FAILED    => '#991b1b;background:#fee2e2',
		] );
	}

	/**
	 * The badge for one status.
	 *
	 * Colour comes from the admin stylesheet, not a style attribute: the class
	 * names a rule stylesheet() writes for every status colours() knows,
	 * including the ones an add-on contributes.
	 */
	public static function badge( string $status ): string {
		return sprintf(
			'<span class="bfmt-badge %s">%s</span>',
			esc_attr( self::badge_class( $status ) ),
			esc_html( self::label( $status ) )
		);
	}

	/**
	 * The modifier class for a status.
	 *
	 * Status keys come from a filter, so anything outside a-z, 0-9, _ and - is
	 * dropped rather than trusted to be a valid class name.
	 */
	public static function badge_class( string $status ): string {
		return 'bfmt-badge--' . preg_replace( '/[^a-z0-9_-]/', '', strtolower( $status ) );
	}

	/**
	 * One CSS rule per status, for wp_add_inline_style().
	 *
	 * The colours arrive through a filter and end up inside a <style> element,
	 * so each one has to be a hex colour and nothing else. An entry that does
	 * not parse is left out and that status falls back to the neutral colour in
	 * the stylesheet, rather than letting a stray "}" or "</style>" through.
	 */
	public static function stylesheet(): string {
		$rules = [];

		foreach ( self::colours() as $status => $value ) {
			$parsed = self::parse_colour( (string) $value );

			if ( null === $parsed ) {
				continue;
			}

			$rules[] = sprintf(
				'.bfmt-badge.%s{color:%s;background:%s}',
				self::badge_class( (string) $status ),
				$parsed['color'],
				$parsed['background']
			);
		}

		return implode( "\n", $rules );
	}

	/**
	 * Split "text;background:background" into two checked hex colours.
	 *
	 * @return array{color:string, background:string}|null
	 */
	public static function parse_colour( string $value ): ?array {
		if ( ! preg_match( '/^\s*(#[0-9a-f]{3}(?:[0-9a-f]{3})?)\s*;\s*background\s*:\s*(#[0-9a-f]{3}(?:[0-9a-f]{3})?)\s*;?\s*$/iD', $value, $m ) ) {
			return null;
		}

		return [ 'color' => strtolower( $m[1] ), 'background' => strtolower( $m[2] ) ];
	}
}
