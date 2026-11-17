<?php

namespace Webtack\Ecms\Content\FieldTypes;

use Webtack\Ecms\Content\PresentContext;
use Webtack\Ecms\Files\FileStorage;
use Webtack\Ecms\Files\Rules\FileValue;
use Webtack\Ecms\Models\ContentField;

/**
 * Shared behaviour of the field types that hold a stored file: the value is the string
 * `{uuid}.{ext}` kept in `content_values_string`, so reading, filtering (`eq`) and the multiple and
 * translated shapes are those of a short string. The subclass names the upload profile, which is
 * the ceiling for what the field accepts; the field settings (`mimes`, `max_size`) only narrow it.
 *
 * @example
 *   $type = app(FieldTypeRegistry::class)->get('image');
 *   $type->profile(); // 'image'
 *   $type->table();   // 'content_values_string'
 */
abstract class AbstractFileFieldType extends AbstractFieldType implements PreloadsReferences, TracksFiles
{
    /** The upload profile of this type (a key of config/files.php `profiles`). */
    abstract public function profile(): string;

    public function table(): string
    {
        return 'content_values_string';
    }

    public function rules(ContentField $field): array
    {
        // Filters validate with an empty field (see ContentNodeQuery): only the shape of the value then.
        return ['string', new FileValue($this->profile(), $field->type === null ? null : $field)];
    }

    public function fileUuids(mixed $storedValue): array
    {
        $uuids = [];

        foreach (is_array($storedValue) ? $storedValue : [$storedValue] as $value) {
            if (is_string($value) && preg_match(FileValue::PATTERN, $value, $match) === 1) {
                $uuids[] = $match[1];
            }
        }

        return array_values(array_unique($uuids));
    }

    /**
     * The file for a template: its public URL, client-side name and description. `null` for a value that is
     * not a `{uuid}.{ext}` file name. A file whose row is missing keeps its URL and takes the stored file name
     * as its name.
     *
     * @return array{url: string, name: string, description: string|null}|null
     *
     * @example $type->present('0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.pdf', $field, $context); // ['url' => '/storage/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.pdf', 'name' => 'price.pdf', 'description' => null]
     */
    public function present(mixed $value, ContentField $field, PresentContext $context): ?array
    {
        if (! is_string($value) || preg_match(FileValue::PATTERN, $value, $match) !== 1) {
            return null;
        }

        $meta = $context->file($match[1]);

        return [
            'url' => app(FileStorage::class)->urlForValue($this->profile(), $value),
            'name' => $meta['name'] ?? $value,
            'description' => $meta['description'] ?? null,
        ];
    }

    public function preload(array $values, ContentField $field, PresentContext $context): void
    {
        $context->preloadFiles($this->fileUuids($values));
    }

    protected function baseRules(): array
    {
        return ['string'];
    }
}
