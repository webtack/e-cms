import type { ReactNode } from 'react';

/** Column data type; it decides the available filter operators and the filter input. */
export type ColumnType = 'text' | 'number' | 'date' | 'boolean' | 'select';

/** Filter operator. Names match the content query operators (`docs/content-querying.md`). */
export type FilterOperator = 'eq' | 'contains' | 'gt' | 'gte' | 'lt' | 'lte' | 'between';

export type SortDirection = 'asc' | 'desc';

/** Operators allowed for each column type, as sent by the server in `operators`. */
export type OperatorsByType = Record<ColumnType, FilterOperator[]>;

/** One option of a `select` column. `label` is a translation key. */
export type SelectOption = {
    value: string;
    label: string;
};

/**
 * Declarative column description.
 *
 * @example
 * const columns: Column<Product>[] = [
 *     { key: 'name', header: 'Name', type: 'text', sortable: true, filterable: true, hideable: false },
 *     { key: 'price', header: 'Price', type: 'number', sortable: true, filterable: true },
 *     { key: 'status', header: 'Status', type: 'select', options: [{ value: 'draft', label: 'Draft' }],
 *       cell: (row) => <Badge>{row.status}</Badge> },
 * ];
 */
export type Column<Row> = {
    /** Row property name; also the column name used in the address and by the server. */
    key: string;
    /** Translation key of the header. */
    header: string;
    type: ColumnType;
    sortable?: boolean;
    filterable?: boolean;
    /** Whether the user may hide the column. Defaults to `true`. */
    hideable?: boolean;
    /** Whether the column is visible when the user has no saved choice. Defaults to `true`. */
    defaultVisible?: boolean;
    /** Whether the search box looks into this column. Defaults to `true` for `text` columns only. */
    searchable?: boolean;
    /** Options for `select` columns. */
    options?: SelectOption[];
    /**
     * Shows this `select` column as a drop-down next to the search box (a quick filter) instead of through the
     * filter builder. Picking a value sets an `eq` filter on the column, the first item clears it. The filter is
     * an ordinary one: it appears as a chip, in the address and is cleared by Reset. Needs `options`.
     *
     * @example
     * { key: 'mime', header: 'Type', type: 'select', quickFilter: true, allLabel: 'All types', options: typeOptions }
     */
    quickFilter?: boolean;
    /** Translation key of the item that clears a quick filter. Defaults to `All`. */
    allLabel?: string;
    /**
     * Maximum width of the body cells' content (any CSS length). Longer content is cut to one
     * line with an ellipsis; shorter content does not stretch the column. It is an upper bound
     * only (no minimum or fixed width). The header gets the same `max-width`, but its text is
     * never cut.
     *
     * @example
     * { key: 'title', header: 'Title', type: 'text', width: '24rem' }
     */
    width?: string;
    /**
     * `boolean` columns only: shows a false value as a warning badge instead of quiet muted text
     * (for example an inactive user). Ignored when `cell` is set.
     *
     * @example
     * { key: 'is_active', header: 'Active', type: 'boolean', booleanTone: 'warning' }
     */
    booleanTone?: 'warning';
    /** Custom cell renderer; defaults to the plain `row[key]` value. */
    cell?: (row: Row) => ReactNode;
};

/** One filter: column, operator and value (`between` takes `[from, to]`). */
export type TableFilter = {
    column: string;
    operator: FilterOperator;
    value: string | [string, string];
};

/** Complete state of a table: what the user asked for. */
export type TableState = {
    search: string;
    filters: TableFilter[];
    /** Column key, or `null` for the source order. */
    sort: string | null;
    dir: SortDirection | null;
    page: number;
    perPage: number;
};

/** Pagination summary of the current result. */
export type TableMeta = {
    page: number;
    perPage: number;
    total: number;
    lastPage: number;
    /** 1-based index of the first shown row (0 when empty). */
    from: number;
    /** 1-based index of the last shown row (0 when empty). */
    to: number;
};

