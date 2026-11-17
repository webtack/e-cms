<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Rules\AvailablePublicPath;
use Webtack\Ecms\Rules\TypeSlugHierarchy;

/**
 * Validates the create form of a content type of the kind the route serves (`content` or `page`).
 *
 * `kind` is not a rule, so it never reaches `validated()`: the controller takes it from the route.
 * `slug` is a rule only for kind `content`, so it is dropped from `validated()` for `page`. The rules duplicate the model's on purpose: the form gets errors per field,
 * the model stays the last barrier for other write paths. `UpdateContentTypeRequest` reuses
 * {@see self::sharedRules()}, so both forms cannot drift apart.
 *
 * @example
 *   // POST /admin/content-types  name=News&key=news&slug=news&parent_type=&description=
 *   ContentType::create([...$request->validated(), 'kind' => 'content']);
 */
class StoreContentTypeRequest extends FormRequest
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
        return [
            ...self::sharedRules(kind: $this->route()->defaults['kind']),
            'key' => self::keyRules(),
        ];
    }

    /**
     * Rules of a new type's `key`; shared with the clone dialog.
     *
     * @return array<int, mixed>
     */
    public static function keyRules(): array
    {
        return ['required', 'string', 'max:64', 'regex:'.ContentType::KEY_PATTERN, Rule::notIn(ContentType::RESERVED_KEYS), Rule::unique('content_types', 'key')];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.not_in' => __('This key is reserved.'),
        ];
    }

    /**
     * Rules of the fields both forms edit (including the shape of `settings.list`); a `ContentType` makes the slug unique among the others.
     * The parent must be of the same kind, and only kind `content` has a slug.
     *
     * @return array<string, array<int, mixed>>
     *
     * @example
     *   self::sharedRules($type, 'content')['slug']; // unique, ignoring $type itself
     *   self::sharedRules(kind: 'page'); // no `slug` key
     */
    public static function sharedRules(?ContentType $type = null, string $kind = 'content'): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            ...($kind === 'content' ? ['slug' => [
                'nullable', 'string', 'max:190', 'regex:'.ContentType::SLUG_PATTERN,
                Rule::unique('content_types', 'slug')->ignore($type?->getKey()),
                new AvailablePublicPath(forPage: false, current: $type?->slug, type: $type),
                new TypeSlugHierarchy($type),
            ]] : []),
            'parent_type' => ['nullable', 'string', Rule::exists('content_types', 'key')->where('kind', $kind)],
            'description' => ['nullable', 'string'],
            // List settings: only the shape is checked here; the keys are checked against the type's
            // fields and the service keys by `ContentType::assertValidListSettings()` when it is saved.
            'settings' => ['nullable', 'array:list'],
            'settings.list' => ['nullable', 'array:visible,search,sort'],
            'settings.list.visible' => ['nullable', 'array'],
            'settings.list.visible.*' => ['string', 'distinct'],
            'settings.list.search' => ['nullable', 'array'],
            'settings.list.search.*' => ['string', 'distinct'],
            'settings.list.sort' => ['nullable', 'array:field,direction'],
            'settings.list.sort.field' => ['required_with:settings.list.sort.direction', 'nullable', 'string'],
            'settings.list.sort.direction' => ['nullable', 'in:asc,desc'],
        ];
    }
}
