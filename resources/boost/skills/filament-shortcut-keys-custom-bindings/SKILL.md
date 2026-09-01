---
name: filament-shortcut-keys-custom-bindings
description: Let admins personalize their own shortcuts in aristonis/filament-shortcut-keys — remap a key, disable one, or add a binding pointing at a route or an on-page element — and understand the copy-on-write map model behind it. Use when driving personal customization from code, when a stored remap appears to do nothing, or when validating a custom binding payload.
---

# Skill — personal customization

Requires `config('shortcut-keys.customization') === 'personal'`. In `locked` mode every write throws.

Admins normally do all of this through the manage-shortcuts page. Drive it from code only when you are
building your own UI on top.

## The use case

```php
use Aristonis\FilamentShortcutKeys\Application\EditUserMap;

$editor = app(EditUserMap::class);

$type = $user->getMorphClass();
$id   = (string) $user->getKey();

$editor->remap($type, $id, 'admin', 'navigation:'.OrderResource::class, 'o');
$editor->disable($type, $id, 'admin', 'global:export');
$editor->enable($type, $id, 'admin', 'global:export');   // drops the override, back to convention
$editor->addCustomBinding($type, $id, 'admin', 'custom:reports', 'j', ['route' => '/admin/orders']);
```

**Derive the owner from the authenticated principal, never from request input.** These arguments
identify whose map is written; taking them from a path or body parameter is a cross-user write.

## Copy on write

An admin never edits the shared map. Their first write forks it into a private copy, repoints their
selection, and stores the change there — the shared map is untouched.

A forked map holds **only differences**. Everything it does not mention falls through to the live
conventions, which is why a resource added next month still appears in a map customized today.

`enable()` removes the override rather than storing a no-op row, so the shortcut returns to whatever
the convention currently says instead of freezing at today's letter.

## Custom binding payloads

Exactly one of two shapes, validated on the way in:

```php
['selector' => '#refresh-button']   // clicks an element already on the page
['route'    => '/admin/orders']     // navigates
```

Rules the validator enforces:

- The target must be namespaced `custom:`.
- The payload must be **exactly one** key — `selector` or `route`, never both, never extra keys.
- The value must be a non-empty string.
- A route must be **host-relative**, starting with a single `/`. A scheme or `//` is rejected, which
  blocks off-site redirects and `javascript:` URIs on the client's navigate path.

This shape *is* the security boundary. A binding can only click something the page already rendered or
visit a route the app already guards — an unauthorized control is never rendered, and an unauthorized
route is stopped by middleware. Never widen this to accept a Livewire method or a raw server action.

Custom bindings are only accepted by the custom set. A payload attached to any other set is ignored, so
it cannot displace a real shortcut.

## When a stored edit does nothing

- **You remapped a table or row-action key.** Those sets are bare keys with no modifier, and a forced
  letter needs a modifier to attach to, so the override is stored and then discarded at resolve time.
  Only navigation, actions and custom bindings can be remapped.
- **The forced letter is already taken in that modifier pool.** The remap is dropped rather than
  displacing the existing shortcut.
- **The letter is not a–z.** Rejected during resolution.
- **The keymap looks stale.** Every write bumps the map version, which is what busts the cache. Writing
  to the tables directly without bumping serves the old map.

## Switching maps

```php
app(\Aristonis\FilamentShortcutKeys\Application\SelectActiveMap::class)
    ->select($type, $id, 'admin', $mapId);
```

One active map per admin per panel, enforced by a unique constraint. Selecting a map that is neither a
system map for that panel nor the admin's own custom map gets a not-found.
