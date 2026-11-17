<?php

namespace Webtack\Ecms\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Shared key-value store of application settings for the core and future modules.
 *
 * Keys look like `module.name` (lowercase letters, digits, `_`, `-`; at least two dot-separated
 * segments; up to 191 characters). Values are any JSON-compatible value, `null` included, so a
 * stored `null` differs from a missing key. Reads are cached in memory for the lifetime of the
 * instance and every write keeps that cache in sync; there is no cross-process cache.
 *
 * @example
 *   $settings = app(AppSettings::class);
 *   $settings->set('content.multilingual', true);
 *   $settings->get('content.multilingual', false); // true
 *   $settings->has('seo.title');                   // false
 *   $settings->prefix('content.');                 // ['content.multilingual' => true]
 *   $settings->forget('content.multilingual');
 */
class AppSettings
{
    public const KEY_PATTERN = '/^[a-z0-9_-]+(\.[a-z0-9_-]+)+$/';

    public const KEY_MAX_LENGTH = 191;

    /** @var array<string, mixed>|null Decoded values by key; null until first loaded. */
    private ?array $cache = null;

    /**
     * Get a setting, or $default when the key is absent.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->assertValidKey($key);
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /**
     * Whether the key exists, even when its stored value is null.
     */
    public function has(string $key): bool
    {
        $this->assertValidKey($key);

        return array_key_exists($key, $this->all());
    }

    /**
     * Create or overwrite a setting.
     *
     * @param  mixed  $value  Any JSON-encodable value.
     */
    public function set(string $key, mixed $value): void
    {
        $this->assertValidKey($key);
        $now = now();

        DB::table('app_settings')->upsert(
            [['key' => $key, 'value' => json_encode($value, JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now]],
            ['key'],
            ['value', 'updated_at'],
        );

        $this->all();
        $this->cache[$key] = $value;
    }

    /**
     * Remove a setting; a no-op when it does not exist.
     */
    public function forget(string $key): void
    {
        $this->assertValidKey($key);

        DB::table('app_settings')->where('key', $key)->delete();

        $this->all();
        unset($this->cache[$key]);
    }

    /**
     * All settings whose key starts with $prefix, as key => value pairs.
     *
     * @return array<string, mixed>
     */
    public function prefix(string $prefix): array
    {
        return array_filter(
            $this->all(),
            fn (string $key) => str_starts_with($key, $prefix),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Drop the in-memory cache so the next read hits the database.
     */
    public function flush(): void
    {
        $this->cache = null;
    }

    /**
     * @return array<string, mixed>
     */
    private function all(): array
    {
        if ($this->cache === null) {
            $this->cache = DB::table('app_settings')->pluck('value', 'key')
                ->map(fn (string $json) => json_decode($json, true))
                ->all();
        }

        return $this->cache;
    }

    /**
     * @throws ValidationException
     */
    private function assertValidKey(string $key): void
    {
        if (strlen($key) > self::KEY_MAX_LENGTH || ! preg_match(self::KEY_PATTERN, $key)) {
            throw ValidationException::withMessages([
                'key' => "Invalid settings key [{$key}]: expected module.name (a-z, 0-9, _, -), up to ".self::KEY_MAX_LENGTH.' characters.',
            ]);
        }
    }
}
