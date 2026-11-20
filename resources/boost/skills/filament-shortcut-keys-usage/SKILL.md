---
name: filament-shortcut-keys-usage
description: Install and register aristonis/filament-shortcut-keys on a Filament v5 panel, understand which shortcuts appear with no configuration, and choose between the locked and personal customization modes. Use when adding keyboard shortcuts to a panel, when a shortcut is expected but missing, or when deciding whether admins may customize their own keys.
---

# Skill — use the package

Register the plugin on a panel and every resource, page, header action and table gets keys.

## Install

Requires PHP 8.2+, Laravel 12 or 13, and Filament 5.x.

```bash
composer require aristonis/filament-shortcut-keys
php artisan vendor:publish --tag="filament-shortcut-keys-migrations"
php artisan migrate
php artisan filament:assets
```

Migrations are required even in `locked` mode: the shared system map, its overrides, and each admin's
selection all live in those tables. `filament:assets` publishes the client script; without it the
keymap is in the page but no key fires. An app set up with `filament:install` already re-runs it on
every Composer install or update through `filament:upgrade` in `post-autoload-dump`; without that
hook, re-run it after every update of the package.

## Register on the panel

```php
use Aristonis\FilamentShortcutKeys\FilamentShortcutKeysPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugin(FilamentShortcutKeysPlugin::make());
}
```

That is the whole setup. The plugin registers its own pages, injects the keymap into every panel page,
and ships the client script.

No theme step is needed, custom theme or not: the plugin's views style themselves with Filament's
own components and a small embedded stylesheet, so there are no Tailwind classes to keep from purging.

## What you get with no configuration

| Set | Keys | Covers |
| --- | --- | --- |
| Navigation | `G`, then a letter | every registered resource and page |
| Actions | bare letter | the current page's header actions |
| Table | bare keys | `/` search, `J`/`K` start and move the row cursor, `Esc` releases it, `Space` selects, `Enter` edits, `Delete` deletes; while the cursor is live the arrows move it (up/down) and paginate (left/right) |
| Row actions | bare letters | only the record actions you register by name |
| Custom | `G`, then a letter | bindings an admin adds for themselves |

Nothing is held down by default, so no shortcut fights the browser for `Alt` or `Ctrl` combinations.
`G` waits about a second and a half for its letter; `Esc` cancels.

On bare letters, a header action gets a default key only if pressing it by accident is harmless: it opens a page, a
modal or a confirmation. An action that runs on the click itself gets none; pin a letter in the
`overlay` config to key it deliberately. Users can switch all single-key shortcuts off from the
cheatsheet; the choice is kept per browser.

`Shift` + `/` opens a cheatsheet of what is active on the current page, and a full listing of the
panel-wide shortcuts gets its own sidebar page.

Shortcuts that map to one stable element also carry a small key badge on that element: navigation
links, header actions and custom bindings. Table keys and row actions do not, because a table key has
no single element and a row action repeats per row. The badge is a `span.fi-hotkey-hint`, marked
`aria-hidden` so screen readers still read only the control's own label; the key is announced
through `aria-keyshortcuts` instead (not for `G` sequences, which that attribute cannot express).

Letters come from the item's name first, then the rest of the alphabet: the label for navigation, the
action name for header and row actions, the binding name for custom bindings. Two shortcuts sharing a
pool never share a letter.

No bare or `g` shortcut fires while an input, textarea, select, or contenteditable element has focus.
Shortcuts on held modifiers, if you configure any, still do.

## Choose a mode

`config('shortcut-keys.customization')` takes one of two values:

- **`personal`** (default) — each admin gets a manage-shortcuts page to remap a key, disable one, add
  their own binding, or switch between presets. Their first edit copies the shared map into a private
  one. From then on, later edits to the shared map no longer reach them; keys they never touched still
  follow the conventions, so a resource added later still gets a key.
- **`locked`** — no per-admin editing at all. Everyone uses the panel's default system map, which an authorized admin sets on the manage page; with none set, the conventions. An admin who selected a map while `personal` mode was on keeps that selection, including a personal map they can no longer change.

Publish the config only if you need to change something:

```bash
php artisan vendor:publish --tag="filament-shortcut-keys-config"
```

## Expected shortcut is missing

Work down this list before changing anything:

- **A row action has no key** — only names you register get one. See `filament-shortcut-keys-row-actions`.
- **A table key does nothing** — the page's table has to actually expose that control. `Enter` needs an
  edit action on the row, `Delete` needs a delete action, `/` needs a searchable column.
- **A key fires nothing but the page is right** — the control may exist but be unauthorized for this
  admin, so Filament never rendered it. That is the intended outcome, not a bug.
- **The keymap looks stale after an edit** — a content change bumps the map version, which is what
  busts the cache. If you wrote to the tables directly and skipped the bump, the old map is still served.

## What not to do

- Do not filter the keymap by permission. It is public by design; access is enforced when a key fires.
- Do not bind a key to something the admin could not click anyway and expect it to be blocked at build
  time — the boundary is at trigger time.
- Do not expect the manage page to remap table or row-action keys; it offers navigation only. Move
  those through the config overlay, and never onto a reserved key (`g`, `j`, `k`, a table key).
