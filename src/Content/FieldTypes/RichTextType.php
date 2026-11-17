<?php

namespace Webtack\Ecms\Content\FieldTypes;

use Closure;
use Webtack\Ecms\Content\HtmlSanitizer;
use Webtack\Ecms\Content\LocalizedUrl;
use Webtack\Ecms\Content\PresentContext;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\File;

/**
 * Rich text: an HTML fragment (paragraphs, headings, emphasis, lists, links) kept in the long text
 * table. Settings: `min` and `max` length of the VISIBLE text (markup is not counted).
 *
 * The value is sanitised in `prepare()` against an allow-list, so what is stored and read is always
 * clean. A value without visible text, an image and an embedded frame (`<p></p>`) is blank.
 *
 * Images are files of the `image` profile: the editor uploads them first and the text holds their
 * relative address, so the type tracks them (`TracksFiles`) and every one must be an existing file.
 *
 * @example
 *   $type = app(FieldTypeRegistry::class)->get('richtext');
 *   $type->prepare('<p onclick="x()">Hi</p>');   // '<p>Hi</p>'
 *   $type->isBlank('<p></p>');                   // true
 *   $type->fileUuids('<p><img src="/storage/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png"></p>');   // ['0190a1b27c3e7f0a8b1c2d3e4f5a6b7c']
 */
class RichTextType extends AbstractFieldType implements HasUploadProfile, TracksFiles
{
    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    public function key(): string
    {
        return 'richtext';
    }

    public function table(): string
    {
        return 'content_values_text';
    }

    public function rules(ContentField $field): array
    {
        $settings = $field->settings ?? [];
        $rules = $this->baseRules();

        if (isset($settings['min']) || isset($settings['max'])) {
            $rules[] = $this->visibleLength($settings['min'] ?? null, $settings['max'] ?? null);
        }

        $rules[] = $this->imagesExist();

        return $rules;
    }

    /**
     * The HTML cleaned once more on the way out, so a value written around the API (a seeder, SQL) is safe too.
     *
     * @example $type->present('<p onclick="x()">Hi</p>', $field, $context); // '<p>Hi</p>'
     */
    public function present(mixed $value, ContentField $field, PresentContext $context): ?string
    {
        return is_string($value) && $value !== '' ? app(LocalizedUrl::class)->html($this->sanitizer->clean($value)) : null;
    }

    /**
     * The upload profile of the images inserted into this field.
     */
    public function uploadProfile(): string
    {
        return 'image';
    }

    /**
     * The `media.uuid` of every image in a value (one text or the list of a multiple field).
     *
     * The value is cleaned first, so only images with the canonical storage address are found.
     *
     * @return list<string>
     */
    public function fileUuids(mixed $storedValue): array
    {
        $uuids = [];

        foreach (is_array($storedValue) ? $storedValue : [$storedValue] as $value) {
            if (! is_string($value)) {
                continue;
            }

            preg_match_all('/<img\b[^>]*\ssrc="[^"]*\/([0-9a-f]{32})\.[a-z0-9]{1,8}"/i', $this->sanitizer->clean($value), $matches);
            array_push($uuids, ...$matches[1]);
        }

        return array_values(array_unique($uuids));
    }

    public function prepare(mixed $value): mixed
    {
        return $this->sanitizer->clean((string) $value);
    }

    public function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && $this->sanitizer->text($value) === '' && ! $this->sanitizer->hasFrame($value) && ! $this->sanitizer->hasImage($value));
    }

    public function operators(): array
    {
        return ['eq', 'in', 'contains'];
    }

    public function searchable(): bool
    {
        return true;
    }

    protected function baseRules(): array
    {
        return ['string'];
    }

    /**
     * A rule that every image of the value is an existing file of the `image` profile.
     */
    private function imagesExist(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $uuids = $this->fileUuids($value);

            if ($uuids === []) {
                return;
            }

            $found = File::query()->whereIn('uuid', $uuids)->where('profile', $this->uploadProfile())->count();

            if ($found !== count($uuids)) {
                $fail("The {$attribute} must contain only existing images.");
            }
        };
    }

    /**
     * A rule limiting the number of characters of the visible text.
     */
    private function visibleLength(mixed $min, mixed $max): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($min, $max): void {
            $length = mb_strlen($this->sanitizer->text((string) $value));

            if ($min !== null && $length < (int) $min) {
                $fail("The {$attribute} must be at least {$min} characters.");
            } elseif ($max !== null && $length > (int) $max) {
                $fail("The {$attribute} must not be greater than {$max} characters.");
            }
        };
    }
}
