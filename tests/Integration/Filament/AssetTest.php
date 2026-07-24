<?php

it('loads the shortcut-keys script on a panel page', function () {
    $html = (string) $this->get('/admin')->assertOk()->getContent();
    expect($html)->toContain('filament-shortcut-keys.js');
});
