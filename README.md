# BlueFairy Microgreen Tracker Lite

Track microgreen grow cycles from seeding to harvest — right inside your WordPress admin.

**Version:** 1.0.0  
**Requires WordPress:** 6.4+  
**Requires PHP:** 8.1+  
**License:** GPL-2.0-or-later

---

## Description

A lightweight, no-dependency grow-cycle logger for micro-farm operators. Records live in a dedicated custom table (`{prefix}bfmtl_grows`) — no CPTs, no WooCommerce.

## Features

- Add, edit, delete grow records (variety, tray size, seeded date, expected harvest, status)
- Sortable WP_List_Table with colour-coded status badges
- Bulk delete
- Data preserved on deactivation; removed only on uninstall
- Unlimited records (no trialware cap)

## Installation

1. Copy the plugin folder to `wp-content/plugins/`.
2. Activate via **Plugins > Installed Plugins**.
3. Go to **Settings > Grow Tracker**.

## Upgrade to Pro

[BlueFairy Microgreen Tracker Pro](https://wordpress.bluefairydevelopment.com/product/bluefairy-microgreen-tracker/) adds yield tracking, CSV export, harvest planning, rack management, task calendar, reports, expense tracking, and worker shortcodes.

## File Structure

```
bluefairy-microgreen-tracker-lite.php   Plugin header + bootstrap
includes/
  class-plugin.php                      Hook registration, activation, deactivation
  class-db.php                          Table creation, CRUD helpers, version check
admin/
  class-grow-admin.php                  Admin menu, add/edit form, delete handlers
  class-grow-list-table.php             WP_List_Table extension
uninstall.php                           Drop table, delete options on plugin deletion
```

## Development Notes

- `declare(strict_types=1)` in every PHP file.
- All DB writes use `$wpdb->prepare()` or `$wpdb->insert()` / `$wpdb->update()` (which internally escape).
- All output escaped via `esc_html()`, `esc_attr()`, `esc_url()`.
- Nonces on every add, edit, delete, and bulk action.
- `current_user_can('manage_options')` checked before any data read or write.
- Constant prefix `BFMTL_` avoids collision with the Pro plugin (`BFMT_`).
- Table suffix `bfmtl_grows` is compatible with the Pro plugin's upgrade migration.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).
