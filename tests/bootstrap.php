<?php
/**
 * Bootstrap for the pure-unit suite.
 *
 * Date validation and the status derivation are the two pieces of logic that
 * decide whether a grower's record is accepted and what it says, and both can
 * be exercised without WordPress. The admin classes are loaded too, against
 * recording stubs, so what they print and what they enqueue can be asserted
 * on. Everything that needs a real install is in tests/integration-checks.php
 * and the Playwright suite.
 */

declare(strict_types=1);

require_once __DIR__ . '/stubs.php';
require_once __DIR__ . '/../includes/class-tray.php';
require_once __DIR__ . '/../includes/class-status.php';
require_once __DIR__ . '/../includes/class-feature-registry.php';
require_once __DIR__ . '/../includes/class-migrator.php';
require_once __DIR__ . '/../includes/class-plugin.php';
require_once __DIR__ . '/../admin/class-tray-list-table.php';
require_once __DIR__ . '/../admin/class-tray-admin.php';
require_once __DIR__ . '/../admin/class-pro-upsell.php';
