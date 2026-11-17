# Changelog

All notable changes to `filament-shortcut-keys` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-11-17

First release. Keyboard shortcuts for a Filament panel, derived from what the panel already declares.

### Added

**Shortcuts without configuration.** Navigation shortcuts are built from every registered resource
and page, header actions from each page's own action list, and table keys once from a fixed set that
applies to every table in the panel. Adding a resource gives it a shortcut; nothing to register.

**Keys that stay out of the browser's way.** Nothing is held down by default. Navigation and custom
bindings use a leader key, `G` then a letter; header and row actions use bare letters. `Alt` and
`Ctrl` combinations belong to the browser, the OS and the keyboard layout (`Alt`+`D`, the `Alt`+`Shift`
layout switch, `Option` typing on a Mac), and a page cannot tell which are free. `G` waits about a
second and a half for its letter and `Esc` cancels it.

**A header action is keyed only when a stray press is harmless.** On bare letters, an action gets a
default key if it opens a page, a modal or a confirmation; one that does its work on the click gets
none unless a developer pins it.

**Table keys.** `/` searches, `J` and `K` start and move a row cursor, `Esc` releases it, `Space`
selects, `Enter` edits and `Delete` deletes the marked row through Filament's own confirm. While the
cursor is live the arrows move it and page the table; otherwise they scroll the page as usual. The
cursor holds real focus and follows the record, so a sort, a search or a refresh never points a row
key at a different record.

**Keys go where the user's attention is.** No bare or `G` shortcut fires while typing in a field, while a modal,
an open select, a dropdown, a menu or a tab list has the keyboard, or with `Enter`/`Space` on a focused
button. A held key acts once, keys typed through an input method are ignored, and a key pressed while
a shortcut's modal is still loading waits for it.

**Real controls, real permissions.** A shortcut clicks the Filament control or follows the route you
would have used yourself, so authorization and confirmation modals stay Filament's. The plugin never
invents a privileged action and never calls a Livewire method directly.

**Stable identity.** A shortcut is keyed on the resource class or the action name, never on a slug or
a label, so a stored override survives both renaming and reordering.

**Two modes.** `locked` gives every admin one shared map. `personal` lets each admin customize their
own: the first edit forks the shared map, stores only the differences, and leaves everything else
following the conventions, so a resource added later still appears in a customized map.

**Custom bindings.** An admin can bind a key to a route in the app or to an element already on the
page. Those two shapes are the whole surface, which is what keeps a binding from becoming a way to
reach something you could not otherwise reach.

**Gated authoring of shared maps.** An authority gate you supply, denying by default, can let a
trusted admin create a preset, clone one, edit a shared map in place, or make a preset the panel
default that every admin without their own map uses (everyone, in `locked` mode). Editing in place and
changing the default ask for explicit confirmation, because both change keys for other admins.

**Discoverability.** A reference page lists every panel-wide shortcut, `?` opens a cheatsheet of what
is active on the current page, and a small key badge sits on each link and action that has a key.

**Accessibility.** Each badged control carries `aria-keyshortcuts`, so a screen reader announces its
key. The cheatsheet has a switch that turns every single-key shortcut off in that browser, for people
using assistive technology or voice control; held-modifier shortcuts, if configured, keep working.

**Row actions.** Register your own row-action names once and each gets a stable letter across the
whole panel, active on any table exposing an action of that name, dispatched against the marked row.

**Configuration.** Per-set keys (`['g']` for a leader, `[]` for bare keys, or held modifiers), a config
overlay that pins a letter or drops a shortcut with no database rows, the authority gate, and the
cache lifetime. Changing any of them reaches the cached keymap on the next request.

**Maintenance.** `shortcut-keys:prune` removes stored overrides whose target no longer exists. It only
deletes what it can prove is gone, and reports the rest rather than guessing.

**Portability.** Any authenticatable model, keyed by integer, UUID, or ULID. Tested against SQLite,
MySQL, and PostgreSQL on every push, and on Laravel 12 and 13.

**Agent guidelines.** Laravel Boost picks up the package's guidelines and skills on `boost:install`.

### Requirements

- PHP 8.2 or newer
- Laravel 12 or 13
- Filament 5.x
- MySQL 5.7+, MariaDB 10.2+, PostgreSQL, or SQLite (the schema uses a native `json` column)
- `php artisan filament:assets` after install and after each update; without it no key fires

### Known limitations

- **English only.** Every string is translatable and publishing the language files gets you a fully
  translated panel, but only English ships.
- **Right to left is unproven.** Pagination keys are mirrored for an RTL panel and that mirroring is
  tested, but the experience as a whole has not been verified in a browser.
- **A page cannot declare its own shortcuts.** Shortcuts come from navigation, page actions, and
  tables.
- **Keys are chosen per deployment, not per admin.** Configured once for everyone; each admin can only
  switch single-key shortcuts off for themselves.
- **Admins customize navigation only.** The manage page does not offer table or row-action keys; a
  developer can still move them with the config overlay.
- **Automatic letters follow sidebar order.** Adding a screen can move a later letter; pin the ones
  people rely on in the config overlay.
- **An action whose only modal is a form schema counts as instant** and gets no default bare key,
  because that cannot be known without mounting it. Give it a modal heading or pin it.
- **`G` sequences carry no `aria-keyshortcuts`.** The attribute cannot express a sequence; the
  cheatsheet lists them.
- **The keymap is public.** It is built from every registered page without a permission check, so it
  reveals which pages exist, not what an admin may do. Access is enforced when the key fires. A page
  whose existence is itself sensitive does not belong in a panel's navigation.
- **Header and row actions cannot share navigation's keys.** The two groups are lettered by
  separate resolvers, so a configuration that puts them on the same scheme is refused rather than
  allowed to hand one key to two shortcuts.
- **One fixed leader per set is the only sequence.** No user-defined sequences, and no command
  palette.

[Unreleased]: https://github.com/aristonis/filament-shortcut-keys/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/aristonis/filament-shortcut-keys/releases/tag/v1.0.0
