<?php
/**
 * PHPStan bootstrap: the main plugin file's path constants.
 *
 * bluefairy-microgreen-tracker.php defines BFMT_PATH, BFMT_URL and BFMT_BASENAME from
 * plugin_dir_path() / plugin_dir_url() / plugin_basename(), which PHPStan cannot
 * evaluate when it scans the file, so it reports them as not found. Declared
 * here with their runtime types only. Never loaded by WordPress or the unit suite.
 *
 * @package BlueFairy
 */

// phpcs:ignoreFile -- static-analysis bootstrap, not shipped behaviour.

define( 'BFMT_PATH', '/' );
define( 'BFMT_URL', 'https://example.invalid/' );
define( 'BFMT_BASENAME', 'bluefairy-microgreen-tracker/bluefairy-microgreen-tracker.php' );
