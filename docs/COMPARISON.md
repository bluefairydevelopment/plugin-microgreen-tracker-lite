# Microgreen Tracker: free and Pro

The source of truth for every comparison table on the storefront, in the
plugins, and in the guides. Change the code, change this file, then rebuild
everything downstream.

| | Free | Pro |
|---|---|---|
| Record a tray: variety, size, seeded date | yes | yes, inherited |
| Expected harvest date you type | yes | yes, inherited |
| Tray list with status, sortable, paged | yes | yes, inherited |
| Mark a tray harvested or failed | yes, from the tray list | yes, from Pro's tray screens (with yield or reason) |
| Undo a harvest or a failure | yes (Mark growing) | yes: a harvest by clearing its date, a failure with Undo failure (Pro 1.5.0 and later) |
| Trays kept on uninstall | yes | yes, inherited |
| **Expected harvest worked out from the variety** | — | **yes** |
| **Soak, weight on, germination, darkness lifted milestones** | — | **yes** |
| **Seven-state grow cycle instead of three** | — | **yes** |
| **Seed variety library** (days to harvest, soak, seed rate, bag price) | — | **yes** |
| **Average yield, harvested and failed counts per variety** | — | **yes** |
| **Cost per tray, and expense tracking** | — | **yes** |
| **Rack and location grid** | — | **yes** |
| **Harvest planning, backwards from a delivery date** | — | **yes** |
| **Task calendar** | — | **yes** |
| **Reports** | — | **yes** |
| **Farm Worker role** | — | **yes** |
| **Front-end forms and board** (5 shortcodes) | — | **yes** |
| **Daily digest email** | — | **yes** |
| **Public availability list for customers** | — | **yes** |

## How the two fit together

One tray record, owned by the free plugin. Pro registers its milestone fields
through `BFMT_Tray::register_field()` and answers the `bfmt_tray_status` filter;
it does not register the post type, the taxonomy, or any meta key of its own
accord. There is exactly one definition of where a tray's data lives.

Pro declares `Requires Plugins: bluefairy-microgreen-tracker` and boots on
`bfmt_loaded`, which the base fires before registering anything of its own.

## Names that must not change

`mgtrack_tray`, `mgtrack_seed`, `mgtrack_plan`, `mgtrack_expense`, and every
`_mgtrack_*` meta key come from the paid plugin's 1.3.3 model. A real farm has
trays stored under exactly those names. They are not renamed to match the
estate's `bluefairy_` convention, and that is deliberate.

Note in particular that the seeded date is `_mgtrack_seeding_date`. The free
plugin's retired table called the same thing `seeded_date`, and assuming the
two matched was worth an hour.

## Things that must never be claimed again

- **"No record cap"** — advertising the absence of a limit invites the reader to
  imagine one, and the list it appeared beside loaded every row into PHP before
  showing twenty-five of them.
- That the free plugin calculates anything. It records what you type. The
  calculation is the first thing Pro adds.
- That trays are removed on uninstall. They are not, and were not meant to be.

## Pricing

Free plugin: $0, wordpress.org.
Pro: $125 once, lifetime licence, one site. Requires the free plugin.
