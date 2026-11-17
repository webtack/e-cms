import { Badge } from '@/Components/ui/badge';
import { useTranslation } from '@/hooks/useTranslation';

/** Computed status of a menu item (see `MenuItem::status()` on the server). */
export type MenuItemStatus = 'published' | 'scheduled' | 'expired' | 'disabled';

/** Translation key of each status. */
const STATUS_LABEL: Record<MenuItemStatus, string> = {
    published: 'Published',
    scheduled: 'Scheduled',
    expired: 'Expired',
    disabled: 'Disabled',
};

/** Badge tone of each status: published is success, scheduled info, expired warning, disabled neutral. */
const STATUS_VARIANT: Record<MenuItemStatus, 'success' | 'info' | 'warning' | 'outline'> = {
    published: 'success',
    scheduled: 'info',
    expired: 'warning',
    disabled: 'outline',
};

/**
 * The badge of a menu item status; `data-status` lets tests and styles tell the states apart.
 *
 * @example
 * <MenuItemStatusBadge status="scheduled" />
 */
export function MenuItemStatusBadge({ status }: { status: MenuItemStatus }) {
    const { t } = useTranslation();

    return (
        <Badge variant={STATUS_VARIANT[status]} data-status={status}>
            {t(STATUS_LABEL[status])}
        </Badge>
    );
}
