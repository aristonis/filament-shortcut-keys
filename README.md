# Filament shortcut keys

[![Latest Version on Packagist](https://img.shields.io/packagist/v/aristonis/filament-shortcut-keys.svg?style=flat-square)](https://packagist.org/packages/aristonis/filament-shortcut-keys)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/aristonis/filament-shortcut-keys/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/aristonis/filament-shortcut-keys/actions?query=workflow%3Atests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/aristonis/filament-shortcut-keys/fix-code-style.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/aristonis/filament-shortcut-keys/actions?query=workflow%3Afix-code-style+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/aristonis/filament-shortcut-keys.svg?style=flat-square)](https://packagist.org/packages/aristonis/filament-shortcut-keys)

Keyboard shortcuts for a Filament panel, assigned automatically. The plugin walks the panel's
registered resources, pages, and actions, gives each one a letter, and injects the resulting keymap
into every page. There is no per-resource wiring: add a resource and it gets a shortcut.

Admins can remap or disable their own keys, and a developer you trust can edit the shared map that
everyone else starts from.

> Status: **1.0.** Filament 5 on Laravel 12 and 13, PHP 8.2 or newer. Filament 4 is not supported.

## Install

```bash
composer require aristonis/filament-shortcut-keys
php artisan vendor:publish --tag="filament-shortcut-keys-migrations"
php artisan migrate
```

Register the plugin on your panel:

```php
use Aristonis\FilamentShortcutKeys\FilamentShortcutKeysPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugin(FilamentShortcutKeysPlugin::make());
}
```

The client script is published by `filament:assets`. An app set up with `filament:install` already
runs it on every `composer install` or `update` through `filament:upgrade`; otherwise run
`php artisan filament:assets` yourself. See the **[Getting started guide](https://aristonis.org/open-source/filament-shortcut-keys/docs)**.

## Documentation — two audiences

- **For developers → [documentation site](https://aristonis.org/open-source/filament-shortcut-keys/docs)** — human
  guides: getting started, the shortcuts, row actions, customization, custom bindings, configuration,
  pruning, translations, security, troubleshooting, limitations and the design notes.
- **For AI agents → [Laravel Boost](https://laravel.com/docs/boost)** — the package ships Boost guidelines and
  skills in [`resources/boost/`](resources/boost/). Pick the package when the interactive
  `php artisan boost:install` asks for third-party packages, or add it to `boost.json` under `packages`
  and run `boost:install` again; your coding agent then loads the guideline and the
  `filament-shortcut-keys-*` skills.

## What it does

- **Shortcuts with no wiring.** Every registered resource and page gets a `G`-then-letter navigation
  key, header actions get bare letters, and every table gets the same fixed keys: `/` search, `J`/`K`
  row cursor, `Space` select, `Enter` edit, `Delete` delete, arrows to move and page.
- **Keys that stay out of the browser's way.** Nothing is held down by default, keys match the physical
  key on any layout, and nothing fires while you type or while a modal, menu or select has the keyboard.
- **Safe defaults.** A header action is keyed only when a stray press is harmless (it opens a page, a
  modal or a confirmation); hidden and disabled actions get no key.
- **Row actions you name** (`->rowActions(['approve', 'reject'])`) get one stable letter across the panel,
  including relation manager and table widget tables.
- **Real controls, real permissions.** A shortcut clicks the Filament control or follows the route you
  would have used, so authorization and confirmations stay Filament's.
- **Customization.** `personal` mode lets each admin remap, disable and add their own bindings; `locked`
  gives everyone one shared map. A trusted admin, behind a gate you supply, edits the shared presets.
- **Discoverability and accessibility.** A `?` cheatsheet, a reference page, key badges with
  `aria-keyshortcuts`, and a switch that turns single-key shortcuts off per browser.
- **Maintenance.** `shortcut-keys:prune` removes stored overrides whose target no longer exists.

## Testing

```bash
composer test   # PHP suite on SQLite; FSK_DB_DRIVER=pgsql|mysql runs it on a real driver
npm test        # browser client, on node alone
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Mekad Alibrahem](https://github.com/aristonis)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
