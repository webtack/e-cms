import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { CountersWidget } from '@/Components/Admin/Dashboard/CountersWidget';
import { FavoritesWidget } from '@/Components/Admin/Dashboard/FavoritesWidget';
import { MediaWidget } from '@/Components/Admin/Dashboard/MediaWidget';
import { NodeListWidget } from '@/Components/Admin/Dashboard/NodeListWidget';
import { QuickCreateMenu } from '@/Components/Admin/Dashboard/QuickCreateMenu';
import type { DashboardProps } from '@/Components/Admin/Dashboard/types';
import { PageTitle } from '@/Components/Admin/PageTitle';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * The admin start page: work widgets (recent changes, my drafts, favorites) and overview widgets (counters, top by
 * views, scheduled, expired, media) in a responsive grid: one column, or two (never more) when the content area is at
 * least `@3xl` wide, chosen by the container width so that it follows the sidebar state. The columns are
 * `minmax(0,1fr)`, so a wide widget (a long title) is shortened inside its card instead of widening the page.
 * Widgets in one row are as tall as the tallest one.
 * Every role sees the same widgets; the server fills them only with what the user's rights allow, and a widget
 * without data is not rendered (see docs/admin-dashboard.md).
 * When nothing at all is available, an empty-state message is shown.
 */
export default function Dashboard({ recent, drafts, create, create_flat, favorites, counters, top, scheduled, expired, media }: DashboardProps) {
    const { t } = useTranslation();
    const empty = [recent, drafts, create, favorites, counters, top, scheduled, expired].every((list) => list.length === 0) && media === null;

    return (
        <>
            <Head title={t('Dashboard')} />
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <PageTitle>{t('Dashboard')}</PageTitle>
                {create.length > 0 && <QuickCreateMenu types={create} flat={create_flat} />}
            </div>

            {empty ? (
                <p className="text-muted-foreground" data-testid="dashboard-empty">
                    {t('You have no access to any content yet. Ask an administrator to grant it.')}
                </p>
            ) : (
                <div className="@container">
                    <div className="grid grid-cols-[minmax(0,1fr)] gap-4 @3xl:grid-cols-2">
                        {recent.length > 0 && <NodeListWidget id="recent" title={t('Recent changes')} nodes={recent} column="updated_at" showAuthor />}
                        {drafts.length > 0 && <NodeListWidget id="drafts" title={t('My drafts')} nodes={drafts} column="updated_at" />}
                        {favorites.length > 0 && <FavoritesWidget favorites={favorites} />}
                        {counters.length > 0 && <CountersWidget counters={counters} />}
                        {top.length > 0 && <NodeListWidget id="top" title={t('Most viewed')} nodes={top} column="views" />}
                        {scheduled.length > 0 && <NodeListWidget id="scheduled" title={t('Scheduled')} nodes={scheduled} column="published_at" />}
                        {expired.length > 0 && <NodeListWidget id="expired" title={t('Expired')} nodes={expired} column="unpublished_at" />}
                        {media !== null && <MediaWidget media={media} />}
                    </div>
                </div>
            )}
        </>
    );
}

Dashboard.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
