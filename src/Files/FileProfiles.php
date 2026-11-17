<?php

namespace Webtack\Ecms\Files;

use InvalidArgumentException;

/**
 * Reader of the upload profiles configured in config/files.php.
 *
 * @phpstan-type Profile array{disk: string, mimes: array<string, string>, max_size: int, max_width?: int|string|null, max_height?: int|string|null}
 *
 * @example
 *   $profiles = app(FileProfiles::class);
 *   $profiles->keys();               // ['image', 'document']
 *   $profiles->get('image')['disk']; // 'public'
 *   $profiles->get('nope');          // throws InvalidArgumentException
 */
class FileProfiles
{
    /**
     * Keys of all configured profiles.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys(config('files.profiles', []));
    }

    /**
     * Whether a profile with this key is configured.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, config('files.profiles', []));
    }

    /**
     * The profile with this key.
     *
     * @return Profile
     *
     * @throws InvalidArgumentException When the profile is not configured.
     */
    public function get(string $key): array
    {
        if (! $this->has($key)) {
            throw new InvalidArgumentException("Unknown file profile [{$key}].");
        }

        return config("files.profiles.{$key}");
    }
}
