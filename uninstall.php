<?php
/**
 * Uninstall.
 *
 * Trays are NOT deleted. They are the grower's records, and they are shared
 * with the Pro add-on. Nor is the record of which Lite rows were imported
 * (bfmt_legacy_imported_ids): it belongs with the trays it describes, and
 * without it a reinstall would bring back trays the grower deleted.
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'bfmt_features' );
delete_option( 'bfmt_legacy_import' );
