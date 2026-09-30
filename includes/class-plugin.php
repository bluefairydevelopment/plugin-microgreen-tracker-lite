<?php
/**
 * Base plugin bootstrap.
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

class BFMT_Plugin {

	/**
	 * Boot.
	 *
	 * bfmt_loaded fires BEFORE any of this plugin's own subsystems register.
	 * The Pro add-on hooks it to declare its tray fields, its feature switches
	 * and its status model, and all three have to be in place before the
	 * registry, the settings screen or the list table read them.
	 */
	public static function boot(): void {
		BFMT_Tray::init();

		do_action( 'bfmt_loaded' );

		if ( is_admin() ) {
			BFMT_Tray_Admin::init();
			BFMT_Pro_Upsell::init();
		}
	}

	/**
	 * Activation: bring any old table across, then get out of the way.
	 *
	 * The import is idempotent and leaves the old table alone, so activating
	 * twice does not duplicate a grower's records and deactivating loses
	 * nothing.
	 */
	public static function activate(): void {
		BFMT_Tray::register();

		if ( BFMT_Migrator::legacy_table_exists() ) {
			BFMT_Migrator::run();
		}
	}
}
