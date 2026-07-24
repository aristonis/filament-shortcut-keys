<?php

use Aristonis\FilamentShortcutKeys\Authorization\AuthorityGate;
use Aristonis\FilamentShortcutKeys\Authorization\ConfigAuthorityGate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/** A throwaway authenticatable — the gate only forwards it, it never inspects identity. */
function fakeUser(): Authenticatable
{
    return new class implements Authenticatable
    {
        public function getAuthIdentifierName()
        {
            return 'id';
        }

        public function getAuthIdentifier()
        {
            return 1;
        }

        public function getAuthPasswordName()
        {
            return 'password';
        }

        public function getAuthPassword()
        {
            return '';
        }

        public function getRememberToken()
        {
            return '';
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName()
        {
            return '';
        }
    };
}

it('is an AuthorityGate', function () {
    expect(new ConfigAuthorityGate)->toBeInstanceOf(AuthorityGate::class);
});

it('denies authoring when authorize config is null', function () {
    config()->set('shortcut-keys.authorize', null);

    expect((new ConfigAuthorityGate)->allowsAuthoring(fakeUser(), 'admin'))->toBeFalse();
});

it('denies authoring when authorize config is absent', function () {
    config()->offsetUnset('shortcut-keys');

    expect((new ConfigAuthorityGate)->allowsAuthoring(fakeUser(), 'admin'))->toBeFalse();
});

it('honors a closure and passes it the user and panel id', function () {
    $captured = [];
    config()->set('shortcut-keys.authorize', function (?Authenticatable $user, string $panelId) use (&$captured) {
        $captured = [$user, $panelId];

        return true;
    });

    $user = fakeUser();

    expect((new ConfigAuthorityGate)->allowsAuthoring($user, 'admin'))->toBeTrue()
        ->and($captured[0])->toBe($user)
        ->and($captured[1])->toBe('admin');
});

it('denies when the closure returns false', function () {
    config()->set('shortcut-keys.authorize', fn () => false);

    expect((new ConfigAuthorityGate)->allowsAuthoring(fakeUser(), 'admin'))->toBeFalse();
});

it('resolves a Gate ability name against the user', function () {
    Gate::define('edit-shortcuts', fn ($user, string $panelId) => $panelId === 'admin');
    config()->set('shortcut-keys.authorize', 'edit-shortcuts');

    $gate = new ConfigAuthorityGate;
    $user = fakeUser();

    expect($gate->allowsAuthoring($user, 'admin'))->toBeTrue()
        ->and($gate->allowsAuthoring($user, 'blog'))->toBeFalse();
});

it('denies a Gate ability name when there is no authenticated user', function () {
    Gate::define('edit-shortcuts', fn ($user) => true);
    config()->set('shortcut-keys.authorize', 'edit-shortcuts');

    expect((new ConfigAuthorityGate)->allowsAuthoring(null, 'admin'))->toBeFalse();
});

it('denies when a closure returns a truthy non-boolean (no fail-open)', function () {
    // A dev returning a truthy non-bool must not silently grant authoring.
    config()->set('shortcut-keys.authorize', fn () => 1);

    expect((new ConfigAuthorityGate)->allowsAuthoring(fakeUser(), 'admin'))->toBeFalse();
});

it('denies for unexpected authorize config types', function (mixed $rule) {
    config()->set('shortcut-keys.authorize', $rule);

    expect((new ConfigAuthorityGate)->allowsAuthoring(fakeUser(), 'admin'))->toBeFalse();
})->with([
    'int truthy' => [1],
    'int zero' => [0],
    'bool true' => [true],
    'bool false' => [false],
    'array' => [['x']],
    'object' => [new stdClass],
]);
