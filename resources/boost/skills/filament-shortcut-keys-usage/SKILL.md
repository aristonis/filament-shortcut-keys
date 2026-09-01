---
name: filament-shortcut-keys-usage
description: Install and register aristonis/filament-shortcut-keys on a Filament v5 panel, understand which shortcuts appear with no configuration, and choose between the locked and personal customization modes. Use when adding keyboard shortcuts to a panel, when a shortcut is expected but missing, or when deciding whether admins may customize their own keys.
---

# Skill — use the package

Register the plugin on a panel and every resource, page, header action and table gets keys.

## Install

```bash
composer require aristonis/filament-shortcut-keys
php artisan vendor:publish --tag="filament-shortcut-keys-migrations"
php artisan migrate
```

Migrations are required even in `locked` mode: the shared system map, its overrides, and each admin's
selection all live in those tables.

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

With a custom Filament theme, add the plugin's views to the theme's css so its classes survive
Tailwind's purge:

```css
@source '../../../../vendor/aristonis/filament-shortcut-keys/resources/**/*.blade.php';
```

## What you get with no configuration

| Set | Keys | Covers |
| --- | --- | --- |
| Navigation | `Alt` + `Shift` + letter | every registered resource and page |
| Actions | `Alt` + letter | the current page's header actions |
| Table | bare keys | `/` search, arrows move the row cursor, `Space` selects, `Enter` edits, `Delete` deletes, left/right paginate |
| Row actions | bare letters | only the record actions you register by name |
| Custom | `Alt` + `Shift` + letter | bindings an admin adds for themselves |

`Shift` + `/` opens a cheatsheet of what is active on the current page, and a full listing of the
panel-wide shortcuts gets its own sidebar page.

Letters come from each item's own label first, then the rest of the alphabet. Two shortcuts sharing a
modifier never share a letter.

Nothing fires while an input, textarea, select, or contenteditable element has focus.

## Choose a mode

`config('shortcut-keys.customization')` takes one of two values:

- **`personal`** (default) — each admin gets a manage-shortcuts page to remap a key, disable one, add
  their own binding, or switch between presets. Their first edit copies the shared map into a private
  one, so later changes to the shared map still reach every key they have not touched.
- **`locked`** — no per-admin editing at all. Everyone uses the panel's system map.

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
- Do not try to remap a table or row-action key. Those sets have no modifier, so a forced letter is
  discarded during resolution.
