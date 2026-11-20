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
- Laravel 12 or 13
- Filament 5.x (Filament 4 is not supported)
- MySQL 5.7+, MariaDB 10.2+, PostgreSQL, or SQLite (the schema uses a native `json` column). CI runs
  the suite on SQLite, MySQL 8 and PostgreSQL 16; older versions and MariaDB are not tested.

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

Publish the plugin's script, as with any Filament plugin that ships one:

```bash
php artisan filament:assets
```

Skipping this is the most common reason nothing happens when you press a key. The keymap still renders
into the page, so there is no error to read — the script that listens for the keys is simply missing.

An app set up with `filament:install` already runs `@php artisan filament:upgrade` from Composer's
`post-autoload-dump` hook, and that republishes every plugin's assets after each `composer install` or
`update`. Run `filament:assets` by hand only if your `composer.json` lacks that hook, or after
rebuilding the package's script on a local path install.

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
No bare key or `G` sequence fires while an input, textarea, select, or contenteditable element has
focus; shortcuts on held modifiers, if you configure any, still do.

| Set | Keys | What it covers |
| --- | --- | --- |
| Navigation | `G`, then a letter | Every registered resource and page |
| Actions | bare letter | The current page's header actions |
| Table | bare keys | The page's own table, its relation managers' and its table widgets' (see below). `/` search, `J`/`K` start and move the row cursor, `Esc` releases it, `Space` select, `Enter` edit, `Delete` delete; while the cursor is live `↑`/`↓` move it and `←`/`→` paginate |
| Row actions | bare letters | Custom record actions you register (see below); never `J` or `K` while they share the table's modifiers |
| Custom | `G`, then a letter | Bindings an admin adds themselves |

Nothing is held down by default. Browsers, operating systems and keyboard layouts already own many
`Alt` and `Ctrl` combinations (`Alt`+`D` focuses the address bar, `Alt`+`Shift` switches the layout on
Windows, `Option` types characters on a Mac), and a page cannot tell which. So navigation uses a
leader key: press `G`, let go, then press the letter within a second and a half. `Esc` cancels.
Header and row actions share one pool of bare letters, so they never collide, and none of them can
take `G`, `J` or `K`. A header action is never lettered automatically onto a registered row action's
letter, on any page, so header letters do not change with the tables a page has. If you prefer held
modifiers, set them per set in the `modifiers` config.

On bare letters, a header action only gets a key by default if it is safe to press by accident: it opens a page, or it
asks first with a modal or a confirmation. An action that does its work on the click itself gets no
default key, so one stray keystroke can never run it. To key one anyway, pin a letter for it in the
`overlay` config. Filament cannot tell without mounting an action whether a plain form schema will
open a modal, so such an action is treated as instant too; give it a modal heading or pin it. On a
held modifier or a leader every action keeps its key, since those are never pressed by accident.
A header action that is hidden (`->visible(false)`, or a `visible` closure that fails for this admin)
or disabled gets no key at all, so it never takes a letter a live action could have had.

Anyone can turn single-key shortcuts off from the `Shift` + `/` cheatsheet. The choice is saved in
that browser and silences every bare key and every `G` sequence, which assistive technology and voice
control users may need. Shortcuts on held modifiers, if you configured any, keep working.

A leader cannot be a key the table already uses (`J`, `K`, `/` and the like), and header or row
actions cannot use the same keys as navigation or custom bindings, because the two groups are
lettered separately. Both mistakes throw on the next render with a message naming the problem.

The arrow keys scroll the page until `J` or `K` starts the row cursor, and again once you click
somewhere else or press `Esc`, so a long page or a wide table still scrolls from the keyboard. A held
key fires an action once; moving the row cursor (`J`, `K`, `↑`, `↓`) repeats while held, like
scrolling. `←` and `→` turn one page per press.

After a shortcut clicks a control that sends a Livewire request, bare keys and `G` sequences are
ignored until the response arrives (at most about two seconds), so a quick second key cannot land on
the row behind a modal that is about to open.

A page with more than one table — a record page with relation managers, a page or dashboard with
table widgets in its header, footer or widget area — gets the table keys too (a table placed any other
way is not detected; see the notes below). `J` and `K` walk the rows of every table in page order, and
`/` and the paging arrows act on the table the cursor is in. Relation managers and table widgets load
after the page, so the cheatsheet checks the page when it opens and lists a row action only while a
row carrying it is on the page; an empty or filtered-out table shows none. There it is labelled from
its name (`mark_paid` reads "Mark paid"), since the page cannot read a label the action sets itself.

