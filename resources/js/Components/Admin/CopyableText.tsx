import type { MouseEvent, ReactNode } from 'react';
import { toast } from 'sonner';

import { useTranslation } from '@/hooks/useTranslation';
import { TOAST_SUCCESS_DURATION } from '@/lib/toastDurations';
import { cn } from '@/lib/utils';

type Props = {
    /** The text placed in the clipboard (it may be longer than what is displayed). */
    value: string;
    /** What is displayed; clipped to one line with an ellipsis when it does not fit. */
    children: ReactNode;
    /** Hint on hover; defaults to the copied value. */
    title?: string;
    className?: string;
};

/**
 * Writes `text` to the clipboard: the Clipboard API where it exists (secure contexts), otherwise a hidden `textarea`
 * and `document.execCommand('copy')` (plain `http://` hosts other than localhost).
 *
 * @returns whether the text was copied
 */
async function copyToClipboard(text: string): Promise<boolean> {
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);

            return true;
        }
    } catch {
        // Denied or unavailable: fall through to the legacy path.
    }

    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    try {
        return document.execCommand('copy');
    } catch {
        return false;
    } finally {
        document.body.removeChild(area);
    }
}

/**
 * A value that is copied to the clipboard on click. It is a native `button`, so it is focusable and `Enter` / `Space`
 * copy it too. The click does not bubble, so it cannot trigger an action of the row or the container around it. A
 * short toast confirms the copy (or reports a failure).
 *
 * @example
 * <CopyableText value={row.slug} title={row.slug}>{row.slug}</CopyableText>
 */
export function CopyableText({ value, children, title, className }: Props) {
    const { t } = useTranslation();

    const copy = async (event: MouseEvent) => {
        event.stopPropagation();
        if (await copyToClipboard(value)) {
            toast.success(t('Copied to clipboard'), { duration: TOAST_SUCCESS_DURATION });
        } else {
            toast.error(t('Could not copy to the clipboard'));
        }
    };

    return (
        <button type="button" title={title ?? value} aria-label={t('Copy: :value', { value })} onClick={copy} className={cn('block max-w-full cursor-copy truncate rounded-sm text-start hover:underline focus-visible:outline-2 focus-visible:outline-ring', className)}>
            {children}
        </button>
    );
}
