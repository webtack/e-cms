import { router } from '@inertiajs/react';

import { useTheme, type Theme } from '@/Components/Admin/ThemeProvider';
import { FormSection } from '@/Components/Admin/FormSection';
import { Label } from '@/Components/ui/label';
import { useTranslation } from '@/hooks/useTranslation';

const THEMES: Theme[] = ['light', 'dark', 'system'];

/**
 * "Settings" section of the profile page: the interface language (saved for the user through `POST /locale`, like
 * the header switcher) and the colour theme (light / dark / system, kept only in this browser's `localStorage`).
 * Both apply immediately; there is no save button.
 *
 * @example
 * <ProfileSettings />
 */
export function ProfileSettings() {
    const { t, locale, locales } = useTranslation();
    const { theme, setTheme } = useTheme();

    const changeLocale = (code: string) => {
        if (code === locale) {
            return;
        }

        router.post('/locale', { locale: code }, { preserveScroll: true, preserveState: true });
    };

    const labels: Record<Theme, string> = { light: t('Light'), dark: t('Dark'), system: t('System') };

    return (
        <FormSection as="section" title={t('Settings')} titleId="profile-settings-heading" className="max-w-xl space-y-4" data-testid="profile-settings">
            <div className="space-y-2">
                <Label htmlFor="profile-locale">{t('Language')}</Label>
                <select
                    id="profile-locale"
                    value={locale}
                    onChange={(e) => changeLocale(e.target.value)}
                    className="h-9 w-full rounded-md border bg-card px-2 text-sm"
                >
                    {locales.map((option) => (
                        <option key={option.code} value={option.code}>
                            {option.name}
                        </option>
                    ))}
                </select>
            </div>

            <div className="space-y-2">
                <Label htmlFor="profile-theme">{t('Theme')}</Label>
                <select
                    id="profile-theme"
                    value={theme}
                    onChange={(e) => setTheme(e.target.value as Theme)}
                    className="h-9 w-full rounded-md border bg-card px-2 text-sm"
                >
                    {THEMES.map((option) => (
                        <option key={option} value={option}>
                            {labels[option]}
                        </option>
                    ))}
                </select>
            </div>
        </FormSection>
    );
}