`Shift` + `/` opens a cheatsheet of everything active on the current page. The "Keyboard shortcuts"
page in the sidebar lists what is the same everywhere in the panel: navigation, custom bindings, the
table keys and the row-action letters. Header actions change from page to page, so they are only in
the cheatsheet.

Letters are picked from the item's name first (the label for navigation, the action or binding name
otherwise), then from the rest of the alphabet. Two shortcuts in the same pool never share a letter:
header and row actions share the bare pool, navigation and custom bindings share the `G` pool.

### Key hints

Where a shortcut maps to one stable element on the page, the plugin paints a small key badge on that
element, so the key is visible where it is used and not only in the cheatsheet. Navigation links,
header actions and an admin's own custom bindings all get one, whether the shortcut fires by
following a link or by clicking a control. Badges for `G` shortcuts appear only while `G` waits for
its letter, so the sidebar is not covered in letters the rest of the time.

Table keys and row actions get none, on purpose. A table key is a behaviour with no single element
behind it, and a row action repeats on every row, so a badge per row would be noise rather than a
hint. Both stay in the cheatsheet.

The badge is a `span` carrying the class `fi-hotkey-hint`, styled inline so the package ships no
stylesheet of its own. It is marked `aria-hidden`, so a screen reader reads the control's own label
and nothing else; the key itself is announced through `aria-keyshortcuts` on the control. A `G`
sequence gets no `aria-keyshortcuts`, because that attribute reads spaces as alternatives (`G O`
would mean "G or O"), so the cheatsheet is where those are listed. A value your app already put on
the control is left alone. Restyle it from your panel's stylesheet if the inline look does not suit the theme.

The row the cursor is on carries the class `fi-hotkey-row-focused` and an inline outline. Target that
class to restyle the cursor; an `outline` rule there needs `!important` to beat the inline one.

### Row actions

Table row actions are per-row, so the plugin only binds the ones you name. Register them once and
they work on any table that exposes an action of that name, including relation manager and widget
tables, firing against the row the cursor is on:

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

Set `customization` to `locked` to turn all of that off. Everyone uses the panel's default system map, which an authorized admin sets on the manage page; with none set, the conventions. An admin who selected a map while `personal` mode was on keeps that selection.

A custom binding points at either a route in your app or a CSS selector on the page:

```php
['route' => '/admin/orders']         // navigates
['selector' => '#refresh-button']    // clicks
```

## Editing the shared map

Editing the map everyone else sees is gated separately from personal customization, and denied by
default. Grant it with a Gate ability or a closure:

```php
// config/shortcut-keys.php

// the name of an ability you defined (recommended)
'authorize' => 'manage-shortcut-keys',

// or a closure; needs `use Illuminate\Contracts\Auth\Authenticatable;` at the top of the file
'authorize' => fn (?Authenticatable $user, string $panelId) => $user?->isDeveloper() ?? false,
```

Prefer the Gate ability. A closure in a config file cannot be serialized, so `php artisan config:cache`
fails while one is there. The ability is checked as `Gate::forUser($user)->allows($ability, $panelId)`.

Whoever passes the gate can create a preset, clone an existing one, edit a preset in place, or make a
preset the panel default: the map every admin without their own selection uses, which in `locked`
mode is everyone. Until a default is set, those admins get the plain conventions. An in-place edit or
a new default changes keys for other admins, so both forms require an explicit confirmation checkbox
before they will save.

## Configuration

```php
return [
    // 'personal' lets each admin fork and remap their own keys; 'locked' uses one shared map.
    'customization' => 'personal',

    // How each set's letters are pressed. One letter (['g']) is a leader key: press it, then the
    // letter. An empty list means bare keys. Tokens ctrl, alt, shift, meta hold modifiers instead.
    // Drop a set from this list to put it back on its built-in scheme; an unknown token throws.
    'modifiers' => [
        'navigation' => ['g'],
        'global' => [],
        'table' => [],
        'row-action' => [],
        'custom' => ['g'],
    ],

    // Who may edit a panel's shared system maps. null denies everyone.
    'authorize' => null,

    // Force or drop a shortcut from code, keyed by target identity.
    'overlay' => [
        'navigation:App\Filament\Resources\ProductResource' => ['letter' => 'r'],
        'global:export' => ['disabled' => true],
    ],

    // Seconds to cache the panel-wide keymap.
    'cache' => ['ttl' => 86400],
];
```

