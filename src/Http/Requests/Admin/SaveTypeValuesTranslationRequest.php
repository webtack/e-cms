<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the translation form of a tab of a type: the tab (`panel`: `section` for "Display", `seo` for "SEO")
 * and that `values` is an array of field key => value. The values are checked by `TranslationSaver`. The right is
 * the type configuration right, enforced by the route (`can:types`).
 *
 * @example
 *   // PUT /admin/content-types/news/values/translations/en  panel=section&values[section_title]=News
 */
class SaveTypeValuesTranslationRequest extends FormRequest
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
            'panel' => ['required', Rule::in(['section', 'seo'])],
            'values' => ['sometimes', 'array'],
            ...SaveNodeTranslationRequest::fileDescriptionRules(),
        ];
    }
}
