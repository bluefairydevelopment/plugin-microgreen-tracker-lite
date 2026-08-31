=== BlueFairy Microgreen Tracker ===
Contributors: bluefairydevelopment
Tags: microgreens, farm, growing, tracker, agriculture
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A grow log for microgreen trays. Record what you planted and when it should be ready, and see at a glance what is still growing.

== Description ==

If you grow microgreens you already keep this record somewhere — a notebook, a whiteboard, a spreadsheet that only opens on one computer. This plugin keeps it in WordPress instead, where you can reach it from the phone in your pocket while you are standing in front of the rack.

= What you record =

* **Variety** — pea shoots, sunflower, radish, whatever you grow. Varieties build up as you use them, so the second tray of sunflower picks the name you already typed.
* **Tray size** — 10×20, half tray, or your own words for it.
* **Seeded** — the day it went in.
* **Expected harvest** — the day you think it comes out. Optional.

= What you see =

Every tray in one list, newest first, with a coloured badge showing whether it is still going, has been cut, or failed. Sort by variety or by seeded date. Twenty-five to a page, fetched a page at a time however many seasons you have logged.

= What it does not do =

It does not calculate anything, chase you with reminders, or ask you to configure it. It is a log. Adding a tray takes four fields and one button.

= Where the record lives =

Trays are ordinary WordPress records, so they are in your database backup along with everything else, they survive deactivating the plugin, and **they are not deleted when you uninstall it**. If you later add Microgreen Tracker Pro, every tray you have already logged is simply there — the two plugins share one record rather than each keeping their own.

== Microgreen Tracker Pro ==

The paid add-on installs alongside this plugin and keeps everything you have logged. It adds:

* Expected harvest worked out for you, from the variety and how long it takes
* The full grow cycle — soaking, weight on, darkness lifted, germination, harvest
* Yield and germination rates per variety, and cost per tray
* A rack and location grid, so you know what is where
* Harvest planning that works backwards from the date you need the crop
* A task calendar, expense tracking and reports
* A Farm Worker role and front-end forms, so staff log trays without seeing wp-admin
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

= I used version 1.0.0, which kept its own table. =

Activating 1.1.0 brings those rows across into the shared record automatically. It runs once, it does not duplicate anything if you activate twice, and it leaves the old table alone so nothing is lost either way.

= Can my staff log trays without a WordPress login? =

They need an account, but not an admin one — that is what the Farm Worker role and the front-end forms in Pro are for.

= Does it need WooCommerce, or any other plugin? =

No.

== Screenshots ==

1. Microgreens — every tray, with its status at a glance.
2. Adding a tray: variety, size, seeded date, expected harvest.

== Changelog ==

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
