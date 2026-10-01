<?php
/**
 * Plugin Name:       BlueFairy Microgreen Tracker
 * Plugin URI:        https://wordpress.bluefairydevelopment.com/bluefairy-microgreen-tracker/
 * Description:       A grow log for microgreen trays: variety, tray size, seeded date, expected harvest, status.
 * Version:           1.1.2
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Blue Fairy Development
 * Author URI:        https://wordpress.bluefairydevelopment.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bluefairy-microgreen-tracker
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'BFMT_VERSION',  '1.1.2' );
define( 'BFMT_FILE',     __FILE__ );
define( 'BFMT_PATH',     plugin_dir_path( __FILE__ ) );
define( 'BFMT_URL',      plugin_dir_url( __FILE__ ) );
define( 'BFMT_BASENAME', plugin_basename( __FILE__ ) );

require_once BFMT_PATH . 'includes/class-tray.php';
require_once BFMT_PATH . 'includes/class-status.php';
require_once BFMT_PATH . 'includes/class-feature-registry.php';
require_once BFMT_PATH . 'includes/class-migrator.php';
require_once BFMT_PATH . 'includes/class-plugin.php';

if ( is_admin() ) {
	require_once BFMT_PATH . 'admin/class-tray-list-table.php';
	require_once BFMT_PATH . 'admin/class-tray-admin.php';
	require_once BFMT_PATH . 'admin/class-pro-upsell.php';
}

/**
 * Marker the Pro add-on uses to confirm the base plugin is present.
 */
function bfmt_plugin(): string {
	return BFMT_VERSION;
}

register_activation_hook( __FILE__, [ 'BFMT_Plugin', 'activate' ] );

add_action( 'plugins_loaded', [ 'BFMT_Plugin', 'boot' ] );
