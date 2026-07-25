<?php

namespace Aristonis\FilamentShortcutKeys\Filament;

use Aristonis\FilamentShortcutKeys\Core\Resolution\ResolvedMap;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\NavItem;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutTarget;

/**
 * Flattens a resolved map into rows the reference page can render: a human label and the display
 * tokens for each shortcut's key combo. Set names and other copy stay out of here so this can be
 * unit-tested without translations; the view localizes the set keys it returns.
 */
final class ReferenceCatalog
{
    private const MODIFIER_LABELS = ['ctrl' => 'Ctrl', 'alt' => 'Alt', 'shift' => 'Shift', 'meta' => 'Cmd'];

    private const CODE_LABELS = [
        'Slash' => '/',
        'ArrowUp' => '↑',
        'ArrowDown' => '↓',
        'ArrowLeft' => '←',
        'ArrowRight' => '→',
        'Enter' => 'Enter',
        'Space' => 'Space',
        'Delete' => 'Del',
        'Backspace' => 'Backspace',
    ];

    /**
     * @param  NavItem[]  $navItems  used to resolve a navigation target's label from its structure key
     * @return array<int, array{set: string, rows: array<int, array{label: string, keys: string[]}>}>
     */
    public static function build(ResolvedMap $map, array $navItems): array
    {
        $labels = [];
        foreach ($navItems as $item) {
            $labels[$item->structureKey] = $item->label;
        }

        $groups = [];

        foreach ($map->groups() as $group) {
            $rows = [];

            foreach ($group->bindings as $binding) {
                // A disabled or not-yet-assigned binding fires nothing, so it never appears in the reference.
                if (! $binding->enabled || $binding->keyCombo === null) {
                    continue;
                }

                $rows[] = [
                    'label' => self::labelFor($binding->target, $labels),
                    'keys' => self::keys($group->modifier, $binding->keyCombo->code),
                ];
            }

            if ($rows !== []) {
                $groups[] = ['set' => $group->setKey, 'rows' => $rows];
            }
        }

        return $groups;
    }

    /** @param  array<string, string>  $labels */
    private static function labelFor(ShortcutTarget $target, array $labels): string
    {
        if ($target->set === 'navigation') {
            return $labels[$target->structureKey] ?? $target->structureKey;
        }

        return $target->structureKey;
    }

    /** @return string[] */
    private static function keys(ModifierScheme $modifier, string $code): array
    {
        $keys = [];

        foreach (['ctrl', 'alt', 'shift', 'meta'] as $token) {
            if ($modifier->{$token}) {
                $keys[] = self::MODIFIER_LABELS[$token];
            }
        }

        $keys[] = self::CODE_LABELS[$code] ?? (str_starts_with($code, 'Key') ? substr($code, 3) : $code);

        return $keys;
    }
}
