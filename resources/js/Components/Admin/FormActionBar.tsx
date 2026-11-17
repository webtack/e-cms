import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { Button } from '@/Components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    /** Where "Cancel" leads (the list the form came from). */
    cancelHref: string;
    /** Disables "Save" while the form is being sent. */
    processing: boolean;
    /** Extra controls on the left of the bar (e.g. the button that opens the service panel on narrow screens). */
    children?: ReactNode;
    /** Extra classes for the bar (e.g. a grid span). */
    className?: string;
    /** Hides "Save" for a read-only form (e.g. the system role); "Cancel" stays as the way back. */
    hideSave?: boolean;
    /** Keeps "Save" disabled (e.g. in a translation mode until something changed). */
    saveDisabled?: boolean;
};

/**
 * Sticky bottom bar of every admin form: "Cancel" and "Save" stay in view while a long form scrolls. It must be the
 * last child of the `<form>` (so "Save" submits it); it is pulled out to the edges of the shell's `<main>` padding.
 * It is a `--card` (white) panel, not `--background`, so it stays visible over the muted page.
 *
 * @example
 * <form onSubmit={submit}>
 *     ...fields
 *     <FormActionBar cancelHref="/admin/content-types" processing={processing} />
 * </form>
 */
export function FormActionBar({ cancelHref, processing, children, className = '', hideSave = false, saveDisabled = false }: Props) {
    const { t } = useTranslation();

    return (
        <div
            data-testid="form-action-bar"
            className={`sticky bottom-0 z-10 -mx-4 -mb-4 flex items-center gap-2 border-t bg-card px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] md:-mx-6 md:-mb-6 md:px-6 ${className}`}
        >
            {children}
            <div className="ml-auto flex items-center gap-2">
                <Button variant="ghost" asChild>
                    <Link href={cancelHref}>{t('Cancel')}</Link>
                </Button>
                {!hideSave && (
                    <Button type="submit" disabled={processing || saveDisabled}>
                        {t('Save')}
                    </Button>
                )}
            </div>
        </div>
    );
}
