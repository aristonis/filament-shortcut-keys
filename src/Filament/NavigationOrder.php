<?php

namespace Aristonis\FilamentShortcutKeys\Filament;

use Filament\Navigation\NavigationGroup;
use Filament\Resources\Resource;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Orders navigation targets the way Filament lays out the sidebar, so letters follow the order the
 * admin reads them.
 *
 * The ranking mirrors Filament's NavigationManager: a missing sort counts as -1, registered groups keep
 * the panel's order (an enum group ranks by its case), and unregistered groups follow in order of their
 * first item. Ties keep the input order, which callers pass as Filament mounts it: pages, then resources.
 *
 * Three tiers follow each other so nothing outside the main sidebar takes a letter a sidebar item would
 * get: the sidebar, then cluster members (shown in their cluster's sub-navigation), then hidden targets
 * when included or pinned. Resources Filament never links to, nested children and those without an
 * index page, are left out: they have no URL a key could open. canAccess() is deliberately ignored;
 * the keymap does not depend on permissions. A custom navigation builder is not mirrored.
 *
 * @internal
 */
final readonly class NavigationOrder
{
    /**
     * @param  class-string[]  $targets  resource and page classes
     * @param  array<int|string, string|NavigationGroup>  $registeredGroups  the panel's getNavigationGroups()
     * @param  bool  $includeHidden  whether targets hidden from the sidebar are kept, after every other one
     * @param  class-string[]  $pinned  hidden targets kept anyway, because the overlay pins them a letter
     * @return class-string[]
     */
    public function order(array $targets, array $registeredGroups, bool $includeHidden, array $pinned = []): array
    {
        $linkable = collect($targets)->reject(fn (string $target): bool => $this->hasNoPlainLink($target));

        [$shown, $hidden] = $linkable->partition(fn (string $target): bool => $target::shouldRegisterNavigation());
        [$clustered, $sidebar] = $shown->partition(fn (string $target): bool => filled($target::getCluster()));

        $keptHidden = $includeHidden
            ? $hidden
            : $hidden->filter(fn (string $target): bool => in_array($target, $pinned, true));

        return [
            ...$this->sidebarOrder($sidebar, $registeredGroups),
            ...$this->sidebarOrder($clustered, $registeredGroups),
            ...$this->sidebarOrder($keptHidden, $registeredGroups),
        ];
    }

    /** A nested child resource or one without an index page: Filament gives it no navigation link. */
    private function hasNoPlainLink(string $target): bool
    {
        return is_subclass_of($target, Resource::class)
            && ($target::getParentResourceRegistration() !== null || ! $target::hasPage('index'));
    }

    /** @return class-string[] */
    private function sidebarOrder(Collection $targets, array $registeredGroups): array
    {
        return $targets
            ->sortBy(fn (string $target): int => $target::getNavigationSort() ?? -1)
            ->groupBy(fn (string $target): string => serialize($target::getNavigationGroup()))
            ->sortBy(fn (Collection $group, string $key): int => $this->groupRank(unserialize($key), $registeredGroups))
            ->flatten()
            ->all();
    }

    private function groupRank(string | UnitEnum | null $group, array $registeredGroups): int
    {
        if (blank($group)) {
            return -1;
        }

        // Filament searches group keys and labels together when the panel registers NavigationGroup objects.
        // Searching a list ranks by position, so string-keyed groups cannot all collapse onto rank 0.
        $searchable = Arr::first($registeredGroups) instanceof NavigationGroup
            ? [
                ...array_keys($registeredGroups),
                ...array_map(fn (NavigationGroup $registered): string => $registered->getLabel(), array_values($registeredGroups)),
            ]
            : array_values($registeredGroups);

        $rank = array_search($group instanceof UnitEnum ? $group->name : $group, $searchable);

        if ($group instanceof UnitEnum) {
            $caseRank = array_search($group, $group::cases(), true);
            $rank = $caseRank !== false ? $caseRank : $rank;
        }

        return $rank === false ? count($registeredGroups) : $rank;
    }
}
