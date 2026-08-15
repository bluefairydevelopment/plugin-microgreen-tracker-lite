=== BlueFairy Microgreen Tracker Lite ===
Contributors:      bluefairydevelopment
Tags:              microgreens, farm management, grow log, plant tracker, gardening
Requires at least: 6.4
Tested up to:      7.0
Requires PHP:      8.1
Stable tag:        1.0.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Track microgreen grow cycles from seeding to harvest — right inside your WordPress admin.

== Description ==

BlueFairy Microgreen Tracker Lite gives micro-farm growers a simple, clean log to track every tray from seeding to harvest. Add a record with the variety name, tray size, seeded date, expected harvest date, and current status. Update status as trays move from active to harvested or failed.

**Features include:**

* Custom database table — no post-type bloat, no WooCommerce dependency.
* Full add / edit / delete workflow with bulk delete support.
* Sortable list table with colour-coded status badges (Active, Harvested, Failed).
* Data is preserved on deactivation and only removed when you explicitly uninstall the plugin.
* No record cap — log as many grow cycles as you need.

**Want more?** [Upgrade to BlueFairy Microgreen Tracker Pro](https://wordpress.bluefairydevelopment.com/product/bluefairy-microgreen-tracker/) for yield and germination tracking, per-grow notes, CSV export, rack/location grid, harvest planning, task calendar, reports, expense tracking, and worker shortcodes.

== Installation ==

1. Upload the `bluefairy-microgreen-tracker-lite` folder to the `/wp-content/plugins/` directory, or install via **Plugins > Add New**.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **Settings > Grow Tracker** to start logging your grows.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

No. The Lite plugin is completely standalone and has no WooCommerce dependency.

= Is my data deleted when I deactivate the plugin? =

No. Your grow records are preserved when you deactivate the plugin. Data is only deleted when you choose **Delete** from the Plugins screen (which triggers the uninstall routine).

= What does the Pro version add? =

The Pro version adds:

* Yield and germination tracking per tray
* Per-grow notes
* CSV export of all grow records
* Rack/location grid to visualise where each tray is sitting
* Harvest planning calendar
* Task calendar and reminders
* Reports and analytics (total yield, germination rate, best varieties)
* Expense tracker (seed costs, supplies)
* Worker shortcodes to expose public-facing dashboards for farm staff

[Learn more about BlueFairy Microgreen Tracker Pro →](https://wordpress.bluefairydevelopment.com/product/bluefairy-microgreen-tracker/)

= Can I import Lite data into the Pro plugin if I upgrade? =

Yes. Both plugins share the same database table prefix convention (`bfmtl_grows`) so a Pro upgrade migration can read Lite records directly.

= Is this plugin compatible with multisite? =

The plugin creates its database table in the current site's table prefix, making it compatible with multisite installations where each sub-site has its own set of tables.

== Changelog ==

= 1.0.0 — 2026-08-15 =
* Initial release.
* Custom `{prefix}bfmtl_grows` table created via `dbDelta()`.
* Admin menu under Settings > Grow Tracker.
* WP_List_Table list view with sortable columns and bulk delete.
* Add/edit form with nonce and capability enforcement.
* Delete action (GET + nonce URL).
* Upgrade to Pro action link and inline notice.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
