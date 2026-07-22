<?php

namespace Aristonis\FilamentShortcutKeys\Core\Resolution;

/** The final keymap for one page: shortcut groups (one per set), serialized for the client via toArray(). */
final readonly class ResolvedMap
{
    public function __construct(
        private array $groups
    ) {}

    public function groups(): array
    {
        return $this->groups;
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
