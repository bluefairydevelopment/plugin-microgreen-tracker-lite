# BlueFairy Microgreen Tracker

A grow log for microgreen trays. This is the free base plugin;
[Microgreen Tracker Pro](https://wordpress.bluefairydevelopment.com/product/bluefairy-microgreen-tracker-pro/)
is an add-on that extends it and holds no copy of its work.

## Architecture

```
bluefairy-microgreen-tracker.php   constants, requires, bfmt_plugin() marker
includes/class-tray.php            the tray record: post type, taxonomy, fields, CRUD
includes/class-status.php          the derived status, and the filter Pro replaces it at
includes/class-feature-registry.php an extension point for add-on switches (unused by Pro 1.4.0)
includes/class-migrator.php        1.0.0's table, imported once and left in place
admin/                             the list, the form, the Pro panel
assets/                            the Microgreens screen's stylesheet and script (enqueued there only)
wp-org-assets/                     WordPress.org banners, icons, screenshots (never in the zip)
```

`BFMT_Tray` is the only place that knows where a tray's data lives. Pro calls
`register_field()` and answers `bfmt_tray_status`; it does not register the post
type, the taxonomy, or any meta key.

## The names are inherited, on purpose

`mgtrack_tray`, `mgtrack_seed` and every `_mgtrack_*` key come from the paid
plugin's 1.3.3 model, because a real farm has trays stored under exactly those
names. They are not re-prefixed to match the rest of the estate.

The seeded date is `_mgtrack_seeding_date`. The retired free table called the
same field `seeded_date`, and assuming the two matched cost an hour and a
rewritten class.

## Development

```
composer install
vendor/bin/phpunit                                          # 52 unit tests
wp eval-file .../tests/integration-checks.php               # from the site root
```

The unit tests have been checked against deliberate mutations: dropping
`checkdate()`, reversing the harvested/failed precedence, and removing the
status filter all turn the suite red. So do, since 1.1.1: putting back the old
Pro-panel wording, a style attribute on a badge or the panel list, an onclick on
the trash link, a manual `load_plugin_textdomain()`, an interpolated table name
in the importer, a colour check that accepts anything, and enqueueing the
screen's assets on every admin page.

## Licence

GPL-2.0-or-later.
