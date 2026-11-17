/** Translations of one locale: English phrase (key) → translated text. */
export type Messages = Record<string, string>;

/** Values for `:name` placeholders. */
export type Replacements = Record<string, string | number>;

/**
 * Looks up `key` in `messages` and substitutes `:name` placeholders.
 *
 * A missing key is returned as is (keys are the English phrases), so an
 * untranslated string never breaks a page. Like Laravel, longer placeholder
 * names are replaced first so `:minutes` is not clobbered by `:min`.
 *
 * @example
 * translate({ 'Retry in :seconds s': 'Повтор через :seconds с' }, 'Retry in :seconds s', { seconds: 30 });
 * // 'Повтор через 30 с'
 *
 * @example
 * translate({}, 'Log in'); // 'Log in'
 */
export function translate(messages: Messages, key: string, replace: Replacements = {}): string {
    let text = messages[key] ?? key;

    const names = Object.keys(replace).sort((a, b) => b.length - a.length);

    for (const name of names) {
        text = text.replaceAll(`:${name}`, String(replace[name]));
    }

    return text;
}
