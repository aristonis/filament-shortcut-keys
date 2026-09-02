<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Fakes;

use Illuminate\Contracts\Cache\Store;

/**
 * A cache store that serializes on write and refuses to hydrate any class on read, so a stored object
 * graph comes back as __PHP_Incomplete_Class.
 *
 * That is what Laravel 13 does out of the box: the `cache.serializable_classes` default is an empty
 * allowlist. The framework's own ArrayStore only grew the matching constructor argument mid-12.x, so
 * building this fixture from ArrayStore would fail on the Laravel 11 and lowest-12 CI jobs. The
 * behaviour under test is the incomplete-class hydration, not any particular store, so the fake spells
 * it out directly and stays version-independent.
 *
 * Expiry is not modelled: nothing here tests TTL, and every entry lives for the length of one test.
 */
class ClassRestrictedCacheStore implements Store
{
    /** @var array<string, string> */
    private array $storage = [];

    public function get($key)
    {
        if (! array_key_exists($key, $this->storage)) {
            return null;
        }

        return unserialize($this->storage[$key], ['allowed_classes' => false]);
    }

    public function many(array $keys)
    {
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->get($key);
        }

        return $values;
    }

    public function put($key, $value, $seconds)
    {
        $this->storage[$key] = serialize($value);

        return true;
    }

    public function putMany(array $values, $seconds)
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value, $seconds);
        }

        return true;
    }

    public function increment($key, $value = 1)
    {
        $incremented = (int) $this->get($key) + $value;

        $this->put($key, $incremented, 0);

        return $incremented;
    }

    public function decrement($key, $value = 1)
    {
        return $this->increment($key, $value * -1);
    }

    public function forever($key, $value)
    {
        return $this->put($key, $value, 0);
    }

    /** Declared for Laravel 13's Store contract; harmless on 11 and 12, which do not require it. */
    public function touch($key, $seconds)
    {
        return array_key_exists($key, $this->storage);
    }

    public function forget($key)
    {
        unset($this->storage[$key]);

        return true;
    }

    public function flush()
    {
        $this->storage = [];

        return true;
    }

    public function getPrefix()
    {
        return '';
    }
}
