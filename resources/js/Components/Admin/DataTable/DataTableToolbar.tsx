import { RotateCcw, Search } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';

import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';

import { DataTableColumnMenu } from './DataTableColumnMenu';
import { DataTableFilters } from './DataTableFilters';
import { filterValue, isNarrowed, withColumnFilter } from './stateActions';
import type { Column, TableController } from './types';

/** Delay after the last keystroke before the search is applied. */
export const SEARCH_DEBOUNCE_MS = 300;

type Props<Row> = {
    controller: TableController<Row>;
    columns: Column<Row>[];
    isVisible: (column: Column<Row>) => boolean;
    onToggleColumn: (key: string) => void;
    /** Page-supplied controls shown at the end of the row, wrapping on narrow screens. */
    actions?: ReactNode;
    /** Shows the filter button; without it the toolbar has no filter controls. */
    filterable?: boolean;
};

/** Value of the item that clears a quick filter (a Radix item cannot have an empty value). */
const ALL = '__all__';

/**
 * Search box (applied {@link SEARCH_DEBOUNCE_MS} ms after typing stops), the
 * quick filters (a drop-down for each column with `quickFilter`, read from and written to the table filters),
 * the filter builder (only with `filterable`), the column menu and a reset button. The box follows the
 * applied search, so Back or Reset clear it too. `actions` (e.g. a Create button)
 * is placed at the end of the row.
 *
 * @example
 * <DataTableToolbar controller={controller} columns={columns} isVisible={isVisible} onToggleColumn={toggle} />
 */
export function DataTableToolbar<Row>({ controller, columns, isVisible, onToggleColumn, actions, filterable = false }: Props<Row>) {
    const { t } = useTranslation();
    const { state, setSearch } = controller;
    const [value, setValue] = useState(state.search);
    const latest = useRef({ applied: state.search, setSearch });

    latest.current = { applied: state.search, setSearch };

    // Follow the applied search (Back button, Reset, shared link).
    useEffect(() => {
        setValue(state.search);
    }, [state.search]);

    // Apply the typed search after a pause.
    useEffect(() => {
        if (value === latest.current.applied) {
            return;
        }

        const timer = window.setTimeout(() => latest.current.setSearch(value), SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
    }, [value]);

    const canReset = isNarrowed(state) || value !== '';
    const quickFilters = columns.filter((column) => column.quickFilter && column.type === 'select');

    return (
        <div className="flex flex-wrap items-center gap-2">
            <div className="relative w-full sm:w-64">
                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden />
                <Input
                    type="search"
                    className="pl-8"
                    placeholder={t('Search')}
                    aria-label={t('Search')}
                    value={value}
                    onChange={(e) => setValue(e.target.value)}
                />
            </div>
            {quickFilters.map((column) => (
                <Select
                    key={column.key}
                    value={filterValue(state.filters, column.key) ?? ALL}
                    onValueChange={(next) => controller.setFilters(withColumnFilter(state.filters, column.key, next === ALL ? null : next))}
                >
                    <SelectTrigger size="sm" aria-label={t(column.header)} className="w-40" data-testid={`quick-filter-${column.key}`}>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>{t(column.allLabel ?? 'All')}</SelectItem>
                        {(column.options ?? []).map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {t(option.label)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            ))}
            {filterable && (
                <DataTableFilters
                    columns={columns}
                    operators={controller.operators}
                    filters={state.filters}
                    onChange={controller.setFilters}
                />
            )}
            <DataTableColumnMenu columns={columns} isVisible={isVisible} onToggle={onToggleColumn} />
            {canReset && (
                <Button variant="ghost" size="sm" onClick={controller.reset}>
                    <RotateCcw aria-hidden />
                    {t('Reset')}
                </Button>
            )}
            {actions && <div className="flex flex-wrap items-center gap-2 sm:ml-auto">{actions}</div>}
        </div>
    );
}
