# Changelog

All notable changes to BlueFairy Microgreen Tracker Lite are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project adheres to [Semantic Versioning](https://semver.org/).

---

## [1.0.0] — 2026-08-15

### Added
- Initial public release.
- Custom `{prefix}bfmtl_grows` table created via `dbDelta()` on activation and version-checked on `plugins_loaded`.
- Admin submenu registered under **Settings > Grow Tracker** (`manage_options` capability).
- `WP_List_Table` subclass (`BFMTL_Grow_List_Table`) with sortable columns (Variety, Seeded Date, Expected Harvest, Status), bulk delete, and colour-coded status badges.
- Add new grow form: Variety (required), Tray Size, Seeded Date (required), Expected Harvest Date, Status.
- Edit grow form: pre-populated from database row, same validation as add.
- Delete action via GET + nonce URL with confirmation dialog.
- Bulk delete via WP_List_Table bulk actions, protected by nonce.
- "Upgrade to Pro" link in the Plugins list table row.
- Quiet light-blue upgrade notice at the bottom of every Grow Tracker page.
- Transient-based notices survive POST → redirect cycles.
- `uninstall.php` drops the table and removes plugin options on plugin deletion.
- No record cap — unlimited grow records for all users.
