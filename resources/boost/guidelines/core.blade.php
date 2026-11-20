# Aristonis Filament Shortcut Keys

`aristonis/filament-shortcut-keys` gives a Filament v5 panel keyboard shortcuts derived from what the
panel already declares. Registered resources and pages become navigation shortcuts, a page's header
actions become action shortcuts, and the page's tables (its own, its relation managers', its table
widgets') get the same fixed keys. Adding a resource gives it a shortcut; there is nothing to register
per item.

## Orientation
- **Namespace:** `Aristonis\FilamentShortcutKeys\` (PSR-4, `src/`). Composer: `aristonis/filament-shortcut-keys`.
- **Entry point:** `FilamentShortcutKeysPlugin::make()` registered on a panel with `->plugin(...)`.
  There is no facade; the host does not call the resolver directly.
- **Requirements:** PHP 8.2+, Laravel 12 or 13, Filament 5.x (not 4). The client script must be
  published; without it the keymap is in the page but no key fires. An app set up with
  `filament:install` republishes it on every Composer install or update through `filament:upgrade`;
  otherwise run `php artisan filament:assets` after installing or updating.
- **Config:** `config('shortcut-keys.*')`, read at call time. The one setting that is not config is
  `->rowActions([...])`, a fluent method on the plugin.
- **Two admin-facing pages** ship with the plugin: a shortcut reference page, always registered, and a
  manage-shortcuts page that opens in `personal` mode, or in `locked` mode for a user the authoring
  gate grants. In `config` mode the manage page is not registered and nothing touches the database.

## Default keys
- **Navigation and custom bindings:** a leader key, `g` then a letter (config token `['g']`). The
  letter must follow within about a second and a half; `Esc` cancels.
- **Header actions:** bare letters, but only for actions that navigate or open a modal or
  confirmation first. An action that runs on the click itself gets no default key on bare letters.
  A hidden or disabled header action gets no key.
- **Table:** `/` search, `j`/`k` start and move a row cursor, `Esc` releases it, `Space` select,
  `Enter` edit, `Delete` delete; arrows move the cursor and page the table only while it is live.
  Relation managers and header, footer or dashboard table widgets get the same keys; `/` and paging
  act on the table the cursor is in. A table placed through a page's own `content()` schema or
  `Livewire::make()`, or a widget placed through `getWidgetsSchemaComponents()`, is not detected, so
  that page gets no table keys unless it is a table page itself. A held key fires an action once;
  moving the cursor (`j`, `k`, `↑`, `↓`) repeats while held, and `←`/`→` page once per press.
- **Row actions:** bare letters for names registered with `->rowActions([...])`, never on a key
  reserved in their pool (`g`, `j`, `k`, the table keys). No header action is lettered
  automatically onto one of their letters, on any page, even one with no table.
- Nothing holds `Alt`, `Ctrl` or `Cmd` by default; held modifiers are opt-in per set in `modifiers`.
- Users can switch every single-key shortcut off in the `?` cheatsheet; the choice is kept per
  browser under the local-storage key `filament-shortcut-keys:single-keys`.

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
- **Extend by registering a set, never by editing the resolver.** A new category of shortcut is
  a `ShortcutSet` implementation added to a registry, not a branch in the resolution pipeline. The
  ports in `Core\Contracts` that the pipeline runs on are `@internal`: not public API for host apps.
- **Atomic writes run in a transaction owned by the service doing the write** (the persistence
  services, or the prune command for its deletes), never in a model.
- **The panel-wide and page registries must share no set key.** Their results are concatenated, and
  that is only sound while the keys stay disjoint.

### Shortcut behaviour
- **Identity is structural.** A shortcut is keyed on the resource class or a stable action name, never
  on a slug or label, so a stored override survives renaming and reordering.
- **A stored map holds only differences.** Anything it does not mention falls through to the live
  conventions, which is what lets a newly added resource appear in an already-customized map.
- **A forced letter (an overlay pin or a stored remap) works on any set, bare ones included.** It is
  ignored, and the shortcut keeps its automatic letter, only when it is not a–z or its key is reserved
  in that pool (`g`, `j`, `k`, the table keys). Otherwise it takes its key from an automatically
  lettered shortcut, which moves on; when two forced letters land on one key, the later one is
  dropped. Row actions are lettered before the overlay applies, so their letters count as forced:
  pinning one onto a sibling row action's letter drops whichever of the two is later in
  `->rowActions([...])` (with `['approve', 'reject']`, pinning approve to `r` leaves reject with no
  key). Pin both, or pick a free letter. An overlay pin also switches on a header action that runs on
  the click itself. A stored remap replaces an overlay pin on the same target; an overlay disable
  cannot be undone by one. The manage page only offers navigation targets.
- **Every content change bumps the map's version.** That is what busts the cache; an edit that skips
  the bump serves the pre-edit keymap.
- **Keys match the physical key**, so they behave the same on any keyboard layout. Pagination arrows
  are mirrored server-side for a right-to-left panel so the key that fires matches the direction shown.

### Caching
- **The cache lifetime is about reclamation, not correctness.** The key is a fingerprint of the panel
  id, the navigation version, the active map's identity and version, the developer config (overlay,
  `modifiers`, reserved keys), the locale and a cache format version, so freshness is already
  guaranteed by the key changing. It does not include the user: admins on the same map share an
  entry. What the key cannot do is delete the entry it moved away from. Do not set the lifetime to
  `null` on a store nobody manages.
