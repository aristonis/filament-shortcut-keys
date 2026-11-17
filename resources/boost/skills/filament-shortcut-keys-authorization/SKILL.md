---
name: filament-shortcut-keys-authorization
description: Control who may edit a panel's shared system shortcut maps in aristonis/filament-shortcut-keys using the authority gate — a closure or a Gate ability, denying by default. Use when granting an admin the ability to create, clone or edit shared presets, or when the manage-shortcuts page unexpectedly forbids or allows someone.
---

# Skill — gate the shared map

Two different permissions are easy to confuse:

- **Personal customization** is controlled by `customization` (`personal` or `locked`). It lets an
  admin change their *own* keys and touches nobody else.
- **Authoring** is controlled by `authorize`. It lets someone change shared presets and choose the
  panel default, the map every admin without their own selection uses.

This skill is about the second. It is denied by default, and it is not implied by being an admin.

## Grant it

```php
// config/shortcut-keys.php

// deny everyone (default)
'authorize' => null,

// the name of a Gate ability you defined (recommended)
'authorize' => 'manage-shortcut-keys',

// or a closure; needs `use Illuminate\Contracts\Auth\Authenticatable;` at the top of the file
'authorize' => fn (?Authenticatable $user, string $panelId) => $user?->isDeveloper() ?? false,
```

Prefer the Gate ability: a closure in a config file cannot be serialized, so `php artisan
config:cache` fails while one is there. The closure receives the user (possibly null) and the panel
id, so one config can answer differently per panel.

A string is checked as `Gate::forUser($user)->allows($ability, $panelId)`. Define the ability yourself;
an unauthenticated visitor is denied before the gate is consulted.

## What passing the gate allows

- **Create a preset** — a new empty system map for the panel, not the default.
- **Clone a preset** — copy an existing one, including its entries.
- **Edit a preset in place** — change the map every admin on it is already using. The form requires an
  explicit confirmation checkbox, because the change reaches beyond the actor.
- **Set the panel default** — choose the preset every admin without their own selection uses; in
  `locked` mode that is everyone. Also confirmed explicitly, and the previous default loses its mark
  in the same transaction. Until a default is set, those admins get the plain conventions.

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
not-found rather than a cross-panel edit. The gate is checked first and the scope second; on the
manage page both refusals surface as a notification rather than an HTTP error.

## When access is not what you expect

- **The manage-shortcuts page is visible but authoring actions are missing.** Expected in `personal`
  mode with the gate denying: the page is reachable for personal edits, authoring buttons are not.
- **The page 403s entirely.** `locked` mode plus a denying gate leaves nothing the visitor may do.
- **The page still appears in the keymap and cheatsheet for someone who cannot open it.** Also
  expected. Filament hides it from the sidebar, but the keymap is public and unfiltered by
  permission; pressing its key gets a 403. Only the page's existence is visible, never a capability.
- **A Gate ability seems to grant when it should not.** Look for a `Gate::before` callback (a
  super-admin rule, say). It runs before every ability, registered or not, and its `true` grants
  authoring. Also check the ability is spelled the same in `Gate::define` and in the config.
