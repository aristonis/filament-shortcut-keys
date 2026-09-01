<?php

/** @return array<string, string> table behaviour => the physical key the client was told to match */
function tableKeys(string $html): array
{
    $group = collect(injectedKeymap($html))->firstWhere('set', 'table');

    return collect($group['bindings'] ?? [])
        ->mapWithKeys(fn (array $binding) => [$binding['target'] => $binding['code']])
        ->all();
}

it('binds the same table keys on two unrelated resources', function () {
    $orders = tableKeys((string) $this->get('/admin/orders')->assertOk()->getContent());
    $users = tableKeys((string) $this->get('/admin/users')->assertOk()->getContent());

    // UserResource declares only columns; it shares no table code with OrderResource, so an
    // identical key set proves the behaviours come from the plugin rather than per-resource wiring.
    expect($orders)->not->toBeEmpty()->and($users)->toBe($orders);
});

it('keys the row actions a resource declares and nothing more', function () {
    $rowActions = fn (string $html) => collect(
        collect(injectedKeymap($html))->firstWhere('set', 'row-action')['bindings'] ?? []
    )->pluck('target')->all();

    expect($rowActions((string) $this->get('/admin/orders')->getContent()))
        ->toEqualCanonicalizing(['row-action:approve', 'row-action:reject'])
        ->and($rowActions((string) $this->get('/admin/users')->getContent()))
        ->toBe([]);
});
