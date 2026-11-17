<?php

namespace Webtack\Ecms\Menus;

/**
 * The single place that decides which menu item addresses are allowed.
 *
 * An address is up to 2048 characters without whitespace, control characters or a backslash and is either
 * relative (starts with `/` but not `//`, `#` or `?`) or absolute with a scheme from an allow-list
 * (`http`, `https`, `mailto`, `tel`). Anything else (`javascript:`, `data:`, `//host`, `about`) is rejected.
 * The address is not checked against existing pages.
 *
 * @example
 *   MenuUrl::isValid('/about');            // true
 *   MenuUrl::isValid('javascript:alert(1)'); // false
 *   MenuUrl::isValid('/\\evil.example');   // false (a backslash reads as a slash)
 *   MenuUrl::isExternal('https://example.com'); // true
 *   MenuUrl::isExternal('/about');         // false
 *   MenuUrl::isExternal(null);             // null
 */
final class MenuUrl
{
    /** Longest allowed address. */
    public const MAX_LENGTH = 2048;

    /** Schemes of absolute addresses. */
    public const SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Whether the string is an allowed address (an empty string is not: normalise it to null first).
     */
    public static function isValid(string $url): bool
    {
        if ($url === '' || strlen($url) > self::MAX_LENGTH || preg_match('/[\s\x00-\x1F\x7F\\\\]/u', $url) !== 0) {
            return false;
        }

        if (str_starts_with($url, '//')) {
            return false;
        }

        if (in_array($url[0], ['/', '#', '?'], true)) {
            return true;
        }

        $scheme = self::scheme($url);

        if ($scheme === null || ! in_array($scheme, self::SCHEMES, true)) {
            return false;
        }

        $rest = substr($url, strlen($scheme) + 1);

        if (in_array($scheme, ['http', 'https'], true)) {
            return str_starts_with($rest, '//') && (string) parse_url($url, PHP_URL_HOST) !== '';
        }

        return $rest !== '';
    }

    /**
     * `true` for an absolute address, `false` for a relative one, `null` when there is no address.
     */
    public static function isExternal(?string $url): ?bool
    {
        if ($url === null || $url === '') {
            return null;
        }

        return self::scheme($url) !== null && in_array(self::scheme($url), self::SCHEMES, true);
    }

    /** The lower-cased scheme before the first colon, or null when the string has none. */
    private static function scheme(string $url): ?string
    {
        return preg_match('/^([a-z][a-z0-9+.\-]*):/i', $url, $match) === 1 ? strtolower($match[1]) : null;
    }
}
