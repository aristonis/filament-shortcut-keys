<?php

namespace Aristonis\FilamentShortcutKeys\Filament;

use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\NavItem;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Facades\Log;
use LogicException;

final class FilamentNavigationProvider implements NavigationProvider
{
    /** @var array<string, NavItem[]> */
    private array $items = [];

    /** @var array<string, class-string[]> */
    private array $targets = [];

    public function items(string $panelId): array
    {
        // Building each NavItem calls getIndexUrl()/getUrl() per resource; this provider is scoped to
        // the request and both the keymap serializer and the overlay ask for the same list, so memoize
        // it and pay that cost once per request.
        return $this->items[$panelId] ??= array_values(array_filter(array_map(
            fn ($target) => $this->itemFor($target),
            $this->targets($panelId)
        )));
    }

    /** Null for a target whose URL needs a parameter: a key has nothing to open, and one such page must not fail every render. */
    private function itemFor(string $target): ?NavItem
    {
        try {
            return new NavItem($target, $target::getNavigationLabel(), $this->urlFor($target));
        } catch (UrlGenerationException | LogicException $e) {
            Log::warning("Shortcut keys: [{$target}] gets no navigation key because its URL cannot be built without parameters.", ['exception' => $e->getMessage()]);

            return null;
        }
    }

    public function versionToken(string $panelId): string
    {
        // Letters follow this order, so hashing the ordered labels covers every input that moves one:
        // sorts, groups, sidebar visibility and the include_hidden option.
        $data = array_map(fn ($t) => [$t, $t::getNavigationLabel()], $this->targets($panelId));

        return hash('xxh128', json_encode($data));
    }

    public function registeredTargets(string $panelId): array
    {
        $panel = Filament::getPanel($panelId);

        return [...$panel->getPages(), ...$panel->getResources()];
    }

    private function targets(string $panelId): array
    {
        // The resolver asks for the version token on every render, so order once per request.
        return $this->targets[$panelId] ??= (new NavigationOrder)->order(
            $this->registeredTargets($panelId),
            Filament::getPanel($panelId)->getNavigationGroups(),
            config()->boolean('shortcut-keys.navigation.include_hidden', false),
            $this->pinnedTargets(),
        );
    }

    /** @return string[] navigation targets the overlay pins a letter to, which opts a hidden one in */
    private function pinnedTargets(): array
    {
        $pinned = [];
        foreach (config('shortcut-keys.overlay', []) as $identity => $rule) {
            if (str_starts_with((string) $identity, 'navigation:') && isset($rule['letter']) && ! ($rule['disabled'] ?? false)) {
                $pinned[] = substr((string) $identity, strlen('navigation:'));
            }
        }

        return $pinned;
    }

    private function urlFor($target): string
    {
        return is_subclass_of($target, Resource::class)
            ? $target::getIndexUrl()
            : $target::getUrl();
    }
}
