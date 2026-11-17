import { Link } from '@inertiajs/react';
import { useState } from 'react';

import { DashboardWidget } from '@/Components/Admin/Dashboard/DashboardWidget';
import type { CounterRow } from '@/Components/Admin/Dashboard/types';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { useTranslation } from '@/hooks/useTranslation';

/** How many type rows are visible before the user expands the list. */
export const COUNTERS_VISIBLE = 7;

/**
 * Nodes per type: total, published and drafts. The type name opens the node list of the type. Note that `total` can
 * exceed `published + drafts` (scheduled and expired nodes are neither). With many types only the first
 * `COUNTERS_VISIBLE` rows are shown; a toggle below the table reveals the rest.
 *
 * @example
 * <CountersWidget counters={[{ key: 'news', name: 'News', kind: 'content', href: '/admin/content-types/news/nodes', total: 10, published: 6, drafts: 2 }]} />
 */
export function CountersWidget({ counters }: { counters: CounterRow[] }) {
    const { t } = useTranslation();
    const [expanded, setExpanded] = useState(false);
    const collapsible = counters.length > COUNTERS_VISIBLE;
    const rows = collapsible && !expanded ? counters.slice(0, COUNTERS_VISIBLE) : counters;

    return (
        <DashboardWidget id="counters" title={t('Nodes by type')}>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{t('Type')}</TableHead>
                        <TableHead className="text-right">{t('Total')}</TableHead>
                        <TableHead className="text-right">{t('Published')}</TableHead>
                        <TableHead className="text-right">{t('Drafts')}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((row) => (
                        <TableRow key={row.key} data-testid="dashboard-counter">
                            <TableCell>
                                <Link href={row.href} className="text-link hover:underline">
                                    {row.name}
                                </Link>
                            </TableCell>
                            <TableCell className="text-right tabular-nums">{row.total}</TableCell>
                            <TableCell className="text-right tabular-nums">{row.published}</TableCell>
                            <TableCell className="text-right tabular-nums">{row.drafts}</TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            {collapsible && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="mt-2"
                    aria-expanded={expanded}
                    onClick={() => setExpanded((value) => !value)}
                    data-testid="dashboard-counters-toggle"
                >
                    {expanded ? t('Show fewer') : t('Show all (:count)', { count: counters.length })}
                </Button>
            )}
        </DashboardWidget>
    );
}
