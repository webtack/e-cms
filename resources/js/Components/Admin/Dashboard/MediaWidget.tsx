import { Link } from '@inertiajs/react';

import { DashboardWidget } from '@/Components/Admin/Dashboard/DashboardWidget';
import type { MediaSummary } from '@/Components/Admin/Dashboard/types';
import { useTranslation } from '@/hooks/useTranslation';
import { formatDateTime, formatSize } from '@/lib/format';

/**
 * The media library summary: file count, total size and the latest uploads (each links to the file page). An empty
 * library shows a message instead of the list.
 *
 * @example
 * <MediaWidget media={{ count: 12, size: 5242880, latest: [] }} />
 */
export function MediaWidget({ media }: { media: MediaSummary }) {
    const { t, locale } = useTranslation();

    return (
        <DashboardWidget id="media" title={t('Media Library')}>
            <p className="text-sm">
                <span className="text-xl font-semibold tabular-nums" data-testid="dashboard-media-count">
                    {media.count.toLocaleString(locale)}
                </span>{' '}
                <span className="text-muted-foreground">
                    {t('files')} · {formatSize(media.size)}
                </span>
            </p>
            {media.latest.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t('No files yet.')}</p>
            ) : (
                <ul className="divide-y">
                    {media.latest.map((file) => (
                        <li key={file.uuid} className="flex items-baseline justify-between gap-3 py-2 first:pt-0 last:pb-0">
                            <Link href={file.url} className="min-w-0 truncate text-link hover:underline">
                                {file.name}
                            </Link>
                            <span className="shrink-0 text-xs text-muted-foreground">
                                {formatSize(file.size)} · {formatDateTime(file.created_at, locale)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </DashboardWidget>
    );
}
