import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

import { TOAST_ERROR_DURATION, TOAST_SUCCESS_DURATION } from '@/lib/toastDurations';

/**
 * Shows a toast for the `flash.success` / `flash.error` messages shared by
 * the server, with per-status durations. Flash data lives for one request, so a message is not repeated
 * on later visits.
 *
 * The effect is keyed on `flash.id`, which the server regenerates for every response that carries a
 * message. Keying on the text would skip a second toast when two consecutive messages are equal.
 */
export function FlashToaster() {
    const { flash } = usePage().props;

    useEffect(() => {
        if (!flash.id) {
            return;
        }

        if (flash.success) {
            toast.success(flash.success, { duration: TOAST_SUCCESS_DURATION });
        }

        if (flash.error) {
            toast.error(flash.error, { duration: TOAST_ERROR_DURATION });
        }
        // The id intentionally identifies the message; the texts are read from the same payload.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [flash.id]);

    return null;
}
