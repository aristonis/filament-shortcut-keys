<?php

namespace Aristonis\FilamentShortcutKeys\Commands;

use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Enums\TargetVerdict;
use Aristonis\FilamentShortcutKeys\Core\Maintenance\TargetAudit;
use Aristonis\FilamentShortcutKeys\Core\Sets\TableSet;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\NavItem;
use Aristonis\FilamentShortcutKeys\FilamentShortcutKeysPlugin;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMap;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMapEntry;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Removes stored overrides whose target no longer exists — the rows left behind when a resource,
 * page, or registered row action is deleted. Only sets that can be enumerated outside a request are
 * judged; page-scoped and user-defined targets are reported and left in place.
 */
class PruneOrphanedEntriesCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'shortcut-keys:prune
        {--panel=* : Panel ids to audit; defaults to every panel using this plugin}
        {--dry-run : List what would be removed without deleting anything}
        {--force : Delete without confirming, even in production}';

    protected $description = 'Remove stored shortcut overrides whose target no longer exists';

    public function handle(): int
    {
        $panels = $this->panelsToAudit();

        if ($panels === null) {
            return self::FAILURE;
        }

        $orphaned = [];
        $unverifiable = 0;

        foreach ($panels as $panel) {
            $audit = new TargetAudit($this->liveTargets($panel));

            // Chunked because a busy panel can hold an override row per user per shortcut, which is
            // far more than a maintenance pass should pull into memory at once.
            $this->entryQuery($panel->getId())->chunkById(
                1000,
                function (Collection $entries) use ($audit, &$orphaned, &$unverifiable): void {
                    foreach ($entries as $entry) {
                        match ($audit->verdict($entry->target)) {
                            TargetVerdict::ORPHANED => $orphaned[] = $entry,
                            TargetVerdict::UNVERIFIABLE => $unverifiable++,
                            TargetVerdict::LIVE => null,
                        };
                    }
                },
            );
        }

        $this->report($orphaned, $unverifiable);

        if ($orphaned === [] || $this->option('dry-run')) {
            return self::SUCCESS;
        }

        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $this->remove($orphaned);
        $this->info(count($orphaned) . ' orphaned override(s) removed.');

        return self::SUCCESS;
    }

    /**
     * @return Panel[]|null the panels to audit, or null when the caller named one this plugin does
     *                      not run on. Left to itself the command audits every panel that registered
     *                      the plugin; the others hold no shortcut data of ours to judge.
     */
    private function panelsToAudit(): ?array
    {
        $pluginId = $this->pluginId();
        $ids = $this->option('panel');

        if ($ids === []) {
            return array_values(array_filter(
                Filament::getPanels(),
                fn (Panel $panel) => $panel->hasPlugin($pluginId),
            ));
        }

        $panels = [];

        foreach ($ids as $id) {
            $panel = Filament::getPanels()[$id] ?? null;

            if ($panel === null) {
                $this->error("No panel is registered with the id [$id].");

                return null;
            }

            if (! $panel->hasPlugin($pluginId)) {
                $this->error("Panel [$id] does not use this plugin.");

                return null;
            }

            $panels[] = $panel;
        }

        return $panels;
    }

    /**
     * The sets a maintenance pass can enumerate. Anything absent here (custom bindings, page actions,
     * table row actions of the page being viewed) is deliberately left out so it is never judged dead.
     *
     * @return array<string, string[]>
     */
    private function liveTargets(Panel $panel): array
    {
        $plugin = $panel->getPlugin($this->pluginId());

        return [
            'navigation' => array_map(
                fn (NavItem $item) => $item->structureKey,
                app(NavigationProvider::class)->items($panel->getId()),
            ),
            'row-action' => $plugin instanceof FilamentShortcutKeysPlugin ? $plugin->getRowActions() : [],
            'table' => TableSet::behaviors(),
        ];
    }

    private function pluginId(): string
    {
        return app(FilamentShortcutKeysPlugin::class)->getId();
    }

    /** @return Builder<ShortcutMapEntry> */
    private function entryQuery(string $panelId): Builder
    {
        return ShortcutMapEntry::query()
            ->whereHas('map', fn (Builder $map) => $map->where('panel_id', $panelId));
    }

    /** @param  ShortcutMapEntry[]  $orphaned */
    private function remove(array $orphaned): void
    {
        $entryIds = array_map(fn (ShortcutMapEntry $entry) => $entry->id, $orphaned);
        $mapIds = array_unique(array_map(fn (ShortcutMapEntry $entry) => $entry->map_id, $orphaned));

        DB::transaction(function () use ($entryIds, $mapIds): void {
            ShortcutMapEntry::query()->whereIn('id', $entryIds)->delete();

            // A map whose entries changed must look different to the resolver's cache key, or every
            // request keeps serving the keymap that still carries the deleted overrides.
            ShortcutMap::query()->whereIn('id', $mapIds)->increment('version');
        });
    }

    /** @param  ShortcutMapEntry[]  $orphaned */
    private function report(array $orphaned, int $unverifiable): void
    {
        if ($orphaned === []) {
            $this->info('No orphaned overrides found.');
        } else {
            $this->table(
                ['Map', 'Target'],
                array_map(fn (ShortcutMapEntry $entry) => [$entry->map_id, $entry->target], $orphaned),
            );
        }

        if ($unverifiable > 0) {
            $this->line("$unverifiable override(s) target page-scoped or custom shortcuts and were left in place.");
        }
    }
}
