/** One language entry of the translation switcher. */
export type TranslationLanguage = {
    code: string;
    name: string;
    translated: boolean;
};

/**
 * The shared `translation` prop of an edit page (see `docs/admin-translation-mode.md`); `null` when the switcher is
 * not shown (multilingual mode off or no active additional language).
 */
export type TranslationState<Values extends Record<string, unknown> = Record<string, unknown>> = {
    /** The additional language of the page, or `null` in the base mode. */
    lang: string | null;
    /** Code of the base language (shown first in the switcher). */
    baseLang: string;
    /** Active additional languages in order of weight. */
    languages: TranslationLanguage[];
    /** Prefilled values of the translatable fields in the translation mode, `null` in the base mode. */
    values: Values | null;
    /** Whether the current language already has a stored translation (enables "Clear translation"). */
    exists: boolean;
};
