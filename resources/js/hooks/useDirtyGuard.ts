import { router } from '@inertiajs/react';
import { useState } from 'react';

/**
 * Guards leaving a form with unsaved changes: `go(href)` visits at once when the form is clean and otherwise
 * remembers the address in `pending` so the caller can ask for confirmation. `confirm()` leaves, `cancel()` stays
 * (the form keeps its changes because nothing was navigated).
 *
 * @example
 * const guard = useDirtyGuard(form.isDirty);
 * <button onClick={() => guard.go('/admin/taxonomy/categories/terms/0190/edit?lang=en')}>en</button>
 * <AlertDialog open={guard.pending !== null} ...> <Action onClick={guard.confirm} /> </AlertDialog>
 */
export function useDirtyGuard(dirty: boolean) {
    const [pending, setPending] = useState<string | null>(null);

    return {
        pending,
        go: (href: string) => {
            if (dirty) {
                setPending(href);
            } else {
                router.get(href);
            }
        },
        confirm: () => {
            if (pending !== null) {
                router.get(pending);
            }
            setPending(null);
        },
        cancel: () => setPending(null),
    };
}
