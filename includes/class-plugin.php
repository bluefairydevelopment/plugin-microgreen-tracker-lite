<?php
/**
 * Core plugin bootstrap: hook registration, activation, deactivation.
 *
 * @package BlueFairy\MicrogreenTrackerLite
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

class BFMTL_Plugin {

	/**
	 * Called on plugins_loaded. Registers all runtime hooks.
	 */
	public static function init(): void {
		// Check for DB upgrades on every load (lightweight — just compares option value).
		BFMTL_DB::maybe_upgrade();

		// Load translations.
		load_plugin_textdomain( 'bluefairy-microgreen-tracker-lite', false, dirname( BFMTL_BASENAME ) . '/languages' );

		if ( is_admin() ) {
			BFMTL_Grow_Admin::init();
		}

		// Plugin action links (Plugins list screen).
		add_filter( 'plugin_action_links_' . BFMTL_BASENAME, [ __CLASS__, 'action_links' ] );
	}

	/**
	 * Add "Upgrade to Pro" link in the Plugins list table row.
	 *
	 * @param string[] $links
	 * @return string[]
	 */
	public static function action_links( array $links ): array {
		$upgrade = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer" style="color:#2563eb;font-weight:600;">%s</a>',
			esc_url( 'https://wordpress.bluefairydevelopment.com/product/bluefairy-microgreen-tracker/' ),
			esc_html__( 'Upgrade to Pro', 'bluefairy-microgreen-tracker-lite' )
		);
		array_unshift( $links, $upgrade );
		return $links;
	}

	/**
	 * Runs on plugin activation. Creates the DB table.
	 */
	public static function activate(): void {
		BFMTL_DB::install();
	}

	/**
	 * Runs on plugin deactivation. Data is NOT removed — only uninstall removes data.
	 */
	public static function deactivate(): void {
		// Intentionally empty: data is preserved on deactivation per WP best practice.
	}
}
