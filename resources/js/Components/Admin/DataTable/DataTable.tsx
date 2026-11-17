import { closestCenter, DndContext, KeyboardSensor, PointerSensor, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core';
import { arrayMove, SortableContext, sortableKeyboardCoordinates, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';

import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

import { DataTableFilterChips } from './DataTableFilters';
import { DataTablePagination } from './DataTablePagination';
import { DataTableRow } from './DataTableRow';
import { DataTableToolbar } from './DataTableToolbar';
import { isNarrowed } from './stateActions';
import type { DataTableBaseProps, ReorderOptions, TableController, TablePageProps } from './types';
import { useClientTableState } from './useClientTableState';
import { useColumnVisibility } from './useColumnVisibility';
import { useTableState } from './useTableState';

type ServerProps<Row> = DataTableBaseProps<Row> & {
    mode?: 'server';
    /** The table prop built by `Webtack\Ecms\Support\Table\TablePage`. */
    data: TablePageProps<Row>;
    /** Name of that prop on the page, used to reload only it. Defaults to `table`. */
    prop?: string;
};

type ClientProps<Row> = DataTableBaseProps<Row> & {
    mode: 'client';
    /** All rows; search, filters, sort and paging run in the browser. */
    rows: Row[];
    /**
     * Whether the rows are split into pages. `false` shows every matching row at once and hides the pagination
     * bar; use it for trees and other lists that are reordered by dragging. Defaults to `true`.
     */
    paginated?: boolean;
    /**
     * Turns on row reordering by dragging (mouse and keyboard). Each row gets a handle in the
     * leading cell; after a drop `onReorder` receives the ids of all rows in the new order and the
     * page updates its `rows`. Handles are disabled (with a hint) unless the whole list is shown in
     * its source order: no search, filters or sorting, and all rows on one page. Without this prop
     * the markup is unchanged.
     *
     * @example
     * <DataTable mode="client" rows={fields} reorder={{ onReorder: (ids) => saveOrder(ids) }} ... />
     */
    reorder?: ReorderOptions;
};

export type DataTableProps<Row> = ServerProps<Row> | ClientProps<Row>;

/**
 * Admin data table with search, filters, sorting, pagination and column
 * visibility. In `server` mode (default) the state lives in the address and the
 * server does the work; in `client` mode all rows are passed in and the browser
 * does it, without requests. Look and behaviour are the same.
 *
 * @example
 * // Server: pair with TablePage::fromCollection() or TablePage::make() in the controller.
 * <DataTable tableId="products" columns={columns} getRowId={(r) => r.id} data={props.table} />
 *
 * @example
 * // Client: a small set, e.g. content languages.
 * <DataTable mode="client" tableId="languages" columns={columns} getRowId={(r) => r.code} rows={languages} />
 *
 * @example
 * // Client with drag-and-drop ordering: the page owns the order.
 * <DataTable mode="client" tableId="fields" columns={columns} getRowId={(r) => r.key} rows={fields}
 *     reorder={{ onReorder: (ids) => setFields(sortByIds(fields, ids)) }} />
 */
export function DataTable<Row extends object>(props: DataTableProps<Row>) {
    return props.mode === 'client' ? <ClientDataTable {...props} /> : <ServerDataTable {...props} />;
}

function ServerDataTable<Row extends object>({ data, prop, ...base }: ServerProps<Row>) {
    return <DataTableView controller={useTableState(data, prop)} {...base} />;
}

function ClientDataTable<Row extends object>({ rows, reorder, paginated = true, ...base }: ClientProps<Row>) {
    return <DataTableView controller={useClientTableState(rows, base.columns, paginated)} reorder={reorder} paginated={paginated} {...base} />;
}

type ViewProps<Row> = DataTableBaseProps<Row> & { controller: TableController<Row>; reorder?: ReorderOptions; paginated?: boolean };

/** The shared look of both modes: it only talks to the controller. The table wrapper is a `--card` panel on the muted page. */
function DataTableView<Row extends object>({ controller, tableId, columns, getRowId, rowHandle, rowActions, toolbarActions, reorder, paginated = true, filterable = false, emptyMessage = 'No data yet.' }: ViewProps<Row>) {
    const { t } = useTranslation();
    const { visibleColumns, isVisible, toggle } = useColumnVisibility(tableId, columns);
    const { state, rows, meta, loading } = controller;
    const narrowed = isNarrowed(state);
    const hasHandle = !!rowHandle || !!reorder;
    const colSpan = visibleColumns.length + (hasHandle ? 1 : 0) + (rowActions ? 1 : 0);

    // Dragging is only meaningful when the whole list is visible in its source order.
    const canReorder = state.search === '' && state.filters.length === 0 && state.sort === null && meta.total <= state.perPage;
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 4 } }), useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }));
    const ids = rows.map((row) => String(getRowId(row)));

    const onDragEnd = ({ active, over }: DragEndEvent) => {
        const from = ids.indexOf(String(active.id));
        const to = over ? ids.indexOf(String(over.id)) : -1;

        if (reorder && from >= 0 && to >= 0 && from !== to) {
            reorder.onReorder(arrayMove(ids, from, to));
        }
    };

    const body = (
        <TableBody className={cn(loading && 'opacity-60 transition-opacity')}>
            {rows.length === 0 ? (
                <TableRow>
                    <TableCell colSpan={Math.max(colSpan, 1)} className="h-24 text-center text-muted-foreground">
                        <div className="flex flex-col items-center gap-2" data-testid="table-empty">
                            <span>{narrowed ? t('Nothing found.') : t(emptyMessage)}</span>
                            {narrowed && (
                                <Button variant="outline" size="sm" onClick={controller.reset}>
                                    {t('Reset')}
                                </Button>
                            )}
                        </div>
                    </TableCell>
                </TableRow>
            ) : (
                rows.map((row) => {
                    const id = String(getRowId(row));

                    return (
                        <DataTableRow
                            key={id}
                            row={row}
                            columns={visibleColumns}
                            rowHandle={rowHandle}
                            rowActions={rowActions}
                            sortable={
                                reorder
                                    ? {
                                          id,
                                          disabled: !canReorder,
                                          disabledHint: t('Reordering is available only when all rows are shown without search, filters or sorting.'),
                                      }
                                    : undefined
                            }
                        />
                    );
                })
            )}
        </TableBody>
    );

    return (
        <div className="flex flex-col gap-3" data-testid={`data-table-${tableId}`} aria-busy={loading}>
            <DataTableToolbar controller={controller} columns={columns} isVisible={isVisible} onToggleColumn={toggle} actions={toolbarActions} filterable={filterable} />
            <DataTableFilterChips columns={columns} filters={state.filters} onChange={controller.setFilters} />

            {controller.ignored.length > 0 && (
                <div
                    role="status"
                    className="flex flex-wrap items-center justify-between gap-2 rounded-md border bg-card px-3 py-2 text-sm"
                >
                    <span>{t('Some parameters were ignored.')}</span>
                    <Button variant="outline" size="xs" onClick={controller.reset}>
                        {t('Reset')}
                    </Button>
                </div>
            )}

            <div className="rounded-md border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {hasHandle && <TableHead className="w-8" />}
                            {visibleColumns.map((column) => {
                                const sorted = state.sort === column.key;
                                const Icon = !sorted ? ArrowUpDown : state.dir === 'desc' ? ArrowDown : ArrowUp;

                                return (
                                    <TableHead
                                        key={column.key}
                                        style={column.width ? { maxWidth: column.width } : undefined}
                                        aria-sort={
                                            column.sortable
                                                ? sorted
                                                    ? state.dir === 'desc'
                                                        ? 'descending'
                                                        : 'ascending'
                                                    : 'none'
                                                : undefined
                                        }
                                    >
                                        {column.sortable ? (
                                            <button
                                                type="button"
                                                className="-mx-1 inline-flex items-center gap-1 rounded-sm px-1 py-0.5 font-medium hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                                                onClick={() => controller.toggleSort(column.key)}
                                            >
                                                {t(column.header)}
                                                <Icon className={cn('size-3.5', !sorted && 'text-muted-foreground')} aria-hidden />
                                            </button>
                                        ) : (
                                            t(column.header)
                                        )}
                                    </TableHead>
                                );
                            })}
                            {rowActions && (
                                <TableHead className="w-10">
                                    <span className="sr-only">{t('Actions')}</span>
                                </TableHead>
                            )}
                        </TableRow>
                    </TableHeader>
                    {reorder ? (
                        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
                            <SortableContext items={ids} strategy={verticalListSortingStrategy}>
                                {body}
                            </SortableContext>
                        </DndContext>
                    ) : (
                        body
                    )}
                </Table>
            </div>

            {paginated && <DataTablePagination meta={meta} onPage={controller.setPage} onPerPage={controller.setPerPage} />}
        </div>
    );
}
