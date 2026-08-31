# Changelog

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
