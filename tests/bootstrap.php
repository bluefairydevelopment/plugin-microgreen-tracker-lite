<?php
/**
 * Bootstrap for the unit suite.
 *
 * Loads the real plugin file — not the classes one by one — against recording
 * stubs, so the constants, the marker function and the hooks the plugin file
 * registers are the ones that ship. Everything that needs a real install is in
 * tests/integration-checks.php and the Playwright suite.
 */

declare(strict_types=1);

require_once __DIR__ . '/stubs.php';

// is_admin() is false while the plugin file loads, as on the front end; the
// admin classes are loaded below so they can be tested directly.
require_once dirname( __DIR__ ) . '/bluefairy-microgreen-tracker.php';

require_once dirname( __DIR__ ) . '/admin/class-tray-list-table.php';
require_once dirname( __DIR__ ) . '/admin/class-tray-admin.php';
require_once dirname( __DIR__ ) . '/admin/class-pro-upsell.php';

/**
 * What the plugin file itself did while loading, before any test reset it.
 */
$GLOBALS['bfmt_load_calls'] = $GLOBALS['bfmt_calls'];
