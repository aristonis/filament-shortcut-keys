---
name: filament-shortcut-keys-troubleshooting
description: Diagnose why a keyboard shortcut from aristonis/filament-shortcut-keys does not fire, fires the wrong thing, or shows a stale key — covering the typing guard, table focus, unrendered controls, set precedence, letter assignment and the keymap cache. Use when a shortcut misbehaves in a panel and the cause is not obvious.
---

# Skill — why a shortcut is not working

Work top down. Most reports end at the first three, and most of those are the design working.

## 1. Is the key in the keymap at all?

The server injects the map into every panel page as a JSON block with id
`filament-shortcut-keys-map`. Read it in the browser console:

```js
JSON.parse(document.getElementById('filament-shortcut-keys-map').textContent)
```

Each group carries its set, its client handler, its modifier and its bindings. If the binding is
absent, the problem is server-side discovery — skip to section 4. If it is present, the problem is
dispatch.

## 2. Nothing fires while typing

By design. The listener ignores everything while an input, textarea, select or contenteditable element
has focus. Bare-key sets additionally require their own context: table keys only act when the page has
a table.

If a bare key seems dead, check whether focus is sitting in the table's search box — that is the usual
cause, and pressing a key there types rather than acts.

## 3. The key fires but nothing happens

The shortcut clicks a real control or follows a real route. When nothing happens, usually nothing was
there to click:

- **The control is not rendered.** Filament does not render a control the viewer cannot trigger, so an
  unauthorized action's key is a no-op. Intended, not a bug.
- **The route is refused.** A custom binding pointing at a page the admin cannot open navigates there
  and gets a 403. Also intended.
- **The table does not expose that action.** `Enter` needs an edit action on the row, `Delete` a delete
  action, `/` a searchable column. Without them the key has no target.
- **No row is focused.** Row actions dispatch against the focused row. Move the cursor with the arrow
  keys first.

A useful check: does clicking the control with the mouse work for this user? If not, the shortcut is
behaving correctly.

## 4. The binding is missing from the keymap

- **A row action you expected** — only registered names are bound, and only on pages whose table
  exposes that name. See `filament-shortcut-keys-row-actions`.
- **A page you expected** — navigation is built from registered resources and pages. If it is not
  registered on the panel, it has no shortcut.
- **A shortcut you disabled in the overlay** — a disabled entry is removed before letters are assigned.
- **A shortcut with a forced letter that collided** — an invalid or taken forced letter is dropped
  rather than displacing another shortcut.

## 5. The wrong thing fires

When one key could match more than one active set, the client resolves in a fixed order:

```
page > custom > table > global > navigation
```

Sets on different modifiers cannot collide at all, so this only arises within a shared modifier pool.
Two sets configured onto the same scheme share one letter pool, which prevents duplicates but does move
letters around — if keys shifted after a config change, that is why.

## 6. The keymap is stale

The cache key is a fingerprint of the navigation, the active map and its version, the overlay and the
locale. Any of those changing produces a different key, so a stale map almost always means a version
that did not move:

- **Wrote to the tables directly?** Every content change must bump the map's `version`. The application
  services do this; raw SQL does not.
- **Changed navigation?** The nav version token is derived from the registered resources and pages, so
  adding or renaming one busts the key by itself.
- **Changed config?** The overlay is hashed into the key. Editing the config file is enough — but a
  cached config in production still needs `config:clear`.

## 7. Right-to-left panels

Pagination arrows are mirrored server-side, so the key that fires matches the direction shown. If they
feel backwards, check the panel's direction rather than the plugin: it reads Filament's own
`filament-panels::layout.direction`.

Row-movement arrows are **not** mirrored — up is up in any direction.

## What is not supported

Before investigating further, these never worked and are not bugs:

- multi-key sequences (`g` then `d`) — single combinations only
- a command palette or fuzzy search
- keying items inside a **closed** `ActionGroup` dropdown
- a page declaring its own shortcuts
- per-admin modifier schemes — modifiers are configured per deployment
