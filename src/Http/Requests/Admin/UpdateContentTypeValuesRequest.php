<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the shape of the panels of a content type: `values` is an array of field key => value.
 * The values themselves are checked by `ContentValueWriter`, which builds the rules from the
 * field definitions and refuses the fields the type does not hold values for. The optional
 * `settings.public.meta` maps `title`, `description` and `image` to a field key (missing or empty means "not chosen");
 * whether the field exists and has an allowed type is checked by `ContentType::assertValidPublicSettings()`.
 *
 * @example
 *   // PUT /admin/content-types/news/values  values[section_title]=News&values[meta_image]=&settings[public][meta][title]=title
 */
class UpdateContentTypeValuesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'values' => ['array'],
            'settings' => ['nullable', 'array:public'],
            'settings.public' => ['nullable', 'array:meta'],
            'settings.public.meta' => ['nullable', 'array:title,description,image'],
            'settings.public.meta.title' => ['nullable', 'string'],
            'settings.public.meta.description' => ['nullable', 'string'],
            'settings.public.meta.image' => ['nullable', 'string'],
        ];
    }
}
