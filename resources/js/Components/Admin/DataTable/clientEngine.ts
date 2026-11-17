import { isFilterComplete } from './params';
import type { Column, TableFilter, TableMeta, TableState } from './types';

/**
 * Applies search, filters, sort and paging to `rows` in the browser, with the
 * same rules as the server (`Webtack\Ecms\Support\Table\TablePage::fromCollection`,
 * see `docs/admin-data-table.md`): search and `contains` ignore case; `eq` on
 * text ignores case; numbers compare numerically, dates (`YYYY-MM-DD`)
 * chronologically, text and select by lowercase code unit; empty values
 * (`null`, `''`) never match a filter and always sort last; sorting is stable.
 * Filters the server would reject as invalid are skipped here too.
 *
 * Pure: it returns new arrays and never touches `rows` or `state`. The page is
 * brought into range, as the server does.
 *
 * @example
 * const columns = [{ key: 'name', header: 'Name', type: 'text', sortable: true }];
 * const rows = [{ name: 'b' }, { name: 'A' }, { name: null }];
 * runClientEngine(rows, columns, { ...EMPTY_STATE, sort: 'name', dir: 'desc' }).rows;
 * // [{ name: 'b' }, { name: 'A' }, { name: null }]  (empty value stays last)
 */
export function runClientEngine<Row extends object>(
    rows: Row[],
    columns: Column<Row>[],
    state: TableState,
): { rows: Row[]; meta: TableMeta } {
    const byKey = new Map(columns.map((c) => [c.key, c]));
    let result = rows;

    const term = state.search.trim().toLowerCase();

    if (term !== '') {
        const searchable = columns.filter((c) => c.searchable ?? c.type === 'text');

        result = result.filter((row) =>
            searchable.some((c) => {
                const cell = cellOf(row, c.key);

                return !isEmpty(cell) && text(cell).toLowerCase().includes(term);
            }),
        );
    }

    const filters = state.filters.filter((f) => {
        const column = byKey.get(f.column);

        return column !== undefined && column.filterable !== false && isFilterComplete(f) && isFilterValid(column, f);
    });

    if (filters.length > 0) {
        result = result.filter((row) => filters.every((f) => matches(row, byKey.get(f.column)!, f)));
    }

    const sortColumn = state.sort !== null ? byKey.get(state.sort) : undefined;

    if (sortColumn !== undefined && state.dir !== null) {
        const direction = state.dir === 'desc' ? -1 : 1;

        result = result
            .map((row, index) => ({ row, index, cell: cellOf(row, sortColumn.key) }))
            .sort((a, b) => {
                const emptyA = isEmpty(a.cell);
                const emptyB = isEmpty(b.cell);

                if (emptyA || emptyB) {
                    return emptyA === emptyB ? a.index - b.index : emptyA ? 1 : -1;
                }

                return compare(sortColumn, a.cell, text(b.cell)) * direction || a.index - b.index;
            })
            .map((entry) => entry.row);
    }

    const total = result.length;
    const lastPage = Math.max(1, Math.ceil(total / state.perPage));
    const page = Math.min(Math.max(1, state.page), lastPage);
    const start = (page - 1) * state.perPage;

    return {
        rows: result.slice(start, start + state.perPage),
        meta: {
            page,
            perPage: state.perPage,
            total,
            lastPage,
            from: total === 0 ? 0 : start + 1,
            to: total === 0 ? 0 : Math.min(page * state.perPage, total),
        },
    };
}

function cellOf(row: object, key: string): unknown {
    return (row as Record<string, unknown>)[key];
}

function isEmpty(cell: unknown): boolean {
    return cell === null || cell === undefined || cell === '';
}

function text(cell: unknown): string {
    return typeof cell === 'boolean' ? (cell ? 'true' : 'false') : String(cell);
}

function isBetweenValue(value: TableFilter['value']): value is [string, string] {
    return Array.isArray(value);
}

/** Mirrors the server's value validation (`TableRequest::normalizeValue`). */
function isFilterValid<Row>(column: Column<Row>, filter: TableFilter): boolean {
    const values = isBetweenValue(filter.value) ? filter.value : [filter.value];

    return values.every((value) => {
        switch (column.type) {
            case 'number':
                return value.trim() !== '' && Number.isFinite(Number(value));
            case 'date':
                return /^\d{4}-\d{2}-\d{2}$/.test(value) && !Number.isNaN(Date.parse(value));
            case 'boolean':
                return value === 'true' || value === 'false';
            case 'select':
                return column.options === undefined || column.options.some((o) => o.value === value);
            default:
                return value.trim() !== '';
        }
    });
}

/** Three-way comparison of a row value with a filter value, by column type. */
function compare<Row>(column: Column<Row>, cell: unknown, value: string): number {
    switch (column.type) {
        case 'number':
            return Math.sign(Number(cell) - Number(value));
        case 'date':
            return ordinal(text(cell).slice(0, 10), value);
        case 'boolean':
            return ordinal(isTrue(cell) ? 'true' : 'false', isTrue(value) ? 'true' : 'false');
        default:
            return ordinal(text(cell).toLowerCase(), value.toLowerCase());
    }
}

function ordinal(a: string, b: string): number {
    return a < b ? -1 : a > b ? 1 : 0;
}

function isTrue(cell: unknown): boolean {
    return cell === true || cell === 1 || cell === '1' || cell === 'true';
}

function matches<Row extends object>(row: Row, column: Column<Row>, filter: TableFilter): boolean {
    const cell = cellOf(row, column.key);

    if (isEmpty(cell)) {
        return false;
    }

    const { operator, value } = filter;

    if (operator === 'contains') {
        return text(cell).toLowerCase().includes(String(value).toLowerCase());
    }

    if (isBetweenValue(value)) {
        return compare(column, cell, value[0]) >= 0 && compare(column, cell, value[1]) <= 0;
    }

    const order = compare(column, cell, value);

    switch (operator) {
        case 'eq':
            return order === 0;
        case 'gt':
            return order > 0;
        case 'gte':
            return order >= 0;
        case 'lt':
            return order < 0;
        case 'lte':
            return order <= 0;
        default:
            return false;
    }
}
