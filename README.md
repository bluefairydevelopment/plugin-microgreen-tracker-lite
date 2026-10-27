# BlueFairy Microgreen Tracker

A grow log for microgreen trays. This is the free base plugin;
[Microgreen Tracker Pro](https://wordpress.bluefairydevelopment.com/product/bluefairy-microgreen-tracker-pro/)
is an add-on that extends it and holds no copy of its data.

## Architecture

```
bluefairy-microgreen-tracker.php    constants, requires, bfmt_plugin() marker
includes/class-tray.php             the tray record: post type, taxonomy, fields, CRUD
includes/class-status.php           the derived status, the filter an add-on replaces it at,
                                    and the harvested / failed / growing actions
includes/class-feature-registry.php an extension point for add-on switches
includes/class-migrator.php         imports Microgreen Tracker Lite 1.0.0's table once
admin/                              the list, the form, the Pro panel
assets/                             the Microgreens screen's stylesheet and script
```

`BFMT_Tray` is the only place that knows where a tray's data lives. An add-on
calls `register_field()` and answers `bfmt_tray_status`; it does not register
the post type, the taxonomy, or any meta key.

## Stored names

`mgtrack_tray`, `mgtrack_seed` and the `_mgtrack_*` meta keys are the storage
schema of existing installs of the Pro add-on. They keep that prefix so those
trays are readable the moment this plugin is activated.

A harvest and a failure are stored as dates (`_mgtrack_harvest_date`,
`_mgtrack_failed`), which is the form Pro reads them in.

## Development

The unit suite and its stubs live in the source repository, not in the
distributed plugin.

```
composer install
vendor/bin/phpunit
```

## Licence

GPL-2.0-or-later. See `LICENSE`.

## Support the free plugin

This plugin is free and always will be. If it saves you time, you can [buy us a coffee on Ko-fi](https://ko-fi.com/bluefairydevelopment) ☕ — it helps keep the free plugins tested and maintained.

Found a bug or have an idea? [Open an issue](../../issues/new/choose). Security problems: see [SECURITY.md](SECURITY.md).
