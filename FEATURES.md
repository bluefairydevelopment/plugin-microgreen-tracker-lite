# BlueFairy Microgreen Tracker — features

Every statement here is a claim about the shipped code. If one stops being
true, the code is wrong or this file is.

## The tray record

One record, `mgtrack_tray`, owned by this plugin. Pro extends the same record
rather than keeping its own, which is why installing Pro shows you every tray
you have already logged.

| Field | Where it lives |
|---|---|
| Variety | a term in the `mgtrack_seed` taxonomy |
| Tray size | `_mgtrack_tray_size` |
| Seeded | `_mgtrack_seeding_date` |
| Expected harvest | `_mgtrack_expected_harvest_date` |

The variety is a taxonomy term rather than a text field, so the names build up
as you use them and a typo does not become a second variety.

**Verify:** `wp post list --post_type=mgtrack_tray`

## Dates

A date is checked before anything is written, and refused with the value quoted
back if it cannot be read.

The check is `checkdate()`, not a pattern match. `2026-02-30` has exactly the
right shape and is not a day; the previous version accepted it, passed it to a
`DATE NOT NULL` column, and told the grower the record had been added while the
database was rejecting it.

`03/14/2026` is refused rather than guessed at. Reading it as the 3rd of
February would silently record the wrong day for anyone whose locale writes
dates the other way round.

**Verify:** the eight cases in `tests/Unit/TrayDateTest.php`.

## Status

Derived, never stored, so a tray cannot disagree with itself — there is no
status column to fall out of step with the milestone that actually happened.

| State | When |
|---|---|
| Harvested | a harvest date is recorded |
| Failed | the tray is marked failed |
| Active | anything else |

Harvested beats failed: a tray that was cut is not also a failure, whichever
order the two were recorded in.

Pro replaces the answer through the `bfmt_tray_status` filter with its
seven-state model — planned, soaking, seeded, germinating, growing, harvested,
failed. It supplies only the states between the two ends; harvested and failed
are decided here, once.

## The list

Twenty-five trays to a page, ordered and paged **in the database**. The previous
version fetched every row, sorted the whole set in PHP with `usort` and then
sliced twenty-five out of it — beneath a readme advertising no record cap.

Sorting by variety orders by the record title, which the database does
case-insensitively. `strcmp` put "Zucchini" above "amaranth".

**Verify:** `tests/integration-checks.php` seeds thirty trays and asserts that
one page returns twenty-five of them, with the total still reported so
pagination works.

## Deleting

A tray goes to the trash. Nothing in this plugin destroys one outright, and
uninstalling removes only `bfmt_features` and `bfmt_legacy_import` — never a
grower's records.

## Coming from 1.0.0

Version 1.0.0 kept grows in its own `wp_bfmtl_grows` table, which Pro could not
read. Activating 1.1.0 imports those rows into the shared record:

- once, tracked per row, so activating twice does not duplicate anything;
- statuses become the milestone that produced them, since status is derived here;
- the old table is left in place. Nothing is dropped.

**Verify:** `wp option get bfmt_legacy_import`

## Extension points

| Hook | Purpose |
|---|---|
| `bfmt_loaded` | fires before this plugin registers its own subsystems; where Pro attaches |
| `BFMT_Tray::register_field()` | add a field to the tray record |
| `bfmt_tray_status` | replace the derived status |
| `bfmt_status_labels`, `bfmt_status_colours` | name and colour new states |
| `bfmt_tray_created`, `bfmt_tray_updated` | react to a change |
| `bfmt_tray_form_fields` | add fields to the add/edit form |
| `bfmt_after_tray_list` | add content below the list |
| `bfmt_admin_menu` | add pages under the Microgreens menu |

## What this plugin does not do

- No calculations. It records what you type; deriving a harvest date is the
  first thing Pro adds.
- No email, no cron, no scheduled work.
- No database tables of its own.
- Two options, both removed on uninstall.
