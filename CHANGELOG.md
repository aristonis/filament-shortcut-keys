# Changelog

All notable changes to `filament-shortcut-keys` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-09-01

First release. Keyboard shortcuts for a Filament panel, derived from what the panel already declares.

### Added

**Shortcuts without configuration.** Navigation shortcuts are built from every registered resource
and page, header actions from each page's own action list, and table keys once from a fixed set that
applies to every table in the panel. Adding a resource gives it a shortcut; nothing to register.

**Real controls, real permissions.** A shortcut clicks the Filament control or follows the route you
would have used yourself, so authorization and confirmation modals stay Filament's. The plugin never
invents a privileged action and never calls a Livewire method directly.

**Stable identity.** A shortcut is keyed on the resource class or the action name, never on a slug or
a label, so a stored override survives both renaming and reordering.

**Two modes.** `locked` gives every admin one shared map. `personal` lets each admin customize their
own: the first edit forks the shared map, stores only the differences, and leaves everything else
following the conventions — so a resource added later still appears in a customized map.

**Custom bindings.** An admin can bind a key to a route in the app or to an element already on the
page. Those two shapes are the whole surface, which is what keeps a binding from becoming a way to
reach something you could not otherwise reach.

**Gated authoring of shared maps.** An authority gate you supply, denying by default, can let a
trusted admin create a preset, clone one, or edit a shared map in place. Editing in place asks for
explicit confirmation, because it changes the map for everyone using it.

**Discoverability.** A reference page lists every panel-wide shortcut, and `?` opens a cheatsheet
overlay of what is active on the current page.

**Row actions.** Register your own row-action names once and each gets a stable letter across the
whole panel, active on any table exposing an action of that name, dispatched against the focused row.

**Configuration.** Per-set modifiers, a config overlay that can force a letter or drop a shortcut with
no database rows, the authority gate, and the cache lifetime.

**Maintenance.** `shortcut-keys:prune` removes stored overrides whose target no longer exists. It only
deletes what it can prove is gone, and reports the rest rather than guessing.

**Portability.** Any authenticatable model, keyed by integer, UUID, or ULID. Tested against SQLite,
MySQL, and PostgreSQL on every push.

### Requirements

- PHP 8.2 or newer
- Filament 5.0 or newer
- MySQL 5.7+, MariaDB 10.2+, PostgreSQL, or SQLite (the schema uses a native `json` column)

### Known limitations

- **English only.** Every string is translatable and publishing the language files gets you a fully
  translated panel, but only English ships.
- **Right to left is unproven.** Pagination keys are mirrored for an RTL panel and that mirroring is
  tested, but the experience as a whole has not been verified in a browser.
- **A page cannot declare its own shortcuts.** Shortcuts come from navigation, page actions, and
  tables.
- **Modifiers are chosen per deployment, not per admin.** Configured once for everyone.
- **Customization covers navigation shortcuts.** Table keys and row actions are uniform by design and
  are not remappable.
- **The keymap is public.** It is built from every registered page without a permission check, so it
  reveals which pages exist, not what an admin may do. Access is enforced when the key fires. A page
  whose existence is itself sensitive does not belong in a panel's navigation.
- **Single key combinations only.** No multi-key sequences, and no command palette.

[Unreleased]: https://github.com/aristonis/filament-shortcut-keys/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/aristonis/filament-shortcut-keys/releases/tag/v1.0.0
