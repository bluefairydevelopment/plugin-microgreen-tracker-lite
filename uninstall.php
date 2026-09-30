<?php
/**
 * Uninstall.
 *
 * Trays are NOT deleted. They are the grower's records, and they are shared
 * with the Pro add-on. Only this plugin's two options are removed.
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'bfmt_features' );
delete_option( 'bfmt_legacy_import' );
