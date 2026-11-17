<?php

namespace Webtack\Ecms\Content;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Keeps only the safe part of a `style` attribute of rich text: `text-align`, `color` and `background-color`.
 *
 * Colors must be `#rgb`, `#rrggbb` or `rgb(r, g, b)`; alignment one of `left`, `center`, `right`, `justify`.
 * Any other property or value (`url(...)`, `expression(...)`, `position` ...) is dropped, and a `style` left
 * with no declaration is removed. Only the elements the editor writes `style` on are supported; a `figure`
 * (an aligned image) keeps `text-align`, `width` (`{N}px` 1-4000, `fit-content`, or the older `min-content`), `max-width: 100%` and `margin-left` / `margin-right` (`auto` or `0`).
 *
 * @example
 *   // On a `span`:
 *   'color: #f00; position: fixed'   // 'color: #f00'
 *   'color: red'                     // null (attribute removed)
 *   'text-align: center'             // 'text-align: center'
 *   // On a `figure`:
 *   'width: 500px; max-width: 100%; margin-left: auto; text-align: center; margin-top: 9px'
 *   // 'width: 500px; max-width: 100%; margin-left: auto; text-align: center'
 */
final class StyleAttributeSanitizer implements AttributeSanitizerInterface
{
    private const COLOR = '/^(?:#(?:[0-9a-f]{3}|[0-9a-f]{6})|rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\))$/i';

    private const ALIGN = ['left', 'center', 'right', 'justify'];

    public function getSupportedElements(): ?array
    {
        return ['span', 'p', 'h2', 'h3', 'h4', 'blockquote', 'li', 'figure'];
    }

    public function getSupportedAttributes(): ?array
    {
        return ['style'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $kept = [];

        foreach (explode(';', $value) as $declaration) {
            [$property, $setting] = array_pad(explode(':', $declaration, 2), 2, '');
            $property = strtolower(trim($property));
            $setting = trim($setting);

            $valid = match ($property) {
                'text-align' => in_array(strtolower($setting), self::ALIGN, true),
                'width' => $element === 'figure' && $this->validWidth(strtolower($setting)),
                'max-width' => $element === 'figure' && strtolower($setting) === '100%',
                'margin-left', 'margin-right' => $element === 'figure' && in_array(strtolower($setting), ['auto', '0'], true),
                'color', 'background-color' => $element !== 'figure' && preg_match(self::COLOR, $setting) === 1,
                default => false,
            };

            if ($valid) {
                $kept[$property] = in_array($property, ['color', 'background-color'], true) ? $setting : strtolower($setting);
            }
        }

        if ($kept === []) {
            return null;
        }

        return implode('; ', array_map(fn (string $property, string $setting) => "{$property}: {$setting}", array_keys($kept), $kept));
    }

    private function validWidth(string $setting): bool
    {
        if (in_array($setting, ['fit-content', 'min-content'], true)) {
            return true;
        }

        return preg_match('/^([1-9]\d{0,3})px$/', $setting, $match) === 1 && (int) $match[1] <= 4000;
    }
}
