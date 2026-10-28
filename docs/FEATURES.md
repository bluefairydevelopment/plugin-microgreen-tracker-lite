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

The variety is a taxonomy term rather than a text field, so a name is stored
once and reused: type the same name again (capitals don't matter) and the tray
joins that variety. A different spelling is a different variety.

**Check your trays from the command line:** `wp post list --post_type=mgtrack_tray`

## Dates

A date is checked before anything is written, and refused with the value quoted
back if it cannot be read.

The check is a real calendar check, not a pattern match. `2026-02-30` has
exactly the right shape and is not a day, so it is refused rather than stored.
You are never told a tray was saved when it was not.

`03/04/2026` is refused rather than guessed at. Reading it as the 3rd of April
would silently record the wrong day for anyone whose locale writes it as the
4th of March.

## Status

Derived, never stored, so a tray cannot disagree with itself — there is no
status column to fall out of step with the milestone that actually happened.

| State | When |
|---|---|
| Harvested | a harvest date is recorded — **Mark harvested** on the tray's row records today |
| Failed | the tray is marked failed — **Mark failed** on its row |
| Active | anything else — **Mark growing** puts a finished tray back |

The three row links are hidden while Microgreen Tracker Pro is active: Pro
records a harvest with its yield and a failure with its reason, from its own
tray screens. With Pro deactivated, **Mark growing** is not offered on a tray
Pro finished (it would leave Pro's yield or reason behind), nor on one whose
rack slot another unfinished tray now uses (it would put two trays in one slot). A
tray finished here by mistake can be put back while its slot is still free.
Harvest and failure are stored as dates, which is how Pro reads them.

Harvested beats failed: a tray that was cut is not also a failure, whichever
order the two were recorded in.

Pro replaces the answer through the `bfmt_tray_status` filter with its
seven-state model — planned, soaking, seeded, germinating, growing, harvested,
failed. It supplies only the states between the two ends; harvested and failed
are decided here, once.

## The list

The Microgreens menu in wp-admin. With Microgreen Tracker Pro active, the same
screen is **All Trays** inside Pro's Microgreens menu, so there is one
Microgreens menu, not two; its address, `admin.php?page=bfmt-trays`, does not
change.

Twenty-five trays to a page, sorted and paged **in the database**, so the screen
stays fast whether you have logged thirty trays or three thousand.

Sort by variety or by seeded date, in either direction. Variety sorting ignores
case, so "amaranth" files with "Amaranth" rather than after "Zucchini".

## Deleting

A tray goes to the trash. WordPress empties the trash on its own schedule (a
site that sets `EMPTY_TRASH_DAYS` to 0 has no trash, and deletes at once). To
restore a tray, open `wp-admin/edit.php?post_type=mgtrack_tray&post_status=trash`
— there is no link to it on the Microgreens screen. Uninstalling removes only
`bfmt_features` and `bfmt_legacy_import` — never a grower's records, nor the
record of imported Lite rows (`bfmt_legacy_imported_ids`).

## Coming from Microgreen Tracker Lite 1.0.0

Lite 1.0.0 kept grows in its own `wp_bfmtl_grows` table, which Pro could not
read. **Activate this plugin before you delete Lite** — deleting Lite drops that
table. Activation imports its rows into the shared record:

- once, tracked per row, so activating twice does not duplicate anything;
- statuses become the milestone that produced them, since status is derived here;
- the old table is left in place. Nothing is dropped.

While any Lite row is not in the tracker — usually because Lite has no seeded
date for it, or it was added to Lite since — the Microgreens screen says how many. Keep Lite
installed, fix the date in Lite, then deactivate and reactivate this plugin
(with Pro active, deactivate Pro first — the Plugins screen does not offer
Deactivate for a plugin another active plugin requires).
Deleting Lite would take those rows with its table.
`wp option get bfmt_legacy_import` reports the counts: brought across, skipped
and failed.

A finished Lite row is given the day it was last changed in Lite as its
harvest or failure date, since Lite recorded no finish date of its own — unless
that was the day it was entered, when its expected date is used, else its
seeded date. Never a date in the future. Fixing a row in Lite changes it that
day, so check the finish date in Pro after importing a fixed row.

The import keeps its own record of which Lite rows it has brought across
(`bfmt_legacy_imported_ids`), written row by row, so a tray trashed or deleted
afterwards is not imported again and an import cut short repeats at most the
row it was on. Uninstalling keeps the record, as it keeps the trays. After an
update from 1.1.0, which kept no record, it is rebuilt from the trays that
remain; a Lite row whose tray was deleted under 1.1.0 is then shown as not in
the tracker rather than guessed at, since a guess could lose a row that was
never imported.

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
- No settings. Uninstalling removes two options — the import summary counts
  (`bfmt_legacy_import`) and the switches an add-on may store
  (`bfmt_features`) — and keeps every tray and the record of imported Lite
  rows (`bfmt_legacy_imported_ids`).

---

## Free and Pro

This plugin is the base of a pair. Pro is an add-on: it extends this plugin
rather than replacing it, so there is one tray record, one place your data
lives, and nothing to migrate when you upgrade.

| | Pro — $25 | Free |
|---|---|---|
| Record a tray: variety, size, seeded date | Yes | Yes |
| Expected harvest date you type | Yes | Yes |
| Tray list with status, sortable, paged | Yes | Yes |
| Mark a tray harvested or failed | Yes, from Pro's tray screens, with the yield or the reason | Yes, from the tray list |
| Undo a harvest or a failure | A failure, with **Undo failure** (Pro 1.5.0 and later); a harvest cannot be undone | Yes — **Mark growing** |
| Trays kept on uninstall | Yes | Yes |
| **Expected harvest worked out from the variety** | Yes | — |
| **Soak, weight on, germination, darkness lifted milestones** | Yes | — |
| **Seven-state grow cycle instead of three** | Yes | — |
| **Seed variety library** — days to harvest, soak, seed rate, bag price | Yes | — |
| **Average yield, harvested and failed counts per variety** | Yes | — |
| **Cost per tray, and expense tracking** | Yes | — |
| **Rack and location grid** | Yes | — |
| **Harvest planning, backwards from a delivery date** | Yes | — |
| **Task calendar** | Yes | — |
| **Reports** | Yes | — |
| **Farm Worker role** | Yes | — |
| **Front-end forms and board** — five shortcodes | Yes | — |
| **Daily digest email** | Yes | — |
| **Public availability list for customers** | Yes | — |

### Why the missing rows cost you

**You are doing the arithmetic.** This plugin stores the harvest date you type.
Every time you sow a tray you work out, in your head or on paper, when it will
be ready — from the variety, the season and how heavily you seeded. Pro keeps a
variety library with each crop's days-to-harvest range and fills that date in
for you. Get it wrong on one tray and you cut early; get it wrong on a delivery
week and you are short.

**Three states cannot describe a grow.** Active, harvested, failed is enough to
keep a log. It is not enough to run a room. Pro tracks soaking, seeded,
germinating and growing as distinct states with the milestone dates that
produced them — so you can see at a glance which trays come off blackout today
and which are overdue, instead of walking the racks to find out.

**You cannot tell which varieties pay.** Without seed weight, yield weight and
cost per tray, every crop looks equally worthwhile. Pro records what went in and
what came out, and reports average yield, harvested and failed counts and
average cost per tray by variety.
That is the number that decides what you sow next season.

**Nobody else can log a tray.** This plugin is a wp-admin screen, so anyone who
touches it needs a WordPress account with editing rights. Pro adds a Farm Worker
role and five front-end shortcodes, so staff log trays and milestones from a
phone in the grow room without needing wp-admin.

**You are remembering where everything is.** Pro's rack and location grid tracks
occupancy across shelves and slots, blocks a slot that is already full, and
plans a harvest backwards from the date you need the crop.

### Get Pro

**https://wordpress.bluefairydevelopment.com/product/bluefairy-microgreen-tracker-pro/**

One payment, no subscription, lifetime licence for one site. Pro requires this
plugin to be installed and active — it is an add-on, not a replacement, and
every tray you have already logged carries straight over.
