<?php

namespace Aristonis\FilamentShortcutKeys\Filament;

use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\Resolution\ResolvedMap;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutBinding;

/**
 * Turns a resolved map into the JSON the browser needs. The pure map carries only keys and target
 * identities; the client also needs each group's handler and, per binding, how to fire it: navigate a
 * url, click a selector, or run a built-in table behaviour. Those are Filament concerns, so they are
 * resolved here from the same providers that fed discovery rather than inside the framework-agnostic core.
 */
final readonly class ClientMapSerializer
{
    /**
     * @param  array<string, string>  $handlerBySet  set key => the client handler that fires it (a
     *                                               custom row-action set, for instance, is fired by
     *                                               the table handler, which the set name alone can't
     *                                               tell the client)
     */
    public function __construct(
        private NavigationProvider $navigation,
        private PageContextProvider $page,
        private array $handlerBySet,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function serialize(ResolvedMap $map, string $panelId): array
    {
        $urls = $this->navigationUrls($panelId);
        $selectors = $this->actionSelectors();

        return array_map(
            fn ($group) => [
                'set' => $group->setKey,
                'handler' => $this->handlerBySet[$group->setKey] ?? $group->setKey,
                'modifier' => $group->modifier->toString(),
                'bindings' => array_map(
                    fn ($binding) => [
                        'target' => $binding->target->identity(),
                        'code' => $binding->keyCombo->code,
                        'activation' => $this->activation($binding, $group->setKey, $urls, $selectors),
                    ],
                    $group->bindings,
                ),
            ],
            $map->groups(),
        );
    }

    /**
     * @param  array<string, string>  $urls
     * @param  array<string, string>  $selectors
     * @return array<string, string>|null
     */
    private function activation(ShortcutBinding $binding, string $set, array $urls, array $selectors): ?array
    {
        $identity = $binding->target->identity();

        return match ($set) {
            'navigation' => isset($urls[$identity]) ? ['kind' => 'navigate', 'url' => $urls[$identity]] : null,
            'table' => ['kind' => 'table', 'behavior' => $binding->target->structureKey],
            'custom' => $this->customActivation($binding->payload),
            default => isset($selectors[$identity]) ? ['kind' => 'click', 'selector' => $selectors[$identity]] : null,
        };
    }

    /**
     * A custom binding carries its own action: a route to navigate or a selector to click.
     *
     * @param  array{selector: string}|array{route: string}|null  $payload
     * @return array<string, string>|null
     */
    private function customActivation(?array $payload): ?array
    {
        if (isset($payload['route'])) {
            return ['kind' => 'navigate', 'url' => $payload['route']];
        }

        if (isset($payload['selector'])) {
            return ['kind' => 'click', 'selector' => $payload['selector']];
        }

        return null;
    }

    /**
     * @return array<string, string> target identity => destination url
     */
    private function navigationUrls(string $panelId): array
    {
        $urls = [];
        foreach ($this->navigation->items($panelId) as $item) {
            $urls['navigation:' . $item->structureKey] = $item->url;
        }

        return $urls;
    }

    /**
     * @return array<string, string> target identity => element selector
     */
    private function actionSelectors(): array
    {
        $selectors = [];
        foreach ($this->page->actions() as $action) {
            $selectors['global:' . $action->name] = $action->selector;
        }
        foreach ($this->page->rowActions() as $action) {
            $selectors['row-action:' . $action->name] = $action->selector;
        }

        return $selectors;
    }
}
