<?php

namespace Aristonis\FilamentShortcutKeys\Core\Resolution;

use Aristonis\FilamentShortcutKeys\Core\Enums\BindingSource;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\KeyCombo;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutBinding;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutTarget;

/** The final keymap for one page: shortcut groups (one per set), serialized for the client via toArray(). */
final readonly class ResolvedMap
{
    /**
     * Bumped whenever toCache()'s shape changes. It goes into the cache key, so an upgraded package
     * reads a miss rather than an entry written in a shape it no longer understands.
     */
    public const CACHE_FORMAT = 1;

    public function __construct(
        private array $groups
    ) {}

    public function groups(): array
    {
        return $this->groups;
    }

    /**
     * Concatenates this map's groups with another's into a new map, leaving both operands untouched.
     *
     * The two maps always cover disjoint sets (the panel-wide navigation map versus the page's own
     * sets), so groups never clash — a plain concatenation is correct and no dedup is needed.
     */
    public function merge(self $other): self
    {
        return new self(array_merge($this->groups, $other->groups()));
    }

    /**
     * The map as plain scalars and arrays, for storing in a cache.
     *
     * Deliberately not the object graph: a cache store that serializes hands whatever it stored to
     * unserialize(), and Laravel 13 restricts that to an allowlist of classes which defaults to empty.
     * Storing objects would make every host add the package's value objects to `serializable_classes`
     * before the panel could serve a second request. Primitives need no allowlist on any driver.
     *
     * Lossless, unlike toArray(): this is read back into live objects, so it carries the fields the
     * client payload has no use for (letter hint, custom-binding payload, target structure key).
     */
    public function toCache(): array
    {
        return array_map(
            fn (ResolvedGroup $group): array => [
                'set' => $group->setKey,
                'modifier' => self::modifierToCache($group->modifier),
                'bindings' => array_map(self::bindingToCache(...), $group->bindings),
            ],
            $this->groups(),
        );
    }

    public static function fromCache(array $groups): self
    {
        return new self(array_map(
            fn (array $group): ResolvedGroup => new ResolvedGroup(
                $group['set'],
                self::modifierFromCache($group['modifier']),
                array_map(self::bindingFromCache(...), $group['bindings']),
            ),
            $groups,
        ));
    }

    private static function bindingToCache(ShortcutBinding $binding): array
    {
        return [
            'set' => $binding->target->set,
            'structureKey' => $binding->target->structureKey,
            'code' => $binding->keyCombo?->code,
            'modifier' => $binding->keyCombo === null ? null : self::modifierToCache($binding->keyCombo->modifiers),
            'enabled' => $binding->enabled,
            'source' => $binding->source->value,
            'letterHint' => $binding->letterHint,
            'payload' => $binding->payload,
        ];
    }

    private static function bindingFromCache(array $binding): ShortcutBinding
    {
        return new ShortcutBinding(
            new ShortcutTarget($binding['set'], $binding['structureKey']),
            $binding['code'] === null
                ? null
                : new KeyCombo(self::modifierFromCache($binding['modifier']), $binding['code']),
            $binding['enabled'],
            BindingSource::from($binding['source']),
            $binding['letterHint'],
            $binding['payload'],
        );
    }

    /**
     * Stored as four flags rather than as the token string: an empty scheme (bare keys) stringifies to
     * "", and reading that back through fromTokens() would throw on the empty token.
     */
    private static function modifierToCache(ModifierScheme $modifier): array
    {
        return [
            'ctrl' => $modifier->ctrl,
            'alt' => $modifier->alt,
            'shift' => $modifier->shift,
            'meta' => $modifier->meta,
        ];
    }

    private static function modifierFromCache(array $modifier): ModifierScheme
    {
        return new ModifierScheme(
            ctrl: $modifier['ctrl'],
            alt: $modifier['alt'],
            shift: $modifier['shift'],
            meta: $modifier['meta'],
        );
    }

    public function toArray(): array
    {
        return array_map(
            fn ($group) => [
                'set' => $group->setKey,
                'modifier' => $group->modifier->toString(),
                'bindings' => array_map(
                    fn ($binding) => [
                        'target' => $binding->target->identity(),
                        'code' => $binding->keyCombo->code,
                        'enabled' => $binding->enabled,
                        'source' => $binding->source->value,
                    ],
                    $group->bindings
                ),
            ],
            $this->groups()
        );
    }
}
