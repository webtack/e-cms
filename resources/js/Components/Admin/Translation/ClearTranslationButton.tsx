import { router } from '@inertiajs/react';
import { useState } from 'react';

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
import { Button } from '@/Components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    /** Address that clears the translation of the current language (`DELETE`). */
    clearUrl: string;
};

/**
 * The destructive "Clear translation" button with its confirmation dialog, shared by every translation mode
 * (it sits in the `FormActionBar` of each entity form). Confirming sends `DELETE clearUrl`.
 *
 * @example
 * {translation.exists && <ClearTranslationButton clearUrl={`${base}/${node.uuid}/translations/${lang}`} />}
 */
export function ClearTranslationButton({ clearUrl }: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button type="button" variant="destructive" onClick={() => setOpen(true)}>
                {t('Clear translation')}
            </Button>
            <AlertDialog open={open} onOpenChange={setOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t('Clear translation')}</AlertDialogTitle>
                        <AlertDialogDescription>{t('Remove the translation in this language? The base values will be shown instead.')}</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>{t('Cancel')}</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" onClick={() => router.delete(clearUrl, { onFinish: () => setOpen(false) })}>
                            {t('Clear translation')}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
