<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Webtack\Ecms\Content\FieldTypeRegistry;
use Webtack\Ecms\Content\FieldTypes\AbstractFileFieldType;
use Webtack\Ecms\Content\SystemFields;
use Webtack\Ecms\Files\UploadRules;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;

/**
 * Validates the create form of a content field (the type comes from the `contentType` route parameter).
 *
 * `is_system` is not a rule, so it never reaches `validated()`: a field created here is never
 * a system field. The key is unique within the type only. The rules duplicate the model's on
 * purpose: the form gets errors per field, the model stays the last barrier for other write
 * paths. `UpdateContentFieldRequest` reuses {@see self::sharedRules()}, so both forms cannot drift apart.
 *
 * @example
 *   // POST /admin/content-types/news/fields  key=views&name=Views&type=integer&weight=10
 *   $type->fields()->create($request->validated());
 */
class StoreContentFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var ContentType $type */
        $type = $this->route('contentType');

        return [
            ...self::sharedRules($this->route()->defaults['kind'] ?? 'content'),
            ...self::settingsRules($this->input('type')),
            'key' => [
                'required', 'string', 'max:64', 'regex:'.ContentType::KEY_PATTERN,
                Rule::notIn([...ContentField::RESERVED_KEYS, ...app(SystemFields::class)->reservedKeys()]),
                Rule::unique('content_fields', 'key')->where('content_type', $type->key),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.not_in' => __('This key is reserved.'),
            'type.not_in' => __('This field type is only available for content types.'),
        ];
    }

    /**
     * Rules of the fields both forms edit. A `term` field is allowed in `content` types only.
     *
     * @param  string  $kind  Kind of the type section the form belongs to (`content`, `page` or `block`).
     * @return array<string, array<int, mixed>>
     *
     * @example
     *   self::sharedRules()['weight'];            // ['required', 'integer', 'between:0,65535']
     *   self::sharedRules('page')['type'];        // also rejects 'term'
     */
    public static function sharedRules(string $kind = 'content'): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => [
                'required', 'string', Rule::in(app(FieldTypeRegistry::class)->keys()),
                ...($kind === 'content' ? [] : [Rule::notIn(['term'])]),
            ],
            'is_multiple' => ['boolean'],
            'max_items' => ['nullable', 'integer', 'between:0,65535'],
            'is_required' => ['boolean'],
            'is_translatable' => ['boolean'],
            'hide_label' => ['boolean'],
            'weight' => ['required', 'integer', 'between:0,65535'],
        ];
    }

    /**
     * Rules of the `settings` the form edits: the allowed types, the size limit and, for types whose profile
     * holds images (`image`), the maximum width and height in pixels of the file-bearing
     * types (`image`, `file`), which may only narrow the type's upload profile, and the vocabulary of a
     * `term` field (required, an existing key), the kind of a `type_ref` field and the type of a `node_ref` field. Other types take no settings from the form. Shared by the create and edit requests.
     *
     * @return array<string, array<int, mixed>>
     *
     * @example
     *   self::settingsRules('image')['settings.max_size']; // ['nullable', 'integer', 'min:1', 'max:5120']
     *   self::settingsRules('image')['settings.max_width']; // ['nullable', 'integer', 'min:1', 'max:1920']
     *   self::settingsRules('term')['settings.vocabulary']; // ['required', 'string', exists in taxonomy_vocabularies.key]
     *   self::settingsRules('string');                     // ['settings' => ['nullable', 'array:labels'], ...]
     */
    public static function settingsRules(mixed $type): array
    {
        $registry = app(FieldTypeRegistry::class);
        $fileType = is_string($type) && $registry->has($type) ? $registry->get($type) : null;

        return [...self::typeSettingsRules($type, $registry, $fileType), ...self::labelsRules()];
    }

    /**
     * Rules of the translated labels kept in `settings.labels`. Every type accepts them so a client that
     * echoes the settings it received does not fail; the controller never takes labels from the base
     * form (they change only through the translation routes) and keeps the stored ones.
     *
     * @return array<string, array<int, mixed>>
     */
    private static function labelsRules(): array
    {
        return [
            'settings.labels' => ['nullable', 'array'],
            'settings.labels.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The rules of the type's own settings; each `settings` rule also lets `labels` through.
     *
     * @return array<string, array<int, mixed>>
     */
    private static function typeSettingsRules(mixed $type, FieldTypeRegistry $registry, mixed $fileType): array
    {
        // A term field names its vocabulary and nothing else; the model also checks that it exists.
        if ($type === 'term') {
            return [
                'settings' => ['nullable', 'array:vocabulary,labels'],
                'settings.vocabulary' => ['required', 'string', Rule::exists('taxonomy_vocabularies', 'key')],
            ];
        }

        // A reference field names what it points at and nothing else; the field type also checks that it exists.
        if ($type === 'type_ref') {
            return [
                'settings' => ['required', 'array:kind,labels'],
                'settings.kind' => ['required', 'string', Rule::in(config('content.kinds'))],
            ];
        }

        if ($type === 'node_ref') {
            return [
                'settings' => ['required', 'array:content_type,labels'],
                'settings.content_type' => ['required', 'string', Rule::exists('content_types', 'key')],
            ];
        }

        if (! $fileType instanceof AbstractFileFieldType) {
            return ['settings' => ['nullable', 'array:labels']];
        }

        $profile = UploadRules::forProfile($fileType->profile());

        // Pixel limits make sense only for a profile that holds images: `image` has them, `file` does not.
        $hasImages = array_filter($profile->mimes, fn (string $mime) => str_starts_with($mime, 'image/')) !== [];

        $rules = [
            'settings' => ['nullable', 'array:'.implode(',', ['mimes', 'max_size', 'labels', ...($hasImages ? ['max_width', 'max_height'] : [])])],
            'settings.mimes' => ['nullable', 'array'],
            'settings.mimes.*' => ['string', Rule::in($profile->mimes)],
            'settings.max_size' => ['nullable', 'integer', 'min:1', 'max:'.$profile->maxSize],
        ];

        if ($hasImages) {
            $rules['settings.max_width'] = ['nullable', 'integer', 'min:1', ...($profile->maxWidth === null ? [] : ['max:'.$profile->maxWidth])];
            $rules['settings.max_height'] = ['nullable', 'integer', 'min:1', ...($profile->maxHeight === null ? [] : ['max:'.$profile->maxHeight])];
        }

        return $rules;
    }
}
