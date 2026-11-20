---
name: filament-shortcut-keys-row-actions
description: Give table record actions keyboard shortcuts with aristonis/filament-shortcut-keys by registering their names on the plugin. Use when a row action such as approve or reject needs a key, when a registered row action has no shortcut on some pages, or when deciding which record actions to bind.
---

# Skill — key your own row actions

Row actions are per-row, so the plugin binds only the names you register. Register once and they work
on every table in the panel that exposes an action of that name, relation manager and widget tables
included.

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

The array is ordered data, not a set. Each name takes the first letter of the name itself that is
still free, so whoever comes first picks first — `['reject', 'refund']` gives reject `R` and refund `E`, and
swapping the two gives refund `R` and reject `E`. Alphabetising the array during a cleanup silently rekeys the panel. If a letter
has to survive a reordering, pin it in the config overlay rather than relying on the position. One
catch: row actions are lettered before the overlay applies, so pinning one onto a letter a sibling row
action already holds drops the pinned one. Pin both, or pick a letter no sibling has.

A registered row action's letter is kept from header actions on every page, including pages with no
table, so registering a new name can move a header action's letter panel-wide, but adding a relation
manager or a table widget to a page never does.

A row action never gets a key reserved in its pool. By default that is `G` (the navigation leader),
`J` and `K` (they start and move the row cursor) and the other table keys. Putting row actions on
modifiers of their own (`'row-action' => ['alt']`) frees those letters.

On any given page, a binding is emitted only when that page's table actually exposes a record action
of that name. Everywhere else it is neither bound nor listed in the cheatsheet, so there are no dead
keys advertised to the user. A relation manager or a table widget loads after the page, so on a page
with one every registered name is bound, and the cheatsheet checks the page when it opens and lists
a row action only while a row carrying it is on the page. There it is labelled from its name, not
from a label the action sets itself.

Firing one triggers the real Filament record action on the row the cursor is on, with its native
authorization and confirmation. Start the cursor with `J` or `K` first; the arrow keys only move it
once it is live.

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
   (bare letters shared with row actions), not the row-action set.
3. **The action is inside an `ActionGroup`.** Filament flattens record-action groups, so a grouped row
   action is still bound and listed; whether pressing its key reaches the control inside a collapsed
   dropdown has not been verified. Header actions inside a group get no key at all.
4. **The user is not authorized for it**, so Filament never rendered the control. The key exists and
   does nothing, which is the intended security model.

## Letters

Row actions are bare keys — no modifier. Two consequences worth knowing:

- They only fire when a table is present and you are not typing.
- The manage page does not offer them. A developer can still move one with the config overlay (or
  `EditUserMap::remap` from code), as long as the letter is not reserved in the pool (`g`, `j`, `k`, a
  table key).
