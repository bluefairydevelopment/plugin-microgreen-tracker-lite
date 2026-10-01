# Changelog

## [1.1.2] — 2026-09-30

### Changed
- With Microgreen Tracker Pro active, the tray list registers as a submenu of
  Pro's `mgtrack` menu, as **All Trays** at position 3. It no longer adds a
  second top-level Microgreens menu (WPMAP-99). The page slug, `bfmt-trays`,
  and so its address, are unchanged. `register_menu()` now runs on
  `admin_menu` at priority 20, after Pro's top-level page exists. The
  stylesheet and script enqueue compares against the hook suffix WordPress
  actually returned, which is `toplevel_page_bfmt-trays` on its own and
  `microgreens_page_bfmt-trays` under Pro, instead of a fixed string.

### Fixed
- docs/FEATURES.md and docs/COMPARISON.md said a failure cannot be undone in
  Pro. Pro 1.5.0 adds Undo failure.

## [1.1.1] — 2026-09-30

### Added
- **Mark harvested**, **Mark failed** and **Mark growing** row actions. They
  write `_mgtrack_harvest_date` and `_mgtrack_failed` as dates, the form Pro
  reads, each behind a nonce keyed to the action and the tray. A repeat is
  success without moving the recorded date; a trashed tray is refused. Until
  now nothing in the free plugin could finish a tray.
- The status links are hidden, and refused if requested, while Pro is active:
  Pro records a harvest with its yield and sale, a failure with its reason,
  and frees rack slots, and a one-click change here would skip all of that.
  With Pro off, Mark growing is not offered (`BFMT_Status::reopen_blocker()`)
  on a tray Pro finished — a `_by` stamp, yield, sale or failure reason — or
  on one whose rack slot another unfinished tray now uses. A tray finished here
  by mistake can be put back while its slot is free.
- Every row link carries an `aria-label` naming its tray.

### Fixed
- Fatal error on PHP 8.1: `BFMT_Tray::update()` and `validate()` declared a
  `true` return type, which needs PHP 8.2. They now declare `bool|WP_Error`.
- The bulk-action menu below the list posts as `action2`; only `action` was
  read, so "Move to Trash" from the bottom menu did nothing.
- The list's default sort inner-joined `_mgtrack_seeding_date`, dropping any
  tray without one (a Pro tray started with a soak). It now orders by a
  NOT EXISTS clause keyed to the seeded date, so those trays appear, after the
  dated ones in the default order.
- The Lite 1.0.0 importer stored a failure as `'1'`; Pro reads
  `_mgtrack_failed` as a date. Harvests and failures now take the row's
  `updated_at` date unless it is the creation day, else its expected date,
  else its seeded date; never before the seeded date and never after today.
- The importer decided "already imported" from trays carrying
  `_bfmt_legacy_grow_id`, which cannot see trashed or deleted trays, so a
  trashed tray was imported again. It now keeps the imported Lite ids in
  `bfmt_legacy_imported_ids`, written after each row so an interrupted import
  does not repeat, kept on uninstall, and counts rows still waiting by id, not
  by count. On the first read after an update from 1.1.0 the record is
  rebuilt from existing trays (trash included). A Lite row whose tray was
  deleted for good under 1.1.0 is reported as not in the tracker, not guessed
  to be imported: a wrong guess would lose a row silently, while a tray that
  comes back can be trashed again.
- A Lite row whose optional expected date was `0000-00-00` (MySQL under
  WordPress runs without NO_ZERO_DATE) failed to import; the date is now
  dropped and the row imported. While any Lite row is still waiting, the
  screen says how many and how to bring them across, read live from Lite's
  table, since the FAQ tells growers to delete Lite.
- `BFMT_Tray::update()` could blank a tray's seeded date. A tray that never
  had one (a Pro soak-started tray) can still be edited without one.
- The Pro panel claimed a harvest date "from how much seed you sowed" and
  "germination rates"; Pro uses each variety's days to harvest and reports
  yield, harvested and failed counts and cost per tray. It now lists the daily
  digest too, and names the Farm Worker role.
- The edit form promised Pro would fill a blank expected harvest date; Pro only
  does that on add.
- A second `bulk-trays` nonce field was printed alongside WP_List_Table's own.

### Changed
- Status badge colours, the Pro panel's list styling and the trash prompt load
  as an enqueued stylesheet and script on the Microgreens screen only, instead
  of style and onclick attributes in the markup. Colours an add-on supplies
  through `bfmt_status_colours` must be two hex colours; anything else falls
  back to the neutral badge.
- Translations are loaded by WordPress itself; the manual
  `load_plugin_textdomain()` call is gone.
- The 1.0.0 importer passes its table name through `$wpdb->prepare()` as `%i`,
  and escapes it for `SHOW TABLES LIKE`.
- `LICENSE` is the full, unmodified GPLv2 text.
- The WordPress.org banners, icons and listing screenshots moved out of the
  zip to `wp-org-assets/`. `FEATURES.md` moved to `docs/`.

## [1.1.0] — 2026-08-31

Rebuilt as the base plugin for Microgreen Tracker Pro. The two plugins stored
the same trays in different places and neither knew the other existed.

### Fixed

- A date the database could not accept was reported as saved. The required-field
  check ran first, the format check then blanked the value, and the rejected
  insert was reported as a success. Dates are validated with `checkdate()` before
  anything is written and refused with the value quoted back — so `2026-02-30`,
  which has the right shape and is not a day, no longer gets through.
- The list loaded every row, sorted the whole set in PHP and sliced twenty-five
  out of it, beneath a readme advertising no record cap. Ordering and paging
  happen in the query.
- Sorting by variety used `strcmp`, putting "Zucchini" above "amaranth".

### Added

- One shared tray record: `mgtrack_tray`, the `mgtrack_seed` taxonomy, and a
  field registry the Pro add-on contributes to rather than duplicating.
- A derived three-state status, and the `bfmt_tray_status` filter Pro replaces
  it at with its seven-state model.
- A one-time import of 1.0.0's `wp_bfmtl_grows` table, tracked per row, leaving
  the original in place.
- Twenty unit tests and an integration check suite.

### Changed

- Deleting a tray moves it to the trash.
- The screen is a top-level menu rather than a Settings sub-page.
- Uninstalling no longer deletes trays.

## [1.0.0]

First release.
