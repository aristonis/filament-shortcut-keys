<?php

use Illuminate\Support\Facades\Blade;

/*
 * The client keeps bare keys away from modals and dropdowns by matching Filament's markup, and its
 * own tests run against a stub DOM that cannot know whether Filament still renders that markup. These
 * render the real components so a Filament upgrade that renames them fails here instead of silently
 * letting Enter edit the row behind a confirm.
 */

function rendered(string $template): DOMXPath
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">' . Blade::render($template));
    libxml_clear_errors();
    libxml_use_internal_errors(false);

    return new DOMXPath($document);
}

function keyboardOwnerSelectors(): array
{
    $source = file_get_contents(__DIR__ . '/../../../resources/js/keymap.js');

    preg_match('/const KEYBOARD_OWNERS = \[(.*?)\n\]/s', $source, $list);
    preg_match_all("/'([^']+)'/", $list[1] ?? '', $selectors);

    return $selectors[1];
}

it('finds a Filament modal by the role and aria-modal the client looks for', function () {
    $xpath = rendered('<x-filament::modal id="probe" heading="Probe">Body</x-filament::modal>');

    $modal = $xpath->query('//*[@role="dialog"][@aria-modal="true"]');

    expect($modal->count())->toBe(1)
        ->and(keyboardOwnerSelectors())->toContain('[role="dialog"]', '[aria-modal="true"]');
});

it('marks an open Filament modal with the class the client checks when focus stayed behind it', function () {
    $xpath = rendered('<x-filament::modal id="probe" heading="Probe">Body</x-filament::modal>');

    // The class is bound by Alpine at runtime, so the binding is what can be checked server-side, and
    // it has to sit on the same element as aria-modal for the client's combined selector to match.
    $bound = $xpath->query('//*[@aria-modal="true"][@*[name()="x-bind:class"][contains(., "\'fi-modal-open\': isOpen")]]');

    expect($bound->count())->toBe(1)
        ->and(file_get_contents(__DIR__ . '/../../../resources/js/keymap.js'))
        ->toContain("'.fi-modal-open[aria-modal=\"true\"]'");
});

it('finds a Filament dropdown panel by the class the client looks for', function () {
    $xpath = rendered(<<<'BLADE'
        <x-filament::dropdown>
            <x-slot name="trigger"><button type="button">Open</button></x-slot>
            <x-filament::dropdown.list><x-filament::dropdown.list.item>Item</x-filament::dropdown.list.item></x-filament::dropdown.list>
        </x-filament::dropdown>
        BLADE);

    $panel = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " fi-dropdown-panel ")]');

    expect($panel->count())->toBe(1)
        ->and(keyboardOwnerSelectors())->toContain('.fi-dropdown-panel');
});
