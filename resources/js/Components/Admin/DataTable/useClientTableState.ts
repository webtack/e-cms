import { useMemo, useState } from 'react';

import { runClientEngine } from './clientEngine';
import { withFilters, withPage, withPerPage, withReset, withSearch, withSort } from './stateActions';
import { EMPTY_STATE, OPERATORS_BY_TYPE, type Column, type TableController, type TableState } from './types';

/**
 * Browser source of a table for small data sets: all `rows` are on the page
 * and `runClientEngine` does the work. The state lives in memory and never
 * touches the address, so it makes no requests and several tables can share a page.
 *
 * @example
 * const controller = useClientTableState(languages, columns);
 * controller.setSearch('en'); // no request, rows narrow immediately
 *
 * With `paginated = false` every matching row is returned on one page (the page size is unbounded).
 */
export function useClientTableState<Row extends object>(rows: Row[], columns: Column<Row>[], paginated = true): TableController<Row> {
    const [state, setState] = useState<TableState>(EMPTY_STATE);

    const effective = useMemo(() => (paginated ? state : { ...state, page: 1, perPage: Number.MAX_SAFE_INTEGER }), [paginated, state]);
    const result = useMemo(() => runClientEngine(rows, columns, effective), [rows, columns, effective]);
    const applied = { ...effective, page: result.meta.page };

    return {
        rows: result.rows,
        state: applied,
        meta: result.meta,
        ignored: [],
        operators: OPERATORS_BY_TYPE,
        loading: false,
        setSearch: (search) => setState(withSearch(applied, search)),
        setFilters: (filters) => setState(withFilters(applied, filters)),
        toggleSort: (key) => setState(withSort(applied, key)),
        setPage: (page) => setState(withPage(applied, page)),
        setPerPage: (perPage) => setState(withPerPage(applied, perPage)),
        reset: () => setState(withReset(applied)),
    };
}
