<?php

namespace Webtack\Ecms\Http\Controllers\Admin\Concerns;

use Illuminate\Http\Request;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Models\AppLanguage;

/**
 * The server side of the admin translation mode (see `docs/admin-translation-mode.md`): resolves the
 * `?lang=` parameter of an edit page and builds the shared Inertia prop `translation`.
 *
 * The base mode is a page without `lang` or with the code of the base language. The translation mode is
 * the code of an active additional language. Anything else (an unknown code, an inactive language, any
 * additional language while multilingual mode is off) answers 404.
 *
 * @example
 *   $lang = $this->resolveTranslationLang($request);          // null = base mode, 'en' = translation mode
 *   $translation = $this->translationProp($lang, ['en'], ['name' => 'Jackets'], true); // null when no switcher
 */
trait ResolvesTranslationLanguage
{
    /**
     * The additional language of the page, or `null` for the base mode.
     *
     * @return string|null The normalised code of an active additional language.
     */
    protected function resolveTranslationLang(Request $request): ?string
    {
        $raw = $request->query('lang');

        return $raw === null || $raw === '' ? null : $this->activeTranslationLang((string) $raw);
    }

    /**
     * Validates a language code taken from an address: the base code gives `null`, an active additional
     * language its normalised code, anything else 404.
     *
     * @return string|null `null` for the base language.
     */
    protected function activeTranslationLang(string $code): ?string
    {
        $languages = app(ContentLanguages::class);
        $code = $languages->normalize($code);

        if ($code === $languages->baseLang()) {
            return null;
        }

        abort_unless(in_array($code, $languages->active(), true), 404);

        return $code;
    }

    /**
     * The `translation` prop: `null` when the switcher is not shown (multilingual off or no active additional
     * language), otherwise the current language, the base code and the switcher entries.
     *
     * @param  list<string>  $translatedCodes  Languages the entity is translated into (the single "translated" definition: at least one stored value that differs from the base).
     * @param  array<string, mixed>|null  $values  Prefilled form values of the translation mode (`null` in the base mode).
     * @return array{lang: string|null, baseLang: string, languages: list<array{code: string, name: string, translated: bool}>, values: array<string, mixed>|null, exists: bool}|null
     */
    protected function translationProp(?string $lang, array $translatedCodes, ?array $values = null, bool $exists = false): ?array
    {
        $languages = app(ContentLanguages::class);
        $codes = $languages->active();

        if ($codes === []) {
            return null;
        }

        $names = AppLanguage::query()->whereIn('code', $codes)->pluck('name', 'code');

        return [
            'lang' => $lang,
            'baseLang' => $languages->baseLang(),
            'languages' => array_map(fn (string $code) => [
                'code' => $code,
                'name' => $names[$code] ?? $code,
                'translated' => in_array($code, $translatedCodes, true),
            ], $codes),
            'values' => $values,
            'exists' => $exists,
        ];
    }
}