/** An address parameter the server discarded and why. */
export type IgnoredParam = {
    param: string;
    reason: string;
};

/** Shape of the table prop produced by `Webtack\Ecms\Support\Table\TablePage`. */
export type TablePageProps<Row> = {
    rows: Row[];
    meta: TableMeta;
    /** Normalized state the server applied. */
    query: TableState;
    ignored: IgnoredParam[];
    operators: OperatorsByType;
};

/**
 * What the shared table view needs, whichever source produced it
 * (`useTableState` for the server, `useClientTableState` for the browser).
 */
export type TableController<Row> = {
    rows: Row[];
    /** The state that is applied to `rows`. */
    state: TableState;
    meta: TableMeta;
    ignored: IgnoredParam[];
    operators: OperatorsByType;
    /** True while a request is in flight (server mode only). */
    loading: boolean;
    setSearch: (search: string) => void;
    setFilters: (filters: TableFilter[]) => void;
    /** Cycles the sort of a column: ascending → descending → none. */
    toggleSort: (key: string) => void;
    setPage: (page: number) => void;
    setPerPage: (perPage: number) => void;
    /** Clears search, filters and sort and returns to the first page. */
    reset: () => void;
};

/** Source of the data: the server filters via the address, the client filters all rows in the browser. */
export type TableMode = 'server' | 'client';

/** Props shared by both modes. */
export type DataTableBaseProps<Row> = {
    /** Identifies the table for the saved column visibility (`admin.table.{tableId}.columns`). */
    tableId: string;
    columns: Column<Row>[];
    /** Stable unique row key; required for drag-and-drop later and for React keys. */
    getRowId: (row: Row) => string | number;
    /**
     * Optional leading cell of every row (extension point for custom content). With `reorder`,
     * the drag handle is rendered in this same cell, before whatever this returns.
     */
    rowHandle?: (row: Row) => ReactNode;
    /**
     * Optional trailing actions cell of every row (typically a "⋮" menu). It is not a
     * `Column`: it cannot be hidden and takes no part in search, filters or sorting.
     *
     * @example
     * <DataTable rowActions={(row) => <RowMenu id={row.id} />} ... />
     */
    rowActions?: (row: Row) => ReactNode;
    /**
     * Optional controls placed at the end of the toolbar (e.g. a "Create" button).
     *
     * @example
     * <DataTable toolbarActions={<Button>Create</Button>} ... />
     */
    toolbarActions?: ReactNode;
    /**
     * Shows the filter button in the toolbar. Off by default: a table without it has no filter
     * controls. Which columns can be filtered is still decided by each column's `filterable`.
     *
     * @example
     * <DataTable filterable columns={columns} ... />
     */
    filterable?: boolean;
    /**
     * Translation key of the text shown when the table has no rows and nothing was searched or filtered.
     * Defaults to `No data yet.`.
     *
     * @example
     * <DataTable emptyMessage="No files yet." ... />
     */
    emptyMessage?: string;
};

/**
 * Row reordering by dragging (client mode only). The table does not keep the order itself: it
 * reports the new order of all row ids and the page decides what to do (update its rows, save).
 *
 * @example
 * const reorder: ReorderOptions = { onReorder: (ids) => setFields(sortLike(ids)) };
 */
export type ReorderOptions = {
    /** Called after a drop that moved a row, with the ids of all rows in the new order. */
    onReorder: (orderedIds: string[]) => void;
};

export const DEFAULT_PER_PAGE = 15;
export const PER_PAGE_OPTIONS = [15, 30, 50, 100] as const;
export const MAX_PER_PAGE = 100;

/** Fallback copy of the operator table; the server value in `operators` wins. */
export const OPERATORS_BY_TYPE: OperatorsByType = {
    text: ['eq', 'contains'],
    number: ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
    date: ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
    boolean: ['eq'],
    select: ['eq'],
};

export const EMPTY_STATE: TableState = {
    search: '',
    filters: [],
    sort: null,
    dir: null,
    page: 1,
    perPage: DEFAULT_PER_PAGE,
};
