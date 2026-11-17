<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the edit form of a content field.
 *
 * `key`, `content_type` and `is_system` have no rules, so they never reach `validated()` and stay
 * unchanged. Changes the model forbids (the type of any field, the
 * multiplicity and requiredness of a system field) are rejected by the model itself and shown
 * next to their fields.
 *
 * @example
 *   // PUT /admin/content-types/news/fields/views  name=Views&type=integer&weight=5
 *   $field->update($request->validated());
 */
class UpdateContentFieldRequest extends FormRequest
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
            ...StoreContentFieldRequest::sharedRules($this->route()->defaults['kind'] ?? 'content'),
            ...StoreContentFieldRequest::settingsRules($this->input('type')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['type.not_in' => __('This field type is only available for content types.')];
    }
}
