import { router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';

import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    /** The computed status of the row; `disabled` means `is_active` is false. */
    status: 'published' | 'scheduled' | 'expired' | 'disabled';
    /**
     * The `PATCH …/active` address of the row. Without it (a row the user may not edit) the badge is shown as is,
     * not clickable.
     */
    url?: string;
    /** The display-only badge (`NodeStatusBadge` / `MenuItemStatusBadge`); it keeps its own `data-status`. */
    children: ReactNode;
    /** Props to reload after the request; all props when omitted. */
    only?: string[];
};

/**
 * A status badge that flips only `is_active` of its row with one click, without a confirmation. The client sends
 * the target value (`status === 'disabled'` turns the flag on, any other status turns it off), so a double click
 * or a stale list cannot flip the state the wrong way; the dates are never touched on the server. The button is
 * disabled while the request is in flight, keeps the scroll position, and does not let the click reach the row or
 * the table header. Its accessible name says what the click does ("Disable" / "Enable").
 *
 * @example
 * <StatusToggleBadge status={row.status} url={`/admin/menus/main/items/${row.uuid}/active`}>
 *     <MenuItemStatusBadge status={row.status} />
 * </StatusToggleBadge>
 */
export function StatusToggleBadge({ status, url, children, only }: Props) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState(false);

    if (!url) {
        return <>{children}</>;
    }

    const enable = status === 'disabled';

    return (
        <button
            type="button"
            data-testid="status-toggle"
            aria-label={t(enable ? 'Enable' : 'Disable')}
            title={t(enable ? 'Enable' : 'Disable')}
            disabled={busy}
            className="cursor-pointer rounded-4xl focus-visible:outline-none disabled:cursor-wait disabled:opacity-60"
            onClick={(event) => {
                event.stopPropagation();
                router.patch(
                    url,
                    { is_active: enable },
                    {
                        preserveScroll: true,
                        preserveState: true,
                        ...(only ? { only } : {}),
                        onStart: () => setBusy(true),
                        onFinish: () => setBusy(false),
                    },
                );
            }}
        >
            {children}
        </button>
    );
}
