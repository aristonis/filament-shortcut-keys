<?php

namespace Aristonis\FilamentShortcutKeys\Core\Resolution;

use Aristonis\FilamentShortcutKeys\Core\Contracts\MapRepository;
use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\Resolver;
use Aristonis\FilamentShortcutKeys\Core\Enums\BindingSource;
use Aristonis\FilamentShortcutKeys\Core\Sets\ShortcutSetRegistry;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\KeyCombo;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutBinding;

final readonly class ShortcutResolver implements Resolver
{
    public function __construct(
        private ShortcutSetRegistry $registry,
        private array $overlay = [],
        private ?MapRepository $maps = null
    ) {}

    public function resolve(NavigationProvider $nav, PageContextProvider $page, string $panelId, string $authType = '', string $authId = ''): ResolvedMap
    {
        $bindings = $this->registry->discover($nav, $page, $panelId);
        $bindings = $this->applyOverlay($bindings);
        $bindings = $this->applyActiveMap($bindings, $panelId, $authType, $authId);
        $bindings = $this->keepEnabled($bindings);
        $bindings = $this->assignLetters($bindings);

        return $this->group($bindings);
    }

    /**
     * Applies developer config overrides. A forced letter is set as a fixed combo now, before
     * letter assignment, so LetterAssigner keeps it and routes other keys around it. A disable is
     * only flagged here; keepEnabled() removes it before assignment.
     */
    private function applyOverlay(array $bindings): array
    {
        $bindingsOverlaid = [];
        foreach ($bindings as $binding) {
            $rule = $this->overlay[$binding->target->identity()] ?? null;

            if ($rule === null) {
                $bindingsOverlaid[] = $binding;

                continue;
            }

            if ($rule['disabled'] ?? false) {
                $bindingsOverlaid[] = new ShortcutBinding(
                    $binding->target,
                    $binding->keyCombo,
                    false,
                    BindingSource::OVERLAY,
                    $binding->letterHint,
                );

                continue;
            }

            if (isset($rule['letter'])) {
                $scheme = $this->registry->get($binding->target->set)->defaultModifier();
                $combo = KeyCombo::parse($scheme->toString() . '+' . strtolower($rule['letter']));

                $bindingsOverlaid[] = new ShortcutBinding(
                    $binding->target,
                    $combo,
                    $binding->enabled,
                    BindingSource::OVERLAY,
                    $binding->letterHint,
                );

                continue;
            }

            $bindingsOverlaid[] = $binding;
        }

        return $bindingsOverlaid;
    }

    /**
     * Applies the user's active map over the convention bindings. A remap stores the full combo
     * the user chose; a disable is flagged (removed later by keepEnabled). Targets the map does not
     * mention fall through to convention unchanged.
     */
    private function applyActiveMap(array $bindings, string $panelId, string $authType = '', string $authId = ''): array
    {
        if ($this->maps === null) {
            return $bindings;
        }
        $entries = $this->maps->activeMap($authType, $authId, $panelId)->entries;
        $bindingsMapped = [];
        foreach ($bindings as $binding) {
            $entry = $entries[$binding->target->identity()] ?? null;

            if ($entry === null) {
                $bindingsMapped[] = $binding;

                continue;
            }

            if ($entry['disabled'] ?? false) {
                $bindingsMapped[] = new ShortcutBinding(
                    $binding->target,
                    $binding->keyCombo,
                    false,
                    BindingSource::USER,
                    $binding->letterHint,
                );

                continue;
            }

            if (isset($entry['key'])) {
                $bindingsMapped[] = new ShortcutBinding(
                    $binding->target,
                    KeyCombo::parse($entry['key']),
                    $binding->enabled,
                    BindingSource::USER,
                    $binding->letterHint,
                );

                continue;
            }

            $bindingsMapped[] = $binding;
        }

        return $bindingsMapped;
    }

    private function keepEnabled(array $bindings): array
    {
        // disabled shortcuts (overlay/user) never reach letter assignment or the client map
        return array_values(array_filter($bindings, fn ($binding) => $binding->enabled));
    }

    private function assignLetters(array $bindings): array
    {
        $pools = [];
        foreach ($bindings as $binding) {
            $scheme = $this->registry->get($binding->target->set)->defaultModifier();
            $key = $scheme->toString();
            $pools[$key][] = $binding;
        }
        $result = [];
        foreach ($pools as $key => $pool) {
            $scheme = $this->registry->get($pool[0]->target->set)->defaultModifier();
            $result = array_merge($result, (new LetterAssigner)->assign($scheme, $pool));
        }

        return $result;
    }

    private function group(array $bindings): ResolvedMap
    {
        $groups = [];
        foreach ($bindings as $binding) {
            $groups[$binding->target->set][] = $binding;
        }
        $resolvedGroups = [];
        foreach ($groups as $key => $group) {
            $resolvedGroups[] = new ResolvedGroup($key, $this->registry->get($key)->defaultModifier(), $group);

        }

        return new ResolvedMap($resolvedGroups);
    }
}
