<?php

namespace Webtack\Ecms\Content\FieldTypes;

use Closure;
use Illuminate\Support\Facades\Validator;
use Webtack\Ecms\Content\PresentContext;
use Webtack\Ecms\Models\ContentField;

/**
 * A link with an optional title: the link as the editor entered it (trimmed) is the scalar value in
 * `content_values_string`, the title is kept as JSON in `options` of the same row (see `ComposedValue`).
 *
 * The composed value is `['url', 'title']`. Input may also be a bare link. Accepted links: absolute
 * `http(s)` with a host, `mailto:` / `tel:` with a non-empty address, and relative ones (`/path`
 * but not `//host`, `./`, `../`, `#fragment`, `?query`). Everything else (`javascript:`, `data:`, ...)
 * is rejected. Saving never touches the network.
 *
 * @example
 *   UrlType::isValid('https://example.com/a?b=1#c'); // true
 *   UrlType::isValid('/about');                      // true
 *   UrlType::isValid('mailto:info@example.com');     // true
 *   UrlType::isValid('//example.com');               // false
 *   UrlType::isValid('javascript:alert(1)');         // false
 */
class UrlType extends AbstractFieldType implements ComposedValue
{
    /** Longest stored link, the size of the `value` column. */
    public const MAX_URL = 512;

    /** Longest title. */
    public const MAX_TITLE = 255;

    /**
     * Whether the text is an acceptable link. The text is trimmed first.
     */
    public static function isValid(string $input): bool
    {
        $input = trim($input);

        // Empty, or whitespace / control characters inside; a backslash is read by browsers as a slash.
        if ($input === '' || preg_match('/[\s\x00-\x1F\x7F\\\\]/u', $input) === 1) {
            return false;
        }

        if (str_starts_with($input, '/')) {
            return ! str_starts_with($input, '//');
        }

        foreach (['./', '../', '#', '?'] as $prefix) {
            if (str_starts_with($input, $prefix)) {
                return true;
            }
        }

        if (preg_match('/^(mailto|tel):(.+)$/i', $input) === 1) {
            return true;
        }

        $parts = parse_url($input);

        return $parts !== false
            && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && ($parts['host'] ?? '') !== '';
    }

    public function key(): string
    {
        return 'url';
    }

    public function table(): string
    {
        return 'content_values_string';
    }

    public function rules(ContentField $field): array
    {
        return [function (string $attribute, mixed $value, Closure $fail): void {
            $value = is_array($value) ? $value : ['url' => $value];

            $validator = Validator::make($value, [
                'url' => ['required', 'string', function (string $name, mixed $url, Closure $failUrl): void {
                    if (! self::isValid($url)) {
                        $failUrl("The {$name} must be a valid link.");
                    } elseif (mb_strlen(trim($url)) > self::MAX_URL) {
                        $failUrl("The {$name} must not be greater than ".self::MAX_URL.' characters.');
                    }
                }],
                'title' => ['nullable', 'string', 'max:'.self::MAX_TITLE],
            ]);

            if ($validator->fails()) {
                $fail("The {$attribute}: ".$validator->errors()->first());
            }
        }];
    }

    public function split(mixed $input): array
    {
        $input = is_array($input) ? $input : ['url' => $input];
        $title = isset($input['title']) ? trim((string) $input['title']) : '';

        return [trim((string) $input['url']), $title !== '' ? ['title' => $title] : null];
    }

    public function compose(mixed $scalar, ?array $options): mixed
    {
        return ['url' => $scalar, 'title' => $options['title'] ?? null];
    }

    public function isBlank(mixed $value): bool
    {
        $url = is_array($value) ? ($value['url'] ?? null) : $value;

        return ! is_string($url) || trim($url) === '';
    }

    /**
     * The link and its title; `null` for a link that is not acceptable (see {@see self::isValid()}), so a
     * template never prints a `javascript:` address even if one reached the database.
     *
     * @return array{url: string, title: string|null}|null
     *
     * @example $type->present(['url' => '/about', 'title' => 'About'], $field, $context); // ['url' => '/about', 'title' => 'About']
     */
    public function present(mixed $value, ContentField $field, PresentContext $context): ?array
    {
        $url = is_array($value) ? ($value['url'] ?? null) : null;

        if (! is_string($url) || ! self::isValid($url)) {
            return null;
        }

        return ['url' => $context->localize(trim($url)), 'title' => $value['title'] ?? null];
    }

    public function operators(): array
    {
        return ['eq', 'in', 'contains'];
    }

    protected function baseRules(): array
    {
        return [];
    }
}
