<?php

namespace Aristonis\FilamentShortcutKeys\Core\Resolution;

use Aristonis\FilamentShortcutKeys\Core\Contracts\MapRepository;
use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\Resolver;
use Aristonis\FilamentShortcutKeys\Core\Enums\BindingSource;
use Aristonis\FilamentShortcutKeys\Core\Sets\ShortcutSetRegistry;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\KeyCombo;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutBinding;
use Aristonis\FilamentShortcutKeys\Exceptions\UnknownShortcutSetException;

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
     * only flagged here; keepEnabled() removes it before assignment. An invalid forced letter is
     *  ignored, so one bad config value can't take down the whole map.
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
                $bindingsOverlaid[] = $this->disabled($binding, BindingSource::OVERLAY);

                continue;
            }

            $bindingsOverlaid[] = isset($rule['letter'])
                ? $this->forced($binding, (string) $rule['letter'], BindingSource::OVERLAY)
                : $binding;
        }

        return $bindingsOverlaid;
    }

    /**
     * Applies the user's active map over the convention bindings. A remap re-letters the shortcut
     * within its set's own modifier scheme; a disable is flagged (removed later by keepEnabled).
     * Targets the map does not mention fall through to convention unchanged.
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
                $bindingsMapped[] = $this->disabled($binding, BindingSource::USER);

                continue;
            }

            $bindingsMapped[] = isset($entry['letter'])
                ? $this->forced($binding, (string) $entry['letter'], BindingSource::USER)
                : $binding;
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
        // Pool by effective modifier scheme: only bindings that share a scheme can collide.
        $pools = [];
        foreach ($bindings as $binding) {
            $pools[$this->schemeFor($binding->target->set)->toString()][] = $binding;
        }

        $assigner = new LetterAssigner;
        $result = [];
        foreach ($pools as $pool) {
            $result = array_merge($result, $assigner->assign($this->schemeFor($pool[0]->target->set), $pool));
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
            $resolvedGroups[] = new ResolvedGroup($key, $this->schemeFor($key), $group);
        }

        return new ResolvedMap($resolvedGroups);
    }

    private function disabled(ShortcutBinding $binding, BindingSource $source): ShortcutBinding
    {
        return new ShortcutBinding($binding->target, $binding->keyCombo, false, $source, $binding->letterHint);
    }

    /** Forces a shortcut onto a specific letter, or returns it unchanged if the letter is unusable. */
    private function forced(ShortcutBinding $binding, string $letter, BindingSource $source): ShortcutBinding
    {
        $combo = $this->forcedCombo($this->schemeFor($binding->target->set), $letter);
        if ($combo === null) {
            return $binding;
        }

        return new ShortcutBinding($binding->target, $combo, $binding->enabled, $source, $binding->letterHint);
    }

    /** A letter forced onto a set's scheme, or null if the letter isn't a–z or the set has no modifier. */
    private function forcedCombo(ModifierScheme $scheme, string $letter): ?KeyCombo
    {
        if ($scheme->toString() === '' || ! preg_match('/^[a-z]$/i', $letter)) {
            return null;
        }

        return new KeyCombo($scheme, 'Key' . strtoupper($letter));
    }

    private function schemeFor(string $set): ModifierScheme
    {
        $shortcutSet = $this->registry->get($set);
        if ($shortcutSet === null) {
            throw UnknownShortcutSetException::forSet($set);
        }

        return $shortcutSet->defaultModifier();
    }
}
