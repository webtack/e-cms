<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Content\PublicPaths;
use Webtack\Ecms\Models\AppLanguage;

/**
 * Validates adding an additional content language (`POST /admin/settings/languages`).
 *
 * The code is normalised before validation. A new language is inactive unless `is_active` is sent.
 * A code equal to the saved base language code, to a reserved path, to a page slug or to the first segment of a
 * content type slug is rejected under `code`. Access is decided by
 * `can:settings`.
 *
 * @example
 *   // POST /admin/settings/languages  code=PT-br&name=Português&is_active=1
 */
class StoreContentLanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');

        if (is_string($code)) {
            $this->merge(['code' => AppLanguage::normalize($code)]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:'.AppLanguage::CODE_MAX_LENGTH,
                'regex:'.AppLanguage::CODE_PATTERN,
                'not_in:'.config('content.default_lang'),
                'unique:app_languages,code',
                fn (string $attribute, mixed $value, \Closure $fail) => $value === app(ContentLanguages::class)->savedBaseLang()
                    ? $fail(__('The code of the base language cannot be used for an additional language.'))
                    : null,
                // The code becomes the first segment of public addresses: it must not hide a page, a type or a service path.
                function (string $attribute, mixed $value, \Closure $fail) {
                    $reason = app(PublicPaths::class)->languageCodeConflict($value);

                    if ($reason !== null) {
                        $fail(PublicPaths::languageMessage($reason))->translate();
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $key === null ? $data : data_get($data, $key, $default);
    }
}
