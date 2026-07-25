<?php

namespace Aristonis\FilamentShortcutKeys\Filament\Pages;

use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Filament\ReferenceCatalog;
use Aristonis\FilamentShortcutKeys\FilamentShortcutKeysPlugin;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;

/**
 * A read-only listing of the panel-wide shortcuts (navigation and the user's own custom bindings)
 * for the current admin. Page-scoped shortcuts vary per page, so those surface on each page and in
 * the cheatsheet overlay instead of here.
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
     * @return array{groups: array<int, array{set: string, rows: array<int, array{label: string, keys: string[]}>}>}
     */
    protected function getViewData(): array
    {
        $panelId = Filament::getCurrentPanel()->getId();

        $map = FilamentShortcutKeysPlugin::get()->resolvePanelWideMap($panelId, Filament::auth()->user());

        return [
            'groups' => ReferenceCatalog::build($map, app(NavigationProvider::class)->items($panelId)),
        ];
    }
}
