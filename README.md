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

## Requirements

- PHP 8.2 or newer
- Filament 5
- MySQL 5.7+, MariaDB 10.2+, PostgreSQL, or SQLite (the schema uses a native `json` column)

## Installation

```bash
composer require aristonis/filament-shortcut-keys
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="filament-shortcut-keys-migrations"
php artisan migrate
```

Register the plugin on your panel:

```php
use Aristonis\FilamentShortcutKeys\FilamentShortcutKeysPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(FilamentShortcutKeysPlugin::make());
}
```

If you use a custom Filament theme, add the plugin's views to your theme's css file so its classes
survive Tailwind's purge:

```css
@source '../../../../vendor/aristonis/filament-shortcut-keys/resources/**/*.blade.php';
```

The config file is optional:

```bash
php artisan vendor:publish --tag="filament-shortcut-keys-config"
```

## The shortcuts

Keys are matched on the physical key (`event.code`), so they behave the same on any keyboard layout.
Nothing fires while an input, textarea, select, or contenteditable element has focus.

| Set | Keys | What it covers |
| --- | --- | --- |
| Navigation | `Alt` + `Shift` + letter | Every registered resource and page |
| Actions | `Alt` + letter | The current page's header actions |
| Table | bare keys | `/` search, `↑`/`↓` move the row cursor, `Space` select, `Enter` edit, `Delete` delete, `←`/`→` paginate |
| Row actions | bare letters | Custom record actions you register (see below) |
| Custom | `Alt` + `Shift` + letter | Bindings an admin adds themselves |

`Shift` + `/` opens a cheatsheet of everything active on the current page. A full listing of the
panel-wide shortcuts also lives on its own page in the sidebar.

Letters are picked from each item's own label first, then from the rest of the alphabet. Two
shortcuts in the same modifier group never share a letter, so moving one set onto another set's
modifiers via the `modifiers` config merges their letter pools rather than creating a clash.

### Row actions

Table row actions are per-row, so the plugin only binds the ones you name. Register them once and
they work on any table that exposes an action of that name, firing against the row the cursor is on:

```php
FilamentShortcutKeysPlugin::make()
    ->rowActions(['approve', 'reject'])
```

`edit` and `delete` are already bound to `Enter` and `Delete`, so leave those out.

## Letting admins customize

With `customization` set to `personal` (the default) each admin gets a "Manage shortcuts" page where
they can remap a key, disable one, add a binding of their own, or switch between the presets you
publish. The first edit copies the shared map into a private one, so later changes to the shared map
still reach everyone who has not customized that particular key.

Set `customization` to `locked` to turn all of that off. Everyone then uses the panel's system map.

A custom binding points at either a route in your app or a CSS selector on the page:

```php
['route' => '/admin/orders']         // navigates
['selector' => '#refresh-button']    // clicks
```

## Editing the shared map

Editing the map everyone else sees is gated separately from personal customization, and denied by
default. Grant it with a closure or a Gate ability:

```php
// config/shortcut-keys.php
'authorize' => fn (?Authenticatable $user, string $panelId) => $user?->isDeveloper() ?? false,

// or the name of an ability you defined
'authorize' => 'manage-shortcut-keys',
```

Whoever passes the gate can create a preset, clone an existing one, or edit a preset in place. An
in-place edit changes the map for every admin using it, so the form requires an explicit
confirmation checkbox before it will save.

## Configuration

```php
return [
    // 'personal' lets each admin fork and remap their own keys; 'locked' uses one shared map.
    'customization' => 'personal',

    // The modifier scheme per set. Tokens: ctrl, alt, shift, meta. An empty list means bare keys.
    // Drop a set from this list to put it back on its built-in scheme; an unknown token throws.
    'modifiers' => [
        'navigation' => ['alt', 'shift'],
        'global' => ['alt'],
        'table' => [],
        'row-action' => [],
        'custom' => ['alt', 'shift'],
    ],

    // Who may edit a panel's shared system maps. null denies everyone.
    'authorize' => null,

    // Force or drop a shortcut from code, keyed by target identity.
    'overlay' => [
        'navigation:App\Filament\Resources\ProductResource' => ['letter' => 'r'],
        'global:export' => ['disabled' => true],
    ],

    // Seconds to cache the panel-wide keymap. null caches until an input changes.
    'cache' => ['ttl' => null],
];
```

An `overlay` entry wins over both the conventions and an admin's own edit, and needs no database
rows, which makes it the right tool for a key you need to pin in code.

## Pruning dead overrides

A stored override survives a rename, because it is keyed on the resource class rather than its slug.
It does not survive a deletion: remove a resource and the rows that remapped it are left pointing at
nothing. Clear them out with:

```bash
php artisan shortcut-keys:prune

php artisan shortcut-keys:prune --dry-run       # list them without deleting
php artisan shortcut-keys:prune --panel=admin   # audit one panel
php artisan shortcut-keys:prune --force         # skip the production confirmation
```

It prints what it found before deleting anything, and asks for confirmation when the environment is
production.

The command only judges targets it can enumerate outside a request: navigation entries, the row
actions you registered, and the fixed table behaviors. Page actions and custom bindings are reported
and left alone, since a maintenance pass cannot tell a removed action from one that simply is not on
the page.

## Translations

The package ships English and Arabic and renders correctly right to left. In an RTL panel the
pagination arrows are mirrored to match Filament's own flipped buttons, so `←` moves forward.

Publish the files to change the wording:

```bash
php artisan vendor:publish --tag="filament-shortcut-keys-translations"
```

## Security notes

**The keymap is public.** It is built from every registered resource and page, without a `canAccess`
check, so all admins receive the same list of shortcuts regardless of permission. Pressing a key
navigates or clicks, and your own policies and middleware decide what happens next. That is
deliberate: it keeps the map cacheable and identical for everyone. If the mere existence of a route
is sensitive in your app, do not rely on the shortcut layer to hide it.

**Selector bindings click, they do not authorize.** A custom binding with a `selector` payload
performs a click on the page. Anything reachable that way must enforce its own server-side
authorization, exactly as it would for a mouse click.

**Owner identity comes from the authenticated user.** The plugin's own pages derive the map owner
from `Filament::auth()->user()`. If you call the application use-cases yourself, keep doing the same.
Passing an owner type or id from a request parameter would let one admin read and rewrite another
admin's map.

## Notes and limitations

- Register a morph map (`Relation::enforceMorphMap([...])`) if you might rename your user model. Map
  ownership is stored as the model's morph class and compared as a string.
- Letters are assigned in sidebar order, so inserting a resource can shift the letter of a later item
  that had lost a clash. Pin anything you want to stay fixed with a config `overlay` entry.
- Shortcuts inside a closed action dropdown are not reachable.
- Single combos only. There are no multi-key sequences or macros.

## Testing

```bash
composer test
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
