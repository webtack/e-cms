<?php

namespace Webtack\Ecms\Content;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Support\AppSettings;

/**
 * Rules for which content languages may be used, and the multilingual switch.
 *
 * The switch lives in {@see AppSettings} under `content.multilingual` (off by default). The base
 * language `default` is always allowed. Other languages need the switch on and the language listed
 * in `app_languages`; writing additionally needs it to be active. Languages are normalised with
 * {@see AppLanguage::normalize()} before every check, so `pt-br` and `pt-BR` are one language.
 * None of this touches the admin interface locale.
 *
 * The base language code (`content.base_lang`) names the language the `default` values are written
 * in for the outside world (`<html lang>`, `hreflang`, URL prefixes); it never changes the `default`
 * storage key. Without a saved code it reads as `config('content.base_lang_default')`, but multilingual cannot be
 * turned on until a code is saved, and the code never equals an additional language.
 *
 * Deleting a language is guarded by the usage registry: every {@see LanguageUsage} tagged with
 * {@see self::USAGE_TAG} is asked whether it stores translations in that language.
 *
 * @example
 *   $languages = app(ContentLanguages::class);
 *   $languages->setMultilingual(true);
 *   $languages->active();               // ['uk', 'en'] ordered by weight
 *   $languages->assertWritable('pt-br'); // 'pt-BR' or ValidationException
 */
class ContentLanguages
{
    public const SETTING_KEY = 'content.multilingual';

    public const BASE_LANG_KEY = 'content.base_lang';

    /** Container tag of the {@see LanguageUsage} checkers consulted before a language is deleted. */
    public const USAGE_TAG = 'content.language-usages';

    public function __construct(private readonly AppSettings $settings) {}

    /**
     * Whether languages other than `default` may be used.
     */
    public function isMultilingual(): bool
    {
        return (bool) $this->settings->get(self::SETTING_KEY, false);
    }

    /**
     * Turn multilingual content on or off. Stored translations are never touched.
     * Turning it on needs a saved base language code.
     *
     * @throws ValidationException Under `multilingual` when enabling without a saved base code.
     */
    public function setMultilingual(bool $enabled): void
    {
        if ($enabled && $this->savedBaseLang() === null) {
            throw ValidationException::withMessages(['multilingual' => 'Save the base language code before enabling multilingual content.']);
        }

        $this->settings->set(self::SETTING_KEY, $enabled);
        app(PublicLanguages::class)->forget();
    }

    /**
     * The saved base language code, or null when none was saved.
     */
    public function savedBaseLang(): ?string
    {
        $code = $this->settings->get(self::BASE_LANG_KEY);

        return is_string($code) && $code !== '' ? $code : null;
    }

    /**
     * The code of the base language: the saved one, or the configured default (`content.base_lang_default`) while none is saved.
     *
     * @example
     *   $languages->baseLang(); // 'uk'
     */
    public function baseLang(): string
    {
        return $this->savedBaseLang() ?? $this->normalize((string) config('content.base_lang_default'));
    }

    /**
     * Save the base language code in normalised form. Stored values are never changed.
     *
     * @return string The normalised code.
     *
     * @throws ValidationException Under `base_lang` for a malformed, reserved or additional-language code, or one that equals a reserved path, a page slug or a type first segment.
     *
     * @example
     *   $languages->setBaseLang('UK'); // 'uk'
     */
    public function setBaseLang(string $code): string
    {
        $code = $this->normalize($code);

        Validator::make(['base_lang' => $code], [
            'base_lang' => [
                'required',
                'max:'.AppLanguage::CODE_MAX_LENGTH,
                'regex:'.AppLanguage::CODE_PATTERN,
                'not_in:'.config('content.default_lang'),
            ],
        ])->validate();

        if (AppLanguage::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['base_lang' => "The code [{$code}] belongs to an additional language."]);
        }

        if ($reason = app(PublicPaths::class)->languageCodeConflict($code)) {
            throw ValidationException::withMessages(['base_lang' => __(PublicPaths::languageMessage($reason))]);
        }

        $this->settings->set(self::BASE_LANG_KEY, $code);
        app(PublicLanguages::class)->forget();

        return $code;
    }

    /**
     * Whether any stored translation exists in $lang, according to every registered usage checker.
     *
     * @example
     *   $languages->isInUse('en'); // true when a term, menu item or value has an `en` translation
     */
    public function isInUse(string $lang): bool
    {
        $lang = $this->normalize($lang);

        foreach (app()->tagged(self::USAGE_TAG) as $usage) {
            if ($usage->exists($lang)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Codes of the active additional languages ordered by weight; empty while multilingual is off.
     *
     * @return array<int, string>
     */
    public function active(): array
    {
        if (! $this->isMultilingual()) {
            return [];
        }

        return AppLanguage::query()->where('is_active', true)->orderBy('weight')->orderBy('id')->pluck('code')->all();
    }

    /**
     * Normalise a language code; `default` stays `default`.
     */
    public function normalize(string $lang): string
    {
        return AppLanguage::normalize($lang);
    }

    /**
     * Validate that values may be read or queried in $lang (active or not).
     *
     * @return string The normalised language code.
     *
     * @throws ValidationException
     */
    public function assertReadable(string $lang): string
    {
        return $this->assert($lang, requireActive: false);
    }

    /**
     * Validate that values may be written in $lang (listed and active).
     *
     * @return string The normalised language code.
     *
     * @throws ValidationException
     */
    public function assertWritable(string $lang): string
    {
        return $this->assert($lang, requireActive: true);
    }

    private function assert(string $lang, bool $requireActive): string
    {
        $lang = $this->normalize($lang);

        if ($lang === config('content.default_lang')) {
            return $lang;
        }

        if (! $this->isMultilingual()) {
            throw ValidationException::withMessages(['lang' => "Multilingual content is disabled; language [{$lang}] is not available."]);
        }

        $language = AppLanguage::query()->where('code', $lang)->first();

        if ($language === null) {
            throw ValidationException::withMessages(['lang' => "Unknown content language [{$lang}]."]);
        }

        if ($requireActive && ! $language->is_active) {
            throw ValidationException::withMessages(['lang' => "The content language [{$lang}] is not active."]);
        }

        return $lang;
    }
}
