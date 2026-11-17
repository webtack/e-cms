<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the translation mode form of a menu item: the title and the description of one language, both
 * optional (an empty field means "no translation, use the base"). The language comes from the route.
 *
 * @example
 *   // PUT /admin/menus/main/items/{uuid}/translations/en  title=About us&description=
 */
class SaveMenuItemTranslationRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65000'],
        ];
    }
}
