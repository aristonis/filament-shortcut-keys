---
name: filament-shortcut-keys-custom-bindings
description: Let admins personalize their own shortcuts in aristonis/filament-shortcut-keys — remap a key, disable one, or add a binding pointing at a route or an on-page element — and understand the copy-on-write map model behind it. Use when driving personal customization from code, when a stored remap appears to do nothing, or when validating a custom binding payload.
---

# Skill — personal customization

Requires `config('shortcut-keys.customization') === 'personal'`. In `locked` or `config` mode every write throws.

Admins normally do all of this through the manage-shortcuts page. Drive it from code only when you are
building your own UI on top.

## The use case

```php
use Aristonis\FilamentShortcutKeys\Application\EditUserMap;
use Aristonis\FilamentShortcutKeys\Core\Contracts\MapEditor;
use App\Filament\Resources\OrderResource;

// The use case takes the customization mode as a plain string, so the container cannot build it
// on its own; construct it the way the manage page does.
$editor = new EditUserMap(app(MapEditor::class), config('shortcut-keys.customization'));

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

- **The letter falls on a reserved key in that pool** (`g`, `j`, `k` or another table key among bare
  keys). The override is stored but ignored, and the shortcut keeps its automatic letter.
- **The letter is not a–z.** Ignored during resolution; the shortcut keeps its automatic letter.
- **Another forced letter already holds that key** in the same pool: an overlay pin, another stored
  remap, or a row action's letter (row actions are lettered before the map applies, so theirs count
  as forced). The earlier one in resolution order keeps the key and the later shortcut is dropped;
  navigation comes before custom bindings, header actions before row actions. A forced letter does
  take its key from an automatically lettered shortcut, which simply moves to another letter.
- **The overlay disables that target.** An overlay disable wins over a stored letter. A letter the
  overlay merely pins is replaced by the stored one.
- **The selector does not match.** A selector that matches nothing on the page, or is not valid CSS,
  does nothing when pressed; the rest of the keymap keeps working.
- **The keymap looks stale.** Every write bumps the map version, which is what busts the cache. Writing
  to the tables directly without bumping serves the old map.

## Switching maps

```php
use Aristonis\FilamentShortcutKeys\Application\SelectActiveMap;
use Aristonis\FilamentShortcutKeys\Core\Contracts\MapSelector;

(new SelectActiveMap(app(MapSelector::class), config('shortcut-keys.customization')))
    ->select($type, $id, 'admin', $mapId);
```

One active map per admin per panel, enforced by a unique constraint. Selecting a map that is neither a
system map for that panel nor the admin's own custom map gets a not-found.
