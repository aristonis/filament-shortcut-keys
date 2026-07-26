<?php

namespace Aristonis\FilamentShortcutKeys\Filament\Pages;

use Aristonis\FilamentShortcutKeys\Application\AuthorSystemMap;
use Aristonis\FilamentShortcutKeys\Application\EditUserMap;
use Aristonis\FilamentShortcutKeys\Application\SelectActiveMap;
use Aristonis\FilamentShortcutKeys\Authorization\AuthorityGate;
use Aristonis\FilamentShortcutKeys\Core\Contracts\ListMaps;
use Aristonis\FilamentShortcutKeys\Core\Contracts\MapEditor;
use Aristonis\FilamentShortcutKeys\Core\Contracts\MapSelector;
use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\SystemMapAuthor;
use Aristonis\FilamentShortcutKeys\Core\Enums\MapType;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapEntryEdit;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapSummary;
use Aristonis\FilamentShortcutKeys\Exceptions\ShortcutKeysException;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;

/**
 * The shortcut map manager. Personal-mode admins pick an active map and customize their own keys;
 * an admin the authority gate trusts can also author the shared system presets. Each section is
 * shown only when the current mode or gate permits it, and every write runs through the same
 * application use-cases the rest of the plugin uses.
 */
final class ManageShortcuts extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 101;

    protected string $view = 'filament-shortcut-keys::pages.manage-shortcuts';

    /** @var MapSummary[]|null per-render cache; the view and every action option list read the same list */
    private ?array $mapSummaries = null;

    public static function getNavigationLabel(): string
    {
        return trans('filament-shortcut-keys::shortcut-keys.manage.nav_label');
    }

    public function getTitle(): string
    {
        return trans('filament-shortcut-keys::shortcut-keys.manage.title');
    }

    /** Hide the page entirely unless the visitor can actually do something here. */
    public static function canAccess(): bool
    {
        if (self::modeIsPersonal()) {
            return true;
        }

        return app(AuthorityGate::class)->allowsAuthoring(Filament::auth()->user(), Filament::getCurrentPanel()->getId());
    }

    /**
     * @return array{maps: array<int, array{label: string, isActive: bool, isDefault: bool}>, isPersonal: bool, canAuthor: bool}
     */
    protected function getViewData(): array
    {
        $preset = 0;

        $maps = array_map(
            fn (MapSummary $map): array => [
                'label' => $this->mapLabel($map, $map->type === MapType::SYSTEM && ! $map->isDefault ? ++$preset : 0),
                'isActive' => $map->isActive,
                'isDefault' => $map->isDefault,
            ],
            $this->mapSummaries(),
        );

        return [
            'maps' => $maps,
            'isPersonal' => self::modeIsPersonal(),
            'canAuthor' => $this->canAuthor(),
        ];
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        if (self::modeIsPersonal()) {
            $actions = [
                $this->changeActiveMapAction(),
                $this->remapAction(),
                $this->disableAction(),
                $this->resetAction(),
                $this->addCustomBindingAction(),
            ];
        }

        if ($this->canAuthor()) {
            $actions = [...$actions, $this->createPresetAction(), $this->clonePresetAction(), $this->editPresetAction()];
        }

        return $actions;
    }

    private function changeActiveMapAction(): Action
    {
        return Action::make('changeActiveMap')
            ->label(trans('filament-shortcut-keys::shortcut-keys.manage.change_active'))
            ->schema([
                Select::make('map')->label(self::fieldLabel('map'))
                    ->options($this->mapOptions())->required(),
            ])
            ->action(fn (array $data) => $this->run(function () use ($data): void {
                [$type, $id] = $this->owner();
                (new SelectActiveMap(app(MapSelector::class), $this->mode()))
                    ->select($type, $id, $this->panelId(), (int) $data['map']);
            }));
    }

    private function remapAction(): Action
    {
        return Action::make('remap')
            ->label(trans('filament-shortcut-keys::shortcut-keys.manage.remap'))
            ->schema([
                Select::make('target')->label(self::fieldLabel('target'))->options($this->targetOptions())->required(),
                TextInput::make('letter')->label(self::fieldLabel('letter'))->required()->maxLength(1),
            ])
            ->action(fn (array $data) => $this->run(function () use ($data): void {
                [$type, $id] = $this->owner();
                $this->editor()->remap($type, $id, $this->panelId(), $data['target'], strtolower(trim($data['letter'])));
            }));
    }

    private function disableAction(): Action
    {
        return Action::make('disable')
            ->label(trans('filament-shortcut-keys::shortcut-keys.manage.disable'))
            ->schema([Select::make('target')->label(self::fieldLabel('target'))->options($this->targetOptions())->required()])
            ->action(fn (array $data) => $this->run(function () use ($data): void {
                [$type, $id] = $this->owner();
                $this->editor()->disable($type, $id, $this->panelId(), $data['target']);
            }));
    }

    private function resetAction(): Action
    {
        return Action::make('reset')
            ->label(trans('filament-shortcut-keys::shortcut-keys.manage.reset'))
            ->schema([Select::make('target')->label(self::fieldLabel('target'))->options($this->targetOptions())->required()])
            ->action(fn (array $data) => $this->run(function () use ($data): void {
                [$type, $id] = $this->owner();
                $this->editor()->enable($type, $id, $this->panelId(), $data['target']);
            }));
    }

    private function addCustomBindingAction(): Action
    {
        return Action::make('addCustomBinding')
            ->label(trans('filament-shortcut-keys::shortcut-keys.manage.add_custom'))
            ->schema([
                TextInput::make('name')->label(self::fieldLabel('name'))->required()->maxLength(64),
                TextInput::make('letter')->label(self::fieldLabel('letter'))->required()->maxLength(1),
                Select::make('kind')->label(self::fieldLabel('kind'))
                    ->options((array) trans('filament-shortcut-keys::shortcut-keys.manage.kinds'))
                    ->default('route')->required(),
                TextInput::make('value')->label(self::fieldLabel('value'))->required(),
            ])
            ->action(fn (array $data) => $this->run(function () use ($data): void {
                [$type, $id] = $this->owner();
                $this->editor()->addCustomBinding(
                    $type,
                    $id,
                    $this->panelId(),
                    'custom:' . trim($data['name']),
                    strtolower(trim($data['letter'])),
                    [$data['kind'] => trim($data['value'])],
                );
            }));
    }

    private function createPresetAction(): Action
    {
        return Action::make('createPreset')
            ->label(trans('filament-shortcut-keys::shortcut-keys.manage.create_preset'))
            ->action(fn () => $this->run(fn () => $this->author()->createPreset($this->user(), $this->panelId())));
    }

    private function clonePresetAction(): Action
    {
        return Action::make('clonePreset')
            ->label(trans('filament-shortcut-keys::shortcut-keys.manage.clone_preset'))
            ->schema([Select::make('source')->label(self::fieldLabel('source'))->options($this->presetOptions())->required()])
            ->action(fn (array $data) => $this->run(
                fn () => $this->author()->clonePreset($this->user(), $this->panelId(), (int) $data['source']),
            ));
    }

    private function editPresetAction(): Action
    {
        return Action::make('editPreset')
            ->label(trans('filament-shortcut-keys::shortcut-keys.manage.edit_preset'))
            ->schema([
                Select::make('map')->label(self::fieldLabel('map'))->options($this->presetOptions())->required(),
                Select::make('target')->label(self::fieldLabel('target'))->options($this->targetOptions())->required(),
                TextInput::make('letter')->label(self::fieldLabel('letter'))->required()->maxLength(1),
                Checkbox::make('confirmed')->label(trans('filament-shortcut-keys::shortcut-keys.manage.edit_preset_confirm'))->accepted()->required(),
            ])
            ->action(fn (array $data) => $this->run(function () use ($data): void {
                $this->author()->editInPlace(
                    $this->user(),
                    $this->panelId(),
                    (int) $data['map'],
                    new MapEntryEdit($data['target'], strtolower(trim($data['letter'])), false),
                    (bool) $data['confirmed'],
                );
            }));
    }

    /** Without an explicit label Filament humanizes the field name, which is always English. */
    private static function fieldLabel(string $field): string
    {
        return trans("filament-shortcut-keys::shortcut-keys.manage.fields.$field");
    }

    private function run(callable $write): void
    {
        try {
            $write();
            Notification::make()->title(trans('filament-shortcut-keys::shortcut-keys.manage.saved'))->success()->send();
        } catch (ShortcutKeysException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        } catch (ModelNotFoundException | QueryException) {
            // A scope guard (a tampered or stale map id) or a concurrent selection race surfaces
            // here; show a generic failure rather than leaking a framework message or a 500.
            Notification::make()->title(trans('filament-shortcut-keys::shortcut-keys.manage.failed'))->danger()->send();
        }
    }

    private function editor(): EditUserMap
    {
        return new EditUserMap(app(MapEditor::class), $this->mode());
    }

    private function author(): AuthorSystemMap
    {
        return new AuthorSystemMap(app(AuthorityGate::class), app(SystemMapAuthor::class));
    }

    /** @return array<string, string> target identity => label, for the navigation shortcuts a user can remap */
    private function targetOptions(): array
    {
        $options = [];
        foreach (app(NavigationProvider::class)->items($this->panelId()) as $item) {
            $options['navigation:' . $item->structureKey] = $item->label;
        }

        return $options;
    }

    /** @return array<int, string> map id => display label */
    private function mapOptions(): array
    {
        $options = [];
        $preset = 0;

        foreach ($this->mapSummaries() as $map) {
            $options[$map->id] = $this->mapLabel($map, $map->type === MapType::SYSTEM && ! $map->isDefault ? ++$preset : 0);
        }

        return $options;
    }

    /** @return array<int, string> system preset id => label, for cloning and editing */
    private function presetOptions(): array
    {
        $options = [];
        $preset = 0;

        foreach ($this->mapSummaries() as $map) {
            if ($map->type !== MapType::SYSTEM) {
                continue;
            }
            $options[$map->id] = $this->mapLabel($map, $map->isDefault ? 0 : ++$preset);
        }

        return $options;
    }

    /** @return MapSummary[] */
    private function mapSummaries(): array
    {
        [$type, $id] = $this->owner();

        return $this->mapSummaries ??= app(ListMaps::class)->available($type, $id, $this->panelId());
    }

    private function mapLabel(MapSummary $map, int $preset): string
    {
        if ($map->type === MapType::CUSTOM) {
            return trans('filament-shortcut-keys::shortcut-keys.manage.map_custom');
        }

        if ($map->isDefault) {
            return trans('filament-shortcut-keys::shortcut-keys.manage.map_default');
        }

        return trans('filament-shortcut-keys::shortcut-keys.manage.map_preset', ['number' => $preset]);
    }

    private function canAuthor(): bool
    {
        return app(AuthorityGate::class)->allowsAuthoring($this->user(), $this->panelId());
    }

    private function user(): ?Authenticatable
    {
        return Filament::auth()->user();
    }

    /** @return array{0: string, 1: string} the acting user's morph type and id */
    private function owner(): array
    {
        $user = $this->user();

        return [
            $user instanceof Model ? $user->getMorphClass() : '',
            (string) ($user?->getAuthIdentifier() ?? ''),
        ];
    }

    private function panelId(): string
    {
        return Filament::getCurrentPanel()->getId();
    }

    private function mode(): string
    {
        return config('shortcut-keys.customization', 'personal');
    }

    private static function modeIsPersonal(): bool
    {
        return config('shortcut-keys.customization', 'personal') === 'personal';
    }
}
