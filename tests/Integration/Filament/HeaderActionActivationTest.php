<?php

use Aristonis\FilamentShortcutKeys\Tests\Support\Models\Order;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource;

/** The header-action bindings a page hands the client, keyed by target. */
function globalBindingsAt($test, string $url): array
{
    $html = (string) $test->get($url)->assertOk()->getContent();

    return collect(collect(injectedKeymap($html))->firstWhere('set', 'global')['bindings'])->keyBy('target')->all();
}

it('tells the client to open a header action url in a new tab when the action says so', function () {
    $order = Order::create(['name' => 'Probe order']);

    $bindings = globalBindingsAt($this, OrderResource::getUrl('view', ['record' => $order]));

    expect($bindings['global:print']['activation'])->toBe(['kind' => 'navigate', 'url' => '/print/order', 'newTab' => true])
        ->and($bindings['global:share']['activation'])->toBe(['kind' => 'navigate', 'url' => '/share/order', 'newTab' => true]);
});

it('leaves a same-tab header action url as a plain navigation', function () {
    $bindings = globalBindingsAt($this, OrderResource::getUrl('index'));

    expect($bindings['global:create']['activation'])->toBe(['kind' => 'navigate', 'url' => OrderResource::getUrl('create')]);
});
