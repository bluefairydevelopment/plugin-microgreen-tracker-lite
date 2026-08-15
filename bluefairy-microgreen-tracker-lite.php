<?php
/**
 * Plugin Name:       BlueFairy Microgreen Tracker Lite
 * Plugin URI:        https://wordpress.bluefairydevelopment.com/product/bluefairy-microgreen-tracker/
 * Description:       Track microgreen grow cycles from seeding to harvest. Log variety, tray size, seeded date, expected harvest, and status — all from your WordPress admin.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            BlueFairy Development
 * Author URI:        https://wordpress.bluefairydevelopment.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bluefairy-microgreen-tracker-lite
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'BFMTL_VERSION',   '1.0.0' );
define( 'BFMTL_PATH',      plugin_dir_path( __FILE__ ) );
define( 'BFMTL_URL',       plugin_dir_url( __FILE__ ) );
define( 'BFMTL_BASENAME',  plugin_basename( __FILE__ ) );

require_once BFMTL_PATH . 'includes/class-db.php';
require_once BFMTL_PATH . 'includes/class-plugin.php';

if ( is_admin() ) {
	require_once BFMTL_PATH . 'admin/class-grow-list-table.php';
	require_once BFMTL_PATH . 'admin/class-grow-admin.php';
}

register_activation_hook( __FILE__,   [ 'BFMTL_Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'BFMTL_Plugin', 'deactivate' ] );

add_action( 'plugins_loaded', [ 'BFMTL_Plugin', 'init' ] );
