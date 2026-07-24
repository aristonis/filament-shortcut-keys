<?php

namespace Aristonis\FilamentShortcutKeys\Filament;

use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\NavItem;
use Filament\Facades\Filament;
use Filament\Resources\Resource;

final class FilamentNavigationProvider implements NavigationProvider
{
    public function items(string $panelId): array
    {

        return array_map(
            fn ($target) => new NavItem(
                $target,
                $target::getNavigationLabel(),
                $this->urlFor($target),
            ),
            $this->targets($panelId)
        );
    }

    public function versionToken(string $panelId): string
    {
        $data = array_map(fn ($t) => [$t, $t::getNavigationLabel(), $t::getNavigationSort()], $this->targets($panelId));

        return hash('xxh128', json_encode($data));
    }

    private function targets(string $panelId): array
    {
        $panel = Filament::getPanel($panelId);
        $resources = $panel->getResources();
        $pages = $panel->getPages();
        $targets = array_merge($resources, $pages);

        return collect($targets)
            ->sortBy(fn ($target) => $target::getNavigationSort())
            ->values()
            ->all();
    }

    private function urlFor($target): string
    {
        return is_subclass_of($target, Resource::class)
            ? $target::getIndexUrl()
            : $target::getUrl();
    }
}
