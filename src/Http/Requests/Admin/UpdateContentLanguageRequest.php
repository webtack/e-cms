<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates editing an additional content language (`PUT /admin/settings/languages/{language}`).
 *
 * Only the name, the activity flag and the weight change; the code has no rule, so it never reaches
 * `validated()`. Access is decided by `can:settings`.
 *
 * @example
 *   // PUT /admin/settings/languages/en  name=English&is_active=1&weight=2
 */
class UpdateContentLanguageRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'weight' => ['required', 'integer', 'min:0'],
        ];
    }
}
