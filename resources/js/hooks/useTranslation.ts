import { usePage } from '@inertiajs/react';

import { translate, type Replacements } from '@/lib/i18n';

/**
 * Access to the interface language shared by `HandleInertiaRequests`.
 *
 * Returns `t(key, replace?)` to translate an English phrase, the current
 * locale code and the list of available locales.
 *
 * @example
 * const { t } = useTranslation();
 * <Button>{t('Log in')}</Button>
 * t('Retry in :seconds s', { seconds: 30 });
 */
export function useTranslation() {
    const { current, available, messages } = usePage().props.locale;

    return {
        t: (key: string, replace?: Replacements) => translate(messages, key, replace),
        locale: current,
        locales: available,
    };
}
