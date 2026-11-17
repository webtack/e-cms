<?php

namespace Webtack\Ecms\Content;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Keeps `colspan` and `rowspan` of a table cell only as a whole number from 1 to 100.
 *
 * Anything else (`0`, `101`, `x`, `1.5`, an empty value) yields `null`, which removes the attribute
 * and leaves the cell in place. The value is normalized: `03` becomes `3`.
 *
 * @example
 *   '3'     // '3'
 *   '03'    // '3'
 *   '101'   // null
 *   'x'     // null
 */
final class TableSpanSanitizer implements AttributeSanitizerInterface
{
    public const MIN = 1;

    public const MAX = 100;

    public function getSupportedElements(): ?array
    {
        return ['td', 'th'];
    }

    public function getSupportedAttributes(): ?array
    {
        return ['colspan', 'rowspan'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $span = trim($value);

        if (preg_match('/^[0-9]{1,3}$/', $span) !== 1) {
            return null;
        }

        return (int) $span >= self::MIN && (int) $span <= self::MAX ? (string) (int) $span : null;
    }
}
