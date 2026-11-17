import type { TableFilter, TableState } from './types';

/**
 * Pure state transitions shared by the server and client sources, so both
 * behave identically: any change of search, filters, sort or page size goes
 * back to the first page.
 */

export function withSearch(state: TableState, search: string): TableState {
    return { ...state, search, page: 1 };
}

export function withFilters(state: TableState, filters: TableFilter[]): TableState {
    return { ...state, filters, page: 1 };
}

/**
 * Sort cycle of one column: ascending → descending → none. Choosing another
 * column starts it ascending (only one column is sorted at a time).
 *
 * @example
 * withSort(EMPTY_STATE, 'price');                           // sort 'price' asc
 * withSort({ ...EMPTY_STATE, sort: 'price', dir: 'asc' }, 'price');  // sort 'price' desc
 * withSort({ ...EMPTY_STATE, sort: 'price', dir: 'desc' }, 'price'); // no sort
 */
export function withSort(state: TableState, key: string): TableState {
    if (state.sort !== key) {
        return { ...state, sort: key, dir: 'asc', page: 1 };
    }

    if (state.dir === 'asc') {
        return { ...state, dir: 'desc', page: 1 };
    }

    return { ...state, sort: null, dir: null, page: 1 };
}

export function withPage(state: TableState, page: number): TableState {
    return { ...state, page: Math.max(1, page) };
}

export function withPerPage(state: TableState, perPage: number): TableState {
    return { ...state, perPage, page: 1 };
}

/** Clears search, filters and sort; the page size is a display preference and stays. */
export function withReset(state: TableState): TableState {
    return { ...state, search: '', filters: [], sort: null, dir: null, page: 1 };
}

/** Whether search, filters or sort narrow or reorder the data. */
export function isNarrowed(state: TableState): boolean {
    return state.search !== '' || state.filters.length > 0 || state.sort !== null;
}

/** The value of the `eq` filter on `column`, or `null` when the column is not filtered that way. */
export function filterValue(filters: TableFilter[], column: string): string | null {
    const filter = filters.find((f) => f.column === column && f.operator === 'eq');

    return filter && typeof filter.value === 'string' ? filter.value : null;
}

/**
 * Filters with the `eq` filter on `column` set to `value`, or removed when `value` is `null`. Other filters stay,
 * and so does the position of an existing filter on the column.
 *
 * @example
 * withColumnFilter([], 'mime', 'pdf');                                          // [{ column: 'mime', operator: 'eq', value: 'pdf' }]
 * withColumnFilter([{ column: 'mime', operator: 'eq', value: 'pdf' }], 'mime', null); // []
 */
export function withColumnFilter(filters: TableFilter[], column: string, value: string | null): TableFilter[] {
    const rest = filters.filter((f) => f.column !== column);

    return value === null ? rest : [...rest, { column, operator: 'eq', value }];
}
