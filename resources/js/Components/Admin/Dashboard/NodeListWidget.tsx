import { Link } from '@inertiajs/react';

import { DashboardWidget } from '@/Components/Admin/Dashboard/DashboardWidget';
import type { DashboardNode } from '@/Components/Admin/Dashboard/types';
import { useTranslation } from '@/hooks/useTranslation';
import { formatDateTime } from '@/lib/format';

type Props = {
    id: string;
    title: string;
    nodes: DashboardNode[];
    /** What the right-hand column shows: the last update, the publication or unpublication moment, or the view counter. */
    column: 'updated_at' | 'published_at' | 'unpublished_at' | 'views';
    /** Show the author under the title (the recent changes list). */
    showAuthor?: boolean;
};

/**
 * A list of nodes: the title (a link to the edit form only when the server gave an `edit_url`), the type name and,
 * on the right, the value chosen by `column`. Used by recent changes, drafts, top by views, scheduled and expired.
 *
 * @example
 * <NodeListWidget id="top" title={t('Most viewed')} nodes={top} column="views" />
 */
export function NodeListWidget({ id, title, nodes, column, showAuthor = false }: Props) {
    const { t, locale } = useTranslation();

    return (
        <DashboardWidget id={id} title={title}>
            <ul className="divide-y">
                {nodes.map((node) => (
                    <li key={node.uuid} className="flex items-baseline justify-between gap-3 py-2 first:pt-0 last:pb-0" data-testid="dashboard-node">
                        <div className="min-w-0">
                            {node.edit_url ? (
                                <Link href={node.edit_url} className="block truncate text-link hover:underline">
                                    {node.title}
                                </Link>
                            ) : (
                                <span className="block truncate">{node.title}</span>
                            )}
                            <span className="block truncate text-xs text-muted-foreground">
                                {node.type.name}
                                {showAuthor && node.author ? ` · ${t('by :name', { name: node.author })}` : ''}
                            </span>
                        </div>
                        <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                            {column === 'views' ? node.views.toLocaleString(locale) : formatDateTime(node[column], locale)}
                        </span>
                    </li>
                ))}
            </ul>
        </DashboardWidget>
    );
}
