<?php

use Aristonis\FilamentShortcutKeys\Tests\Support\Models\Order;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource\Pages\ListOrders;
use Livewire\Livewire;

/*
 * The client's row cursor finds record rows and remembers them by wire:key, so a sort or a search that
 * re-renders the table cannot leave it pointing at a different record. Filament also renders non-record
 * rows (column search, group headers) with the same row class; this pins that record rows, and only
 * they, carry the key shape the client relies on.
 */
it('gives every record row, and only those, a wire:key ending in the record key', function () {
    $orders = collect(['Alpha', 'Bravo'])->map(fn (string $name) => Order::create(['name' => $name]));

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">' . Livewire::test(ListOrders::class)->html());
    libxml_clear_errors();
    libxml_use_internal_errors(false);

    $keys = collect((new DOMXPath($document))->query(
        '//tr[contains(concat(" ", normalize-space(@class), " "), " fi-ta-row ")][@*[name()="wire:key"][contains(., ".table.records.")]]',
    ))->map(fn (DOMElement $row) => $row->getAttribute('wire:key'));

    expect($keys)->toHaveCount($orders->count());

    foreach ($orders as $order) {
        expect($keys->contains(fn (string $key) => str_ends_with($key, ".table.records.{$order->getKey()}")))->toBeTrue();
    }

    $source = file_get_contents(__DIR__ . '/../../../resources/js/table.js');
    expect($source)->toContain("const ROW_SELECTOR = 'tr.fi-ta-row[wire\\\\:key*=\".table.records.\"]'");
});
