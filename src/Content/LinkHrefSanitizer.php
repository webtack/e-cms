<?php

namespace Webtack\Ecms\Content;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Drops the `href` of a link when it starts with `//` or contains a backslash (the same rule as the `url` field).
 *
 * `//host/x` looks relative but is an external address, and browsers read a backslash as a slash. Any other
 * value is returned unchanged: the scheme check stays with the library (`allowLinkSchemes`). The link keeps its text.
 *
 * @example
 *   '//example.com/a'          // null
 *   '/\\example.com'           // null
 *   '/about'                   // kept
 *   '#top', '?page=2'          // kept
 *   'https://example.com/a'    // kept
 */
final class LinkHrefSanitizer implements AttributeSanitizerInterface
{
    public function getSupportedElements(): ?array
    {
        return ['a'];
    }

    public function getSupportedAttributes(): ?array
    {
        return ['href'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $address = trim($value);

        if (str_starts_with($address, '//') || str_contains($address, '\\')) {
            return null;
        }

        return $value;
    }
}
