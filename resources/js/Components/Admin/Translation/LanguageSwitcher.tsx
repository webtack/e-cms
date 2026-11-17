import { Check } from 'lucide-react';

import { Button } from '@/Components/ui/button';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { useDirtyGuard } from '@/hooks/useDirtyGuard';
import { useTranslation } from '@/hooks/useTranslation';
import type { TranslationState } from '@/types/translation';

type Props = {
    translation: TranslationState;
    /** Address of the edit page without `lang`; a language adds `?lang=<code>`. */
    editUrl: string;
    /** Whether the form below holds unsaved changes; switching then asks for confirmation. */
    dirty: boolean;
};

/**
 * The language switcher above an edit form: the base language first (marked as the base), then the active
 * additional languages, each marked when the entity already has a translation in it. Choosing a language opens the
 * same entity with the matching `?lang=`; with unsaved changes it first asks for confirmation and cancelling keeps
 * the form as it is.
 *
 * @example
 * {translation && <LanguageSwitcher translation={translation} editUrl={`${base}/${term.uuid}/edit`} dirty={form.isDirty} />}
 */
export function LanguageSwitcher({ translation, editUrl, dirty }: Props) {
    const { t } = useTranslation();
    const guard = useDirtyGuard(dirty);

    const tab = (code: string | null, label: string, translated: boolean) => {
        const active = (translation.lang ?? null) === code;

        return (
            <Button
                key={code ?? 'base'}
                type="button"
                size="sm"
                variant={active ? 'default' : 'outline'}
                aria-current={active ? 'page' : undefined}
                data-testid={`language-tab-${code ?? 'base'}`}
                onClick={() => !active && guard.go(code === null ? editUrl : `${editUrl}?lang=${encodeURIComponent(code)}`)}
            >
                {label}
                {translated && <Check aria-label={t('Translated')} data-testid={`translated-marker-${code}`} />}
            </Button>
        );
    };

    return (
        <>
            <nav aria-label={t('Language')} className="mb-6 flex flex-wrap gap-2" data-testid="language-switcher">
                {tab(null, `${translation.baseLang} (${t('Base')})`, false)}
                {translation.languages.map((language) => tab(language.code, `${language.name} (${language.code})`, language.translated))}
            </nav>

            <AlertDialog open={guard.pending !== null} onOpenChange={(open) => !open && guard.cancel()}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t('Unsaved changes')}</AlertDialogTitle>
                        <AlertDialogDescription>{t('The changes you made will be lost if you switch the language.')}</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>{t('Stay')}</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" onClick={guard.confirm}>
                            {t('Discard changes')}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
