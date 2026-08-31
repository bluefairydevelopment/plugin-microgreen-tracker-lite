# BlueFairy Microgreen Tracker

A grow log for microgreen trays. This is the free base plugin;
[Microgreen Tracker Pro](https://wordpress.bluefairydevelopment.com/product/bluefairy-microgreen-tracker-pro/)
is an add-on that extends it and holds no copy of its work.

## Architecture

```
bluefairy-microgreen-tracker.php   constants, requires, bfmt_plugin() marker
includes/class-tray.php            the tray record: post type, taxonomy, fields, CRUD
includes/class-status.php          the derived status, and the filter Pro replaces it at
includes/class-feature-registry.php the switches Pro adds its own to
includes/class-migrator.php        1.0.0's table, imported once and left in place
admin/                             the list, the form, the Pro panel
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
vendor/bin/phpunit                                          # 20 unit tests
wp eval-file .../tests/integration-checks.php               # from the site root
```

The unit tests have been checked against deliberate mutations: dropping
`checkdate()`, reversing the harvested/failed precedence, and removing the
status filter all turn the suite red.

## Licence

GPL-2.0-or-later.
