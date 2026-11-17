import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-react';

import { Button } from '@/Components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';

import { PER_PAGE_OPTIONS, type TableMeta } from './types';

type Props = {
    meta: TableMeta;
    onPage: (page: number) => void;
    onPerPage: (perPage: number) => void;
};

/**
 * Range summary, page size select and first / previous / next / last buttons.
 * Actions that cannot do anything (back on the first page, forward on the
 * last) are disabled.
 *
 * @example
 * <DataTablePagination meta={controller.meta} onPage={controller.setPage} onPerPage={controller.setPerPage} />
 */
export function DataTablePagination({ meta, onPage, onPerPage }: Props) {
    const { t } = useTranslation();
    const atStart = meta.page <= 1;
    const atEnd = meta.page >= meta.lastPage;

    return (
        <div className="flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between">
            <p className="text-muted-foreground" data-testid="table-summary">
                {meta.total > 0
                    ? t('Showing :from–:to of :total', { from: meta.from, to: meta.to, total: meta.total })
                    : t('No rows')}
            </p>
            <div className="flex flex-wrap items-center gap-4">
                <div className="flex items-center gap-2">
                    <span className="text-muted-foreground">{t('Rows per page')}</span>
                    <Select value={String(meta.perPage)} onValueChange={(value) => onPerPage(Number(value))}>
                        <SelectTrigger size="sm" aria-label={t('Rows per page')}>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {PER_PAGE_OPTIONS.map((option) => (
                                <SelectItem key={option} value={String(option)}>
                                    {option}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <span className="text-muted-foreground">
                    {t('Page :page of :lastPage', { page: meta.page, lastPage: meta.lastPage })}
                </span>
                <div className="flex items-center gap-1">
                    <Button variant="outline" size="icon-sm" disabled={atStart} onClick={() => onPage(1)} aria-label={t('First page')}>
                        <ChevronsLeft aria-hidden />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon-sm"
                        disabled={atStart}
                        onClick={() => onPage(meta.page - 1)}
                        aria-label={t('Previous page')}
                    >
                        <ChevronLeft aria-hidden />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon-sm"
                        disabled={atEnd}
                        onClick={() => onPage(meta.page + 1)}
                        aria-label={t('Next page')}
                    >
                        <ChevronRight aria-hidden />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon-sm"
                        disabled={atEnd}
                        onClick={() => onPage(meta.lastPage)}
                        aria-label={t('Last page')}
                    >
                        <ChevronsRight aria-hidden />
                    </Button>
                </div>
            </div>
        </div>
    );
}
