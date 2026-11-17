<?php

namespace Webtack\Ecms\Content;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Keeps the `width` of an image only as a whole number of pixels from 1 to 4000.
 *
 * Anything else (`10px`, `5000`, `0`, `1.5`) yields `null`, which removes the attribute and leaves
 * the image at its natural size.
 *
 * @example
 *   '200'    // '200'
 *   '5000'   // null
 *   '10px'   // null
 */
final class ImageWidthSanitizer implements AttributeSanitizerInterface
{
    public const MIN = 1;

    public const MAX = 4000;

    public function getSupportedElements(): ?array
    {
        return ['img'];
    }

    public function getSupportedAttributes(): ?array
    {
        return ['width'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $width = trim($value);

        if (preg_match('/^[0-9]{1,4}$/', $width) !== 1) {
            return null;
        }

        return (int) $width >= self::MIN && (int) $width <= self::MAX ? (string) (int) $width : null;
    }
}
