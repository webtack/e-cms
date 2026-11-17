<?php

namespace Webtack\Ecms\Content;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Keeps the `src` of an image only when it is the relative address of a stored file.
 *
 * The one accepted form is `{public path of the public disk}/{32 hex}.{extension}` (by default
 * `/storage/{uuid}.png`): no protocol, no host, no query. Absolute, protocol-relative, `data:` and
 * any other address yields `null`, which removes the attribute (and the tag, see `HtmlSanitizer`).
 *
 * @example
 *   // With the public disk served under /storage:
 *   '/storage/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png'         // kept
 *   'http://localhost/storage/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png'   // null
 *   '//host/storage/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png'   // null
 *   '/other/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png'           // null
 */
final class ImageSrcSanitizer implements AttributeSanitizerInterface
{
    public function getSupportedElements(): ?array
    {
        return ['img'];
    }

    public function getSupportedAttributes(): ?array
    {
        return ['src'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $address = trim($value);
        $pattern = '#^'.preg_quote(self::prefix(), '#').'/[0-9a-f]{32}\.[a-z0-9]{1,8}$#';

        return preg_match($pattern, $address) === 1 ? $address : null;
    }

    /**
     * The public path of the `public` disk without a trailing slash (`/storage` by default).
     */
    public static function prefix(): string
    {
        $path = parse_url(Storage::disk('public')->url(''), PHP_URL_PATH);

        return rtrim(is_string($path) ? $path : '', '/');
    }
}
