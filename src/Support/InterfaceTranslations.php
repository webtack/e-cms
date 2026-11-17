<?php

namespace Webtack\Ecms\Support;

use Webtack\Ecms\EcmsServiceProvider;
use Webtack\Ecms\Http\Middleware\HandleInertiaRequests;
use Webtack\Ecms\Http\Middleware\SetLocale;

/**
 * Interface translations of the admin panel and the public site: the JSON dictionary `lang/{code}.json`
 * of the package, overlaid with the host's file of the same code (the host's keys win).
 *
 * Both {@see SetLocale} (does a language have translations?) and
 * {@see HandleInertiaRequests} (the dictionary sent to the client) use it,
 * so they always agree. Only the JSON dictionary is merged here; the PHP groups `auth.php` and `validation.php`
 * are loaded by the framework (see {@see EcmsServiceProvider}).
 *
 * @example
 *   InterfaceTranslations::has('uk');      // true: the package ships lang/uk.json
 *   InterfaceTranslations::has('xx');      // false
 *   InterfaceTranslations::messages('uk'); // object with the package keys, host keys on top
 */
final class InterfaceTranslations
{
    /** The package's `lang` directory. */
    public static function packagePath(): string
    {
        return dirname(__DIR__, 2).'/lang';
    }

    /**
     * Whether the host or the package has a JSON dictionary for the code.
     *
     * @example InterfaceTranslations::has('en'); // true
     */
    public static function has(string $code): bool
    {
        return self::validCode($code)
            && (is_file(lang_path($code.'.json')) || is_file(self::packagePath().'/'.$code.'.json'));
    }

    /**
     * The dictionary of a locale as an object (so an empty one is serialised as `{}`), the host's keys over
     * the package's. A missing or invalid file gives an empty object.
     *
     * @example InterfaceTranslations::messages('uk')->{'Save'}; // 'Зберегти'
     */
    public static function messages(string $code): object
    {
        if (! self::validCode($code)) {
            return (object) [];
        }

        return (object) array_merge(
            self::read(self::packagePath().'/'.$code.'.json'),
            self::read(lang_path($code.'.json')),
        );
    }

    /** A locale code is used in a file name, so it is limited to a safe character set. */
    private static function validCode(string $code): bool
    {
        return preg_match('/^[A-Za-z0-9_-]+$/', $code) === 1;
    }

    /** @return array<string, mixed> */
    private static function read(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }
}
