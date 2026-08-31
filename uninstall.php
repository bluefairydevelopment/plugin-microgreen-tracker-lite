<?php
/**
 * Uninstall.
 *
 * Trays are NOT deleted. They are the grower's records, they are shared with
 * the Pro add-on, and 1.0.0's uninstall dropped its table outright — which is
 * how a season could vanish when someone tidied up after upgrading.
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'bfmt_features' );
delete_option( 'bfmt_legacy_import' );
