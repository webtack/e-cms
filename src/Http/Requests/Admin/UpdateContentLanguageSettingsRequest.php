<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Models\AppLanguage;

/**
 * Validates the switch and base code form (`PUT /admin/settings/languages`).
 *
 * `base_lang` is normalised first; its format is checked here, its collision with an additional
 * language and the "no code, no switch" rule by {@see ContentLanguages} (errors under `base_lang`
 * and `multilingual`). An empty `base_lang` keeps the saved code. Access is decided by `can:settings`.
 *
 * @example
 *   // PUT /admin/settings/languages  multilingual=1&base_lang=UK
 */
class UpdateContentLanguageSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('base_lang');

        if (is_string($code)) {
            $this->merge(['base_lang' => $code === '' ? null : AppLanguage::normalize($code)]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'multilingual' => ['boolean'],
            'base_lang' => [
                'nullable',
                'string',
                'max:'.AppLanguage::CODE_MAX_LENGTH,
                'regex:'.AppLanguage::CODE_PATTERN,
                'not_in:'.config('content.default_lang'),
            ],
        ];
    }
}
