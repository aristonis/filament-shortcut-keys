---
name: filament-shortcut-keys-troubleshooting
description: Diagnose why a keyboard shortcut from aristonis/filament-shortcut-keys does not fire, fires the wrong thing, or shows a stale key — covering the typing guard, table focus, unrendered controls, set precedence, letter assignment and the keymap cache. Use when a shortcut misbehaves in a panel and the cause is not obvious.
---

# Skill — why a shortcut is not working

Work top down. Most reports end at the first three, and most of those are the design working.

## 0. No key at all works, anywhere

Almost always a missing `php artisan filament:assets`. The keymap is rendered server-side, so it is in
the page either way and everything looks installed — but the script that reads it was never published.

```bash
curl -I https://your-panel.test/js/filament-shortcut-keys/filament-shortcut-keys.js
```

A 404 confirms it. Run `php artisan filament:assets` and reload. This needs re-running after each
deploy and after any `composer update` that touches the package.

If the file is a 200 and still nothing fires, the map may be absent from the page entirely — check for
`filament-shortcut-keys-map` in the source before going further.

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

## 8. The key works but shows no badge

Only shortcuts that map to one stable element get a badge: navigation links, header actions and
custom bindings. Table keys and row actions never do, because a table key has no single element and a
row action repeats per row. Both are listed in the `Shift` + `/` cheatsheet instead.

If a shortcut that should carry one does not, the element the badge attaches to was not found, and
where to look depends on how the shortcut fires.

A shortcut that fires by clicking uses one selector for both jobs, so a missing badge means a dead
key as well: go to section 3.

A shortcut that fires by navigating is different. The handler only calls `Livewire.navigate(url)` and
never queries the page, while the badge looks for an `a[href]` matching that url exactly. So the key
can work perfectly and still show no badge, which means the href Filament rendered is not
character-for-character the url in the keymap. Compare the two in the browser: read the url from the
`filament-shortcut-keys-map` script block and the `href` from the link itself.

## What is not supported

Before investigating further, these never worked and are not bugs:

- multi-key sequences (`g` then `d`) — single combinations only
- a command palette or fuzzy search
- keying items inside a **closed** `ActionGroup` dropdown
- a page declaring its own shortcuts
- per-admin modifier schemes — modifiers are configured per deployment
