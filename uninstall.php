<?php
/**
 * Uninstall script — runs when the plugin is deleted from the Plugins screen.
 *
 * Removes the bfmtl_grows table and all plugin options.
 * Data is NOT removed on simple deactivation.
 *
 * @package BlueFairy\MicrogreenTrackerLite
 */

declare(strict_types=1);

// WordPress calls this file directly; bail if accessed any other way.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-db.php';

BFMTL_DB::uninstall();

// Remove any remaining plugin options not handled by BFMTL_DB::uninstall().
delete_option( 'bfmtl_db_version' );
