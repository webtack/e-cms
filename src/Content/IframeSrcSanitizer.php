<?php

namespace Webtack\Ecms\Content;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Keeps the `src` of an iframe only when it points to an allowed embed address.
 *
 * The address must be `https` and its host must match an entry of `content.richtext.iframe_hosts` exactly
 * (no suffix matches, so `youtube.com.evil.example` fails); an entry with a path prefix additionally
 * requires the path to start with it. The raw path must also have no dot segment (`.`, `..`), no backslash
 * and no percent-encoded dot, slash or backslash (`%2e`, `%2f`, `%5c`, any letter case): browsers resolve
 * those, which would turn the prefix into no restriction at all. The path is checked as written, before any
 * decoding. Anything else yields `null`, which removes the attribute.
 *
 * @example
 *   // With the entries 'www.youtube-nocookie.com/' and 'www.facebook.com/plugins/':
 *   'https://www.youtube-nocookie.com/embed/abc'   // kept
 *   'https://www.facebook.com/plugins/post.php'    // kept
 *   'https://www.facebook.com/some.page'           // null (outside the path prefix)
 *   'http://www.youtube-nocookie.com/embed/abc'    // null (not https)
 *   'https://www.youtube.com/embed/../account'     // null (dot segment)
 *   'https://www.youtube.com/embed/%2e%2e/account' // null (encoded dot)
 */
final class IframeSrcSanitizer implements AttributeSanitizerInterface
{
    public function getSupportedElements(): ?array
    {
        return ['iframe'];
    }

    public function getSupportedAttributes(): ?array
    {
        return ['src'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $address = trim($value);
        $parts = parse_url($address);

        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        if (isset($parts['port']) && $parts['port'] !== 443) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '/';

        if (preg_match('#(^|/)\.{1,2}(/|$)|\\\\|%2e|%2f|%5c#i', $path) === 1) {
            return null;
        }

        foreach ((array) config('content.richtext.iframe_hosts', []) as $entry) {
            [$allowedHost, $prefix] = array_pad(explode('/', strtolower((string) $entry), 2), 2, '');

            if ($host === $allowedHost && str_starts_with(strtolower($path), '/'.$prefix)) {
                return $address;
            }
        }

        return null;
    }
}
