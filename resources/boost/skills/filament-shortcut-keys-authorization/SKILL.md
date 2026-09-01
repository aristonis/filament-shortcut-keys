---
name: filament-shortcut-keys-authorization
description: Control who may edit a panel's shared system shortcut maps in aristonis/filament-shortcut-keys using the authority gate — a closure or a Gate ability, denying by default. Use when granting an admin the ability to create, clone or edit shared presets, or when the manage-shortcuts page unexpectedly forbids or allows someone.
---

# Skill — gate the shared map

Two different permissions are easy to confuse:

- **Personal customization** is controlled by `customization` (`personal` or `locked`). It lets an
  admin change their *own* keys and touches nobody else.
- **Authoring** is controlled by `authorize`. It lets someone change the map *every other admin sees*.

This skill is about the second. It is denied by default, and it is not implied by being an admin.

## Grant it

```php
// config/shortcut-keys.php

// deny everyone (default)
'authorize' => null,

// a closure
'authorize' => fn (?Authenticatable $user, string $panelId) => $user?->isDeveloper() ?? false,

// or the name of a Gate ability you defined
'authorize' => 'manage-shortcut-keys',
```

The closure receives the user (possibly null) and the panel id, so one config can answer differently
per panel.

A string is checked as `Gate::forUser($user)->allows($ability, $panelId)`. Define the ability yourself;
an unauthenticated visitor is denied before the gate is consulted.

## What passing the gate allows

- **Create a preset** — a new empty system map for the panel, not the default.
- **Clone a preset** — copy an existing one, including its entries.
- **Edit a preset in place** — change the map every admin on it is already using. The form requires an
  explicit confirmation checkbox, because this is the one operation with blast radius beyond the actor.

What it does **not** allow: system maps never accept a custom binding's payload. Authoring is remap and
disable only. Accepting a selector or route on a shared map would publish an unvalidated client
activation to every admin, so the adapter rejects it regardless of caller.

## Strictness

The closure result is compared with `=== true`. A truthy non-boolean (`1`, `'yes'`) does **not** grant
authoring — a config that returns something odd fails closed.

Anything other than null, a closure, or a string is treated as deny.

## Scope is enforced below the gate

Passing the gate does not let a manager reach another panel's maps. The persistence layer scopes every
lookup by panel id and map type, so a manager for one panel passing another panel's map id gets a
not-found rather than a cross-panel edit. Scope is checked before authorization, so the answer is 404
rather than 403 — an unauthorized id should not be distinguishable from a nonexistent one.

## When access is not what you expect

- **The manage-shortcuts page is visible but authoring actions are missing.** Expected in `personal`
  mode with the gate denying: the page is reachable for personal edits, authoring buttons are not.
- **The page 403s entirely.** `locked` mode plus a denying gate leaves nothing the visitor may do.
- **The page still appears in the sidebar and cheatsheet for someone who cannot open it.** Also
  expected. The keymap is public and unfiltered by permission; clicking through gets a 403. Only the
  page's existence is visible, never a capability.
- **A Gate ability seems to grant when it should not.** Make sure the ability is actually registered
  with `Gate::define`. An unregistered ability name falls through to Laravel's implicit policy
  resolution, which keys on the argument passed — the panel id.
