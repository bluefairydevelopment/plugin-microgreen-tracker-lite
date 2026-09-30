# Changelog

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
- The 1.0.0 importer stored a failure as `'1'`; Pro reads `_mgtrack_failed` as
  a date. It now stores the row's expected date, else its seeded date.
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
