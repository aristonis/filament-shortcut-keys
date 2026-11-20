<?php

namespace Aristonis\FilamentShortcutKeys\Filament\Pages;

use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Filament\OverlayCatalog;
use Aristonis\FilamentShortcutKeys\Filament\ReferenceCatalog;
use Aristonis\FilamentShortcutKeys\FilamentShortcutKeysPlugin;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;

/**
 * A read-only listing of the shortcuts that are the same everywhere in the panel for the current
 * admin: navigation, their own custom bindings, the table keys and the row-action letters. Header
 * actions vary per page, so those surface on each page and in the cheatsheet overlay instead.
 */
final class ShortcutReference extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-command-line';

    // A utility page, so it sits after the app's own resources rather than competing for the top.
    protected static ?int $navigationSort = 100;

    protected string $view = 'filament-shortcut-keys::pages.shortcut-reference';

    public static function getNavigationLabel(): string
    {
        return trans('filament-shortcut-keys::shortcut-keys.nav_label');
    }

    public function getTitle(): string
    {
        return trans('filament-shortcut-keys::shortcut-keys.title');
    }

    public function getSubheading(): string
    {
        return trans('filament-shortcut-keys::shortcut-keys.subheading');
    }

    /**
     * @return array{groups: array<int, array{set: string, rows: array<int, array{label: string, keys: string[], sequence: bool, presence?: string|null}>}>}
     */
    protected function getViewData(): array
    {
        $panelId = Filament::getCurrentPanel()->getId();

        $plugin = FilamentShortcutKeysPlugin::get();
        $user = Filament::auth()->user();

        $panelWide = ReferenceCatalog::build($plugin->resolvePanelWideMap($panelId, $user), app(NavigationProvider::class)->items($panelId));

        // Labelled the way the cheatsheet labels them, so a key reads the same in both places.
        $tables = OverlayCatalog::build(
            $plugin->resolveTableMap($panelId, $user),
            [],
            [],
            (array) trans('filament-shortcut-keys::shortcut-keys.table_behaviors'),
        );

        return [
            'groups' => [...$panelWide, ...$tables],
        ];
    }
}
