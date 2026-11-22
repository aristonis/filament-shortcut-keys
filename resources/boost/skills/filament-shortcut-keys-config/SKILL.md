---
name: filament-shortcut-keys-config
description: Configure aristonis/filament-shortcut-keys — change the modifier scheme per shortcut set, force or drop a specific shortcut from code with the overlay, tune the keymap cache lifetime, and prune stored overrides whose target no longer exists. Use when a shortcut collides with a host application key, when a specific shortcut must be pinned or removed without database rows, or when clearing dead overrides.
---

# Skill — configure the plugin

Publish the config only when you need it:

```bash
php artisan vendor:publish --tag="filament-shortcut-keys-config"
```

Everything is read at call time, so a change takes effect on the next render.

## Modifiers per set

```php
'modifiers' => [
    'navigation' => ['g'],
    'global' => [],
    'table' => [],
    'row-action' => [],
    'custom' => ['g'],
],
```

A single letter (`['g']`) is a leader key: press it, let go, then the letter. It cannot be mixed with
other tokens. An empty array means bare keys. `ctrl`, `alt`, `shift` and `meta` hold modifiers
instead; the defaults avoid them because the browser, the OS and keyboard layouts own many of those
combinations. A leader key is reserved among bare keys, so no bare shortcut can take it. A leader
cannot be a table key (`j`, `k` and the like). Header actions (`global`) and row actions cannot use
the same keys as navigation or custom bindings, whether a leader, held modifiers or bare keys: the two
groups are lettered by separate resolvers, so a shared pool could give two shortcuts one key. Either
mistake throws on render, naming the set or the key. Header or row actions may use keys of their
own, e.g. `'global' => ['a']`.

Three rules that decide the outcome:

- **Removing a set from the list restores its built-in scheme.** It does not turn the set into bare
  keys. Deleting a line is the safe way to undo a change.
- **An unknown token throws** rather than being ignored, so a typo fails loudly at render instead of
  silently changing which keys fire.
- **Two sets sharing a scheme share one letter pool.** `global` and `row-action` share the bare pool
  by default, so nothing ends up double-bound. Row-action letters are claimed panel-wide and lettered
  first, so adding a header action never moves a row action's letter, while registering a row action
  can move header-action letters. A leader token is one lowercase letter (`['G']` throws).

`mod` is not supported. Whether it means ctrl or meta depends on the user's platform, which cannot be
decided on the server, and the keymap is built server-side.

Reach for this when the host application already owns a combination — that is a property of the
deployment, so it is configured once for everyone rather than per admin.

## Overlay: pin or drop one shortcut from code

```php
'overlay' => [
    'navigation:App\Filament\Resources\ProductResource' => ['letter' => 'r'],
    'global:export' => ['disabled' => true],
],
```

Keyed by target identity, which is `set:structuralKey` — the resource class for navigation, the action
name for actions.

An overlay entry beats the conventions and needs no database rows. That makes it the right tool for a
key you need pinned in code and the wrong tool for a preference. An overlay disable also beats an
admin's own edit; a pinned letter does not, so an admin who remaps that shortcut gets their letter.

A forced letter is applied before letters are assigned, so other shortcuts route around it. A disabled
entry is removed before assignment, so its letter returns to the pool for something else.

A letter that is not a–z is ignored rather than fatal, so one bad entry cannot take down the keymap.
The same goes for a letter on a reserved key (`g`, `j`, `k`, a table key): the shortcut keeps its
automatic letter. Any other pin works on any set, bare ones included, and takes its key from an
automatically lettered shortcut, which moves to another letter. If two forced letters land on one key
in a pool, the earlier one keeps it (navigation before custom bindings, header actions before row
actions) and the later shortcut is dropped, so check the cheatsheet after adding pins.

Row-action letters count as forced, because row actions are lettered before the overlay applies.
Pinning a row action onto a sibling's letter drops whichever of the two is later in
`->rowActions([...])`: with `['approve', 'reject']`, pinning approve to `r` gives approve `R` and
leaves reject with no key. Pin both, or pick a letter no sibling has. Row actions do step around
letters the overlay pins for header actions.

Pin the screens whose keys people rely on. Automatic letters follow sidebar order, so adding a
resource can move the letter of a later one that lost a clash; a pinned letter never moves.

Resources and pages hidden from the sidebar get no navigation key. `'navigation' =>
['include_hidden' => true]` letters them after every sidebar item, or an overlay letter pin opts in
one hidden page. Hidden pages whose route needs a parameter, nested resources and resources without an
index page never get one; disable, in the overlay, any other hidden page that shows nothing without a
record or a query parameter. Cluster pages are lettered after the sidebar. When
`shouldRegisterNavigation()` depends on the user, each admin is lettered by their own sidebar.

A letter pin also turns on a header action that runs on the click itself, which otherwise gets no
key on bare letters. Only pin such an action if a stray keystroke running it is acceptable.

## Cache

```php
'cache' => ['ttl' => 86400],
```

The lifetime is about **reclamation, not freshness**. The cache key is a fingerprint of the panel id,
the navigation, the active map's identity and version, the overlay, the `modifiers` config, the
reserved keys, the locale and a cache format version, so any change already resolves to a different
key. It does not include the user; admins on the same map share one entry. What that cannot do is delete the entry it moved away from — every admin edit and
every deploy touching navigation strands its predecessor.

`null` caches forever. Only choose it on a store you actively manage, and expect the keyspace to grow
with the number of edits your admins make.

## Prune dead overrides

An override is keyed on the resource class, so it survives renames — but not deletion. When a resource
is removed, its stored overrides stay behind.

```bash
php artisan shortcut-keys:prune --dry-run
php artisan shortcut-keys:prune
php artisan shortcut-keys:prune --panel=admin --force
```

- `--dry-run` reports and exits without deleting.
- `--panel=` audits specific panels; the default is every panel that registers this plugin. An explicit
  panel id that is unregistered, or registered without the plugin, fails loudly.
- `--force` skips the production confirmation prompt.

It only deletes what it can **prove** is gone — navigation targets, registered row-action names, and
the fixed table behaviours. Custom bindings, page-scoped and unknown targets are reported and left
alone, because outside a request the command cannot tell "this was removed" from "this is not on this
page". Deleting on a guess would destroy live user data.

Deleting bumps the version of every affected map in the same transaction, so cached keymaps carrying
the removed overrides stop being served.
