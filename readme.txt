=== BlueFairy Microgreen Tracker ===
Contributors: bluefairydevelopment
Tags: microgreens, farm, growing, tracker, agriculture
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.1.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A grow log for microgreen trays. Record what you planted and when it should be ready, and see at a glance what is still growing.

== Description ==

If you grow microgreens you already keep this record somewhere — a notebook, a whiteboard, a spreadsheet that only opens on one computer. This plugin keeps it in WordPress instead, where you can reach it from the phone in your pocket while you are standing in front of the rack.

= What you record =

* **Variety** — pea shoots, sunflower, radish, whatever you grow. Type the same name again (capitals don't matter) and the tray joins that variety.
* **Tray size** — 10×20, half tray, or your own words for it.
* **Seeded** — the day it went in.
* **Expected harvest** — the day you think it comes out. Optional.

= What you see =

Every tray in one list, newest first, with a coloured badge showing whether it is still growing, has been harvested, or failed. Mark a tray harvested or failed from its row, and put it back to growing if you tapped the wrong one. (With Microgreen Tracker Pro active, you record these from Pro's tray screens instead, where Pro also takes the yield or the reason.) Sort by variety or by seeded date. Twenty-five to a page, fetched a page at a time however many seasons you have logged.

= What it does not do =

It does not calculate anything, chase you with reminders, or ask you to configure it. It is a log. Adding a tray takes four fields and one button.

= Where the record lives =

Trays are ordinary WordPress records, so they are in your database backup along with everything else, they survive deactivating the plugin, and **they are not deleted when you uninstall it**. If you later add Microgreen Tracker Pro, every tray you have already logged is simply there — the two plugins share one record rather than each keeping their own.

== Microgreen Tracker Pro ==

The paid add-on installs alongside this plugin and keeps everything you have logged. It adds:

* Expected harvest worked out for you, from each variety's days to harvest
* The full grow cycle — soaking, weight on, germination, darkness lifted, harvest
* Average yield, harvested and failed counts per variety, and cost per tray
* A rack and location grid, so you know what is where
* Harvest planning that works backwards from the date you need the crop
* A task calendar, expense tracking and reports
* A Farm Worker role and front-end forms, so staff can log trays from a phone without needing wp-admin
* A daily digest email

https://wordpress.bluefairydevelopment.com/product/bluefairy-microgreen-tracker-pro/

== Installation ==

1. Upload the `bluefairy-microgreen-tracker` folder to `/wp-content/plugins/`, or install the zip through **Plugins → Add New → Upload Plugin**.
2. Activate it.
3. **Microgreens** appears in the admin menu. Add your first tray.

== Frequently Asked Questions ==

= Will I lose my trays if I deactivate or uninstall? =

No. Deactivating changes nothing, and uninstalling removes only this plugin's own settings. Your trays are your records; a plugin should not delete them because you removed it.

= Is there a limit on how many trays I can log? =

No, and the list is built to mean it: each page is a separate database query rather than the whole table loaded and cut down afterwards.

= I used Microgreen Tracker Lite 1.0.0, which kept its own table. =

Activate this plugin **before** you delete Lite. Activation brings Lite's rows across into the shared record once — activating twice does not duplicate anything — and leaves Lite's table in place. Deleting Lite first removes that table, and with it the rows this plugin would have imported. If any row cannot be imported, the Microgreens screen says so — keep Lite installed until you have dealt with it.

= Can my staff log trays without a WordPress login? =

They need an account, but not an admin one — that is what the Farm Worker role and the front-end forms in Pro are for.

= Does it need WooCommerce, or any other plugin? =

No.

== Screenshots ==

1. Microgreens — every tray, with its status at a glance.
2. Adding a tray: variety, size, seeded date, expected harvest.

== Changelog ==

= 1.1.1 =
* Added: mark a tray harvested or failed from its row in the list, and put it back to growing if you marked the wrong one. Until now only trays imported from Lite, or recorded with Pro, could show as harvested or failed. With Pro active these stay on Pro's screens, which record the yield and the reason too.
* Fixed: the plugin stopped with a fatal error on PHP 8.1, the version it says it supports.
* Fixed: "Move to Trash" chosen from the bulk-action menu below the list did nothing. Both menus work.
* Fixed: a tray with no seeded date — one started with a soak in Pro — was missing from the list. It now appears — after the dated trays in the default newest-first order.
* Fixed: trays imported from Lite as harvested or failed now record a date — the day the row was last updated in Lite — which Pro needs to show their days and cost.
* Fixed: a Lite row with an unreadable expected harvest date was not imported at all. The row imports without that date, and the Microgreens screen now says if any row could not be imported.
* Fixed: the edit form accepted a blank seeded date, though it is marked required.
* Fixed: the Microgreen Tracker Pro panel described features Pro does not have: the harvest date "from how much seed you sowed" (it uses each variety's days to harvest) and "germination rates" (Pro reports yield, harvested and failed counts, and cost per tray). The panel now also lists Pro's daily digest email.
* Fixed: the edit form promised Pro would fill in a blank expected harvest date. Pro only does that when a tray is added.
* Changed: the screen's styling and its "Move this tray to the trash?" prompt load as a stylesheet and a script on the Microgreens screen only, instead of being written into each row. Status colours an add-on supplies must be hex colours; any other value shows the neutral badge.
* Changed: translations are loaded by WordPress itself.
* Changed: the WordPress.org directory banners, icons and listing screenshots are no longer inside the plugin zip.

= 1.1.0 =
* Rebuilt as the base plugin for Microgreen Tracker Pro, which now extends it rather than keeping its own separate copy of your trays. Buying Pro used to show an empty tracker, because the two plugins stored the same thing in different places.
* Added: rows from 1.0.0's own table are brought across on activation, once, without touching the original.
* Fixed: a date the database could not accept was reported as saved. Dates are now checked before anything is written, and refused with the value quoted back — including dates like 2026-02-30, which have the right shape and are not a day.
* Fixed: the list loaded every row and sorted the whole set in PHP before showing twenty-five. Sorting and paging now happen in the query.
* Fixed: sorting by variety put "Zucchini" above "amaranth".
* Changed: deleting a tray moves it to the trash instead of destroying it.
* Changed: the screen is its own menu rather than a page buried in Settings.
* Changed: uninstalling no longer deletes your trays.

= 1.0.0 =
* First release.