The cache key already covers freshness: it is a fingerprint of the panel id, the navigation, the
active map's identity and version, the developer config (`overlay`, `modifiers` and the keys they
reserve), the locale and the package's cache format, so a change to any of them resolves to a
different key. It does not include who is asking: admins on the same map share one entry.
The lifetime exists to reclaim the keys left behind by that rotation, since every edit and every
deploy that touches navigation strands its predecessor. Setting it to `null` caches forever, which is
only sensible on a store you actively manage.

An `overlay` entry wins over the conventions and needs no database rows, which makes it the right tool
for a key you need to pin in code. An overlay disable also wins over an admin's own edit; a pinned
letter does not, so an admin who remaps that shortcut on the manage page gets their own letter.

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

The package ships English. Every string it renders goes through Laravel's translator, so publishing
the files and adding your own locale gets you a fully translated panel — nothing is hardcoded.

In a right-to-left panel the pagination arrows are mirrored to match Filament's own flipped buttons,
so `←` moves forward. Panel direction comes from Filament's translations rather than this package's,
so that applies whatever language you run. The mirroring is covered by tests, but the right-to-left
experience as a whole has not been verified in a browser, so treat it as working-but-unproven.

Publish the files to change the wording or add a locale:

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
  that had lost a clash. Pin anything you want to stay fixed with a config `overlay` entry, for
  example `'navigation:App\Filament\Resources\ProductResource' => ['letter' => 'p']`; a pinned
  letter never moves, whatever is added to the sidebar later.
- Header actions inside an action dropdown get no key. Row actions inside one are bound (Filament
  flattens record-action groups), but whether the key reaches a control in a collapsed dropdown is
  unverified.
- One fixed leader key per set (`G`, then a letter) is the only sequence. There are no user-defined
  sequences or macros.
- Navigation keys cover every page and resource the panel registers, including a page left out of
  the sidebar with `$shouldRegisterNavigation = false`; its key still opens it. To drop one, disable
  it in the config `overlay`: `'navigation:App\Filament\Pages\Hidden' => ['disabled' => true]`.
- Items added with `$panel->navigationItems([...])` get no key, whether they point inside the app or
  elsewhere. An admin can bind a route to one as a custom binding.
- Table keys reach a table the page owns, a relation manager, or a table widget in the page's header
  or footer widgets or on a dashboard. A table placed through the page's own `content()` schema or
  `Livewire::make()`, or a widget placed through `getWidgetsSchemaComponents()`, is not detected, so
  that page gets no table keys unless it is a table page itself.
- Filament 4 is not supported. Its bug-fix window closes in January 2027, so the package targets
  Filament 5 only.
- Anything marked `@internal`, including the resolution ports in `Core\Contracts` (`ShortcutSet`,
  `PageContextProvider`, `NavigationProvider`, `ReservesKeys`, `AcceptsCustomBindings`, `Resolver`),
  is not public API and may change in a minor release.

## Laravel Boost

The package ships guidelines and skills for [Laravel Boost](https://github.com/laravel/boost). The
interactive `php artisan boost:install` asks which third-party packages to include; pick this one. A
non-interactive install (`-n`) skips third-party packages, so add the package to `boost.json` and run
`boost:install` again:

```json
"packages": ["aristonis/filament-shortcut-keys"]
```

## How it works inside

The README covers using the plugin. If you are changing it, or want to know why something is built the
way it is, the design notes live in [`docs/`](docs):

- [Architecture](docs/architecture.md) — layers, the ports each adapter implements, what happens on a
  page render, and why the keymap is public rather than filtered per user.
- [Domain model](docs/domain-model.md) — the three tables, the constraints holding them together, and
  how a first edit forks a shared map without losing a concurrent one.
- [Resolution](docs/resolution.md) — the pipeline turning registered resources into a keymap, how
  letters are assigned, and what the browser actually receives.

These ship with the repository but not with the released package.

## Testing

```bash
composer test
```

The suite runs on sqlite by default. Set `FSK_DB_DRIVER` to `pgsql` or `mysql` to run it against a
real driver, which CI does on every push — a few behaviours, including the fork race described in
[docs/domain-model.md](docs/domain-model.md), cannot be observed on a single-connection database.

`composer test` skips coverage so it runs anywhere. For a coverage report, install pcov or Xdebug and
run `composer test:coverage`.

The browser half has its own suite:

```bash
npm test
```

It runs on node alone — the tests import `resources/js` directly and stub the DOM, so there is nothing
to install and nothing to build first. Selectors resolve by exact string against a registry each test
supplies, which checks what a module asks for and what it does with the answer; whether a selector
matches Filament's real markup is asserted separately, against captured HTML, on the PHP side.

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
