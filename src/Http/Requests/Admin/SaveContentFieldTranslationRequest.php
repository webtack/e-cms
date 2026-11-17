<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the translation form of a field: only the label (`name`), up to 255 characters; empty removes it. The
 * right is the type configuration right, enforced by the route (`can:types`).
 *
 * @example
 *   // PUT /admin/content-types/news/fields/hours/translations/en  name=Opening hours
 */
class SaveContentFieldTranslationRequest extends FormRequest
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
        return ['name' => ['nullable', 'string', 'max:255']];
    }
}
