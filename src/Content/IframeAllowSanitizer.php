<?php

namespace Webtack\Ecms\Content;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Cleans the `allow` (Permissions Policy) attribute of an iframe down to the features listed in
 * `content.richtext.iframe_allow`. Only bare feature names are kept (no origins); an `allow` left empty is removed.
 *
 * @example
 *   // With 'autoplay' and 'picture-in-picture' allowed:
 *   'autoplay; camera; picture-in-picture'   // 'autoplay; picture-in-picture'
 *   'camera; microphone'                     // null
 */
final class IframeAllowSanitizer implements AttributeSanitizerInterface
{
    public function getSupportedElements(): ?array
    {
        return ['iframe'];
    }

    public function getSupportedAttributes(): ?array
    {
        return ['allow'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $allowed = array_map('strtolower', (array) config('content.richtext.iframe_allow', []));
        $kept = [];

        foreach (explode(';', $value) as $feature) {
            $feature = strtolower(trim($feature));

            if ($feature !== '' && in_array($feature, $allowed, true) && ! in_array($feature, $kept, true)) {
                $kept[] = $feature;
            }
        }

        return $kept === [] ? null : implode('; ', $kept);
    }
}
