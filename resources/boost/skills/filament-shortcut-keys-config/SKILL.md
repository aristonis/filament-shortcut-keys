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
    'navigation' => ['alt', 'shift'],
    'global' => ['alt'],
    'table' => [],
    'row-action' => [],
    'custom' => ['alt', 'shift'],
],
```

Tokens are `ctrl`, `alt`, `shift`, `meta`. An empty array means bare keys.

Three rules that decide the outcome:

- **Removing a set from the list restores its built-in scheme.** It does not turn the set into bare
  keys. Deleting a line is the safe way to undo a change.
- **An unknown token throws** rather than being ignored, so a typo fails loudly at render instead of
  silently changing which keys fire.
- **Two sets sharing a scheme share one letter pool.** Moving `global` onto `alt+shift` merges it with
  navigation's pool rather than creating a clash — nothing ends up double-bound, but letters shift.

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

An overlay entry beats both the conventions and an admin's own edit, and needs no database rows. That
makes it the right tool for a key you need pinned in code and the wrong tool for a preference.

A forced letter is applied before letters are assigned, so other shortcuts route around it. A disabled
entry is removed before assignment, so its letter returns to the pool for something else.

An invalid forced letter is ignored rather than fatal, so one bad entry cannot take down the keymap.

## Cache

```php
'cache' => ['ttl' => 86400],
```

The lifetime is about **reclamation, not freshness**. The cache key is a fingerprint of the
navigation, the active map and its version, the overlay and the locale, so any change already resolves
to a different key. What that cannot do is delete the entry it moved away from — every admin edit and
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
