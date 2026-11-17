import { router } from '@inertiajs/react';
import { Languages } from 'lucide-react';

import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * Dropdown that switches the interface language. Lists every available locale
 * by its own name, marks the current one and posts the choice to `POST /locale`.
 * `preserveState`/`preserveScroll` keep the persistent layout (sidebar state)
 * intact while the page re-renders in the new language.
 *
 * @example
 * <LocaleSwitcher />
 */
export function LocaleSwitcher() {
    const { t, locale, locales } = useTranslation();

    const change = (code: string) => {
        if (code === locale) {
            return;
        }

        router.post('/locale', { locale: code }, { preserveScroll: true, preserveState: true });
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" aria-label={t('Change language')}>
                    <Languages />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuRadioGroup value={locale} onValueChange={change}>
                    {locales.map((option) => (
                        <DropdownMenuRadioItem key={option.code} value={option.code}>
                            {option.name}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
