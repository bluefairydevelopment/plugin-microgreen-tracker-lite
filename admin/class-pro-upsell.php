<?php
/**
 * The one link to the paid add-on.
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

class BFMT_Pro_Upsell {

	private const PRODUCT_URL = 'https://wordpress.bluefairydevelopment.com/product/bluefairy-microgreen-tracker-pro/';

	public static function init(): void {
		add_filter( 'plugin_action_links_' . BFMT_BASENAME, [ __CLASS__, 'action_links' ] );
	}

	/**
	 * @param  string[] $links
	 * @return string[]
	 */
	public static function action_links( array $links ): array {
		if ( self::pro_is_active() ) {
			return $links;
		}

		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( self::PRODUCT_URL ),
			esc_html__( 'Get Microgreen Tracker Pro', 'bluefairy-microgreen-tracker' )
		);

		return $links;
	}

	public static function pro_is_active(): bool {
		return function_exists( 'bfmtp_plugin' );
	}

	/**
	 * The panel under the tray list.
	 *
	 * Every line is a feature the add-on ships today.
	 */
	public static function render_panel(): void {
		if ( self::pro_is_active() ) {
			return;
		}

		$features = [
			__( 'Expected harvest worked out for you, from each variety\'s days to harvest', 'bluefairy-microgreen-tracker' ),
			__( 'The full grow cycle: soaking, weight on, germination, darkness lifted, harvest', 'bluefairy-microgreen-tracker' ),
			__( 'Average yield, harvested and failed counts per variety, and cost per tray', 'bluefairy-microgreen-tracker' ),
			__( 'A rack and location grid, so you know what is where', 'bluefairy-microgreen-tracker' ),
			__( 'Harvest planning that works backwards from the date you need the crop', 'bluefairy-microgreen-tracker' ),
			__( 'A task calendar, expense tracking and reports', 'bluefairy-microgreen-tracker' ),
			__( 'A Farm Worker role and front-end forms, so staff can log trays from a phone without needing wp-admin', 'bluefairy-microgreen-tracker' ),
			__( 'A daily digest email', 'bluefairy-microgreen-tracker' ),
		];

		echo '<hr><h2>' . esc_html__( 'Microgreen Tracker Pro', 'bluefairy-microgreen-tracker' ) . '</h2>';
		echo '<p>' . esc_html__( 'The add-on installs alongside this plugin and keeps every tray you have already logged.', 'bluefairy-microgreen-tracker' ) . '</p>';
		echo '<ul class="bfmt-pro-features">';

		foreach ( $features as $feature ) {
			echo '<li>' . esc_html( $feature ) . '</li>';
		}

		echo '</ul>';
		printf(
			'<p><a class="button button-primary" href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
			esc_url( self::PRODUCT_URL ),
			esc_html__( 'Get Microgreen Tracker Pro', 'bluefairy-microgreen-tracker' )
		);
	}
}
