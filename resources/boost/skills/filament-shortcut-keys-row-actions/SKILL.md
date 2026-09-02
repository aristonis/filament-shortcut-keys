---
name: filament-shortcut-keys-row-actions
description: Give table record actions keyboard shortcuts with aristonis/filament-shortcut-keys by registering their names on the plugin. Use when a row action such as approve or reject needs a key, when a registered row action has no shortcut on some pages, or when deciding which record actions to bind.
---

# Skill — key your own row actions

Row actions are per-row, so the plugin binds only the names you register. Register once and they work
on every table in the panel that exposes an action of that name.

## Register

```php
use Aristonis\FilamentShortcutKeys\FilamentShortcutKeysPlugin;

$panel->plugin(
    FilamentShortcutKeysPlugin::make()
        ->rowActions(['approve', 'reject', 'suspend'])
);
```

Names only — these are matched against the action names your tables already declare. You are not
defining actions here, only saying which existing ones deserve a key.

## What happens

Each registered name gets a letter, assigned across the **full** registered list rather than per page.
That is deliberate: `approve` keeps the same key whether or not `reject` happens to be on the current
table, so the key does not move as you navigate.

The array is ordered data, not a set. Each name takes the first letter of its own label that is still
free, so whoever comes first picks first — `['reject', 'refund']` gives `R` and `E`, and swapping the
two gives `R` and `J`. Alphabetising the array during a cleanup silently rekeys the panel. If a letter
has to survive a reordering, pin it in the config overlay rather than relying on the position.

On any given page, a binding is emitted only when that page's table actually exposes a record action
of that name. Everywhere else it is neither bound nor listed in the cheatsheet, so there are no dead
keys advertised to the user.

Firing one triggers the real Filament record action on the row the cursor is on, with its native
authorization and confirmation. Move the cursor with the arrow keys first.

## Do not register edit or delete

`edit` and `delete` are already bound to `Enter` and `Delete` by the table set, and they are excluded
from row-action lettering. Registering them is a no-op rather than an error, but it signals a
misunderstanding worth correcting.

`view` is **not** bound by default: a row click already opens it, so the key is spent elsewhere. If
you want a view shortcut, register `view` like any other name and it gets an auto-assigned letter.

## When a registered action has no key on a page

In order of likelihood:

1. **That table does not declare the action.** Page-filtering is working as intended.
2. **The action is on the page but not as a record action.** Header actions belong to the actions set
   (`Alt` + letter), not the row-action set.
3. **The action is inside a closed `ActionGroup`.** Items in a collapsed dropdown are not keyed until
   the menu opens.
4. **The user is not authorized for it**, so Filament never rendered the control. The key exists and
   does nothing, which is the intended security model.

## Letters

Row actions are bare keys — no modifier. Two consequences worth knowing:

- They only fire when a table is present and you are not typing.
- They **cannot be remapped** by an admin. A forced letter needs a modifier to attach to, so a remap
  aimed at a row action is discarded during resolution. If you need a different letter, change the
  action's name or use the developer overlay.
