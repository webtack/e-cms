import { Link } from '@inertiajs/react';

import { DashboardWidget } from '@/Components/Admin/Dashboard/DashboardWidget';
import type { FavoriteTile } from '@/Components/Admin/Dashboard/types';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * Tiles of the user's favorite types with the number of nodes the user may see; a tile opens the node list.
 *
 * @example
 * <FavoritesWidget favorites={[{ key: 'news', name: 'News', kind: 'content', href: '/admin/content-types/news/nodes', count: 7 }]} />
 */
export function FavoritesWidget({ favorites }: { favorites: FavoriteTile[] }) {
    const { t, locale } = useTranslation();

    return (
        <DashboardWidget id="favorites" title={t('Favorites')}>
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                {favorites.map((tile) => (
                    <Link
                        key={tile.key}
                        href={tile.href}
                        data-testid="dashboard-favorite"
                        className="rounded-md border p-3 transition-colors hover:bg-muted"
                    >
                        <span className="block truncate text-sm">{tile.name}</span>
                        <span className="block text-xl font-semibold tabular-nums">{tile.count.toLocaleString(locale)}</span>
                    </Link>
                ))}
            </div>
        </DashboardWidget>
    );
}
