<?php
/**
 * An extension point for add-on feature switches.
 *
 * Nothing in this plugin is behind a switch: labels() is empty unless an
 * add-on fills it through bfmt_feature_labels, and this plugin never writes
 * bfmt_features. Pro 1.4.0 does not use it either. It stays so a future add-on
 * can offer switches without a schema change, and is_enabled() treats an
 * unknown switch as on so a switch added later cannot disable anything.
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

class BFMT_Feature_Registry {

	public const OPTION = 'bfmt_features';

	/**
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return (array) apply_filters( 'bfmt_feature_labels', [] );
	}

	/**
	 * @return array<string, string>
	 */
	public static function descriptions(): array {
		return (array) apply_filters( 'bfmt_feature_descriptions', [] );
	}

	/**
	 * An unknown key counts as on.
	 *
	 * A feature the add-on introduces after this option was last written has no
	 * stored value, and defaulting it to off would silently disable something a
	 * customer had just paid for.
	 */
	public static function is_enabled( string $key ): bool {
		$stored = (array) get_option( self::OPTION, [] );

		return array_key_exists( $key, $stored ) ? (bool) $stored[ $key ] : true;
	}

	/**
	 * Rebuild from the label list, which is what the form renders from.
	 *
	 * @param  mixed $input
	 * @return array<string, bool>
	 */
	public static function sanitize( mixed $input ): array {
		$input  = is_array( $input ) ? $input : [];
		$result = [];

		foreach ( array_keys( self::labels() ) as $key ) {
			$result[ $key ] = ! empty( $input[ $key ] );
		}

		return $result;
	}
}
