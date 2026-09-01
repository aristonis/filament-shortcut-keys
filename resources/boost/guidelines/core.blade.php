# Aristonis Filament Shortcut Keys

`aristonis/filament-shortcut-keys` gives a Filament v5 panel keyboard shortcuts derived from what the
panel already declares. Registered resources and pages become navigation shortcuts, a page's header
actions become action shortcuts, and every table gets the same fixed keys. Adding a resource gives it
a shortcut; there is nothing to register per item.

## Orientation
- **Namespace:** `Aristonis\FilamentShortcutKeys\` (PSR-4, `src/`). Composer: `aristonis/filament-shortcut-keys`.
- **Entry point:** `FilamentShortcutKeysPlugin::make()` registered on a panel with `->plugin(...)`.
  There is no facade; the host does not call the resolver directly.
- **Config:** everything tunable lives under `config('shortcut-keys.*')`, read at call time.
- **Two admin-facing pages** ship with the plugin and register themselves in the panel: a shortcut
  reference page, and a manage-shortcuts page when personal customization is on.

For task recipes, the matching skill (`filament-shortcut-keys-usage`,
`filament-shortcut-keys-row-actions`, `filament-shortcut-keys-config`,
`filament-shortcut-keys-authorization`, `filament-shortcut-keys-custom-bindings`,
`filament-shortcut-keys-troubleshooting`) is loaded on demand.

## The hard rules (non-negotiable — violating them breaks the design contract)

### Security model
- **The keymap is public, and that is deliberate.** It is built from every registered page with no
  `canAccess` check, so all admins receive the same list regardless of permission. Do not "fix" this
  by filtering per user: it buys no security, and the cache is keyed on the map's identity rather than
  the viewer, so a per-viewer filter would make that key wrong for whoever it was built for.
- **Authorization is enforced when a key fires, never when the map is built.** A shortcut clicks a
  real Filament control or follows a real route. An unauthorized control is not rendered, so there is
  nothing to click; an unauthorized route is stopped by middleware.
- **Never invent a privileged action.** A binding must not call a Livewire method directly or trigger
  anything the user could not have clicked themselves.
- **A custom binding's payload is exactly one of two shapes:** a selector for an element already on
  the page, or a host-relative route (`/admin/orders`, never a scheme or protocol-relative URL).
  That restriction is the security boundary, not a convenience.
- **A keymap reveals which pages exist, not what an admin may do.** A page whose existence is itself
  sensitive does not belong in a panel's navigation.

### Architecture
- **`src/Core` is framework-free.** No Filament, Eloquent, or container imports, and no URLs or CSS
  selectors. Everything from outside arrives through a port the core declares itself. If you want an
  `Illuminate\` import in the core, what you need is a new port.
- **Extend by registering a set, never by editing the resolver** (OCP). A new category of shortcut is
  a `ShortcutSet` implementation added to a registry, not a branch in the resolution pipeline.
- **Transactions live in the persistence services**, never in models or callers.
- **The panel-wide and page registries must share no set key.** Their results are concatenated, and
  that is only sound while the keys stay disjoint.

### Shortcut behaviour
- **Identity is structural.** A shortcut is keyed on the resource class or a stable action name, never
  on a slug or label, so a stored override survives renaming and reordering.
- **A stored map holds only differences.** Anything it does not mention falls through to the live
  conventions, which is what lets a newly added resource appear in an already-customized map.
- **Bare-key sets cannot take a forced letter.** Table and row-action shortcuts have no modifier, so a
  remap aimed at them is discarded at resolve time. Only sets with a modifier can be remapped.
- **Every content change bumps the map's version.** That is what busts the cache; an edit that skips
  the bump serves the pre-edit keymap.
- **Keys match the physical key**, so they behave the same on any keyboard layout. Pagination arrows
  are mirrored server-side for a right-to-left panel so the key that fires matches the direction shown.

### Caching
- **The cache lifetime is about reclamation, not correctness.** The key is a fingerprint of everything
  that shapes the map, so freshness is already guaranteed by the key changing. What the key cannot do
  is delete the entry it moved away from. Do not set the lifetime to `null` on a store nobody manages.
