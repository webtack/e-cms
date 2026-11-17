import { router, usePage } from '@inertiajs/react';
import { useCallback, useRef, useState } from 'react';

import { toQueryString } from './params';
import { withFilters, withPage, withPerPage, withReset, withSearch, withSort } from './stateActions';
import type { TableController, TablePageProps, TableState } from './types';

/**
 * Server source of a table: the state is whatever the server applied
 * (`data.query`); every change becomes an Inertia visit to the same page with
 * new address parameters, reloading only the table prop.
 *
 * Page, sort, filters and page size use `push` (the Back button works); typing
 * in the search box uses `replace`. `loading` is true while a visit is in flight.
 * Until a visit finishes, the next change builds on the state it requested, so
 * quick repeated clicks (e.g. cycling a sort) are not lost.
 *
 * @example
 * const controller = useTableState(props.table, 'table');
 * controller.toggleSort('price'); // GET ?sort=price&dir=asc, only the `table` prop reloads
 */
export function useTableState<Row>(data: TablePageProps<Row>, prop = 'table'): TableController<Row> {
    const { url } = usePage();
    const [loading, setLoading] = useState(false);
    // The state asked for by the latest visit that has not finished yet.
    const inFlight = useRef<{ id: number; state: TableState | null }>({ id: 0, state: null });
    const state = data.query;
    const base = () => inFlight.current.state ?? state;

    const visit = useCallback(
        (next: TableState, replace = false) => {
            const id = ++inFlight.current.id;
            inFlight.current.state = next;

            const path = new URL(url, window.location.origin).pathname;
            const query = toQueryString(next);

            router.get(
                query === '' ? path : `${path}?${query}`,
                {},
                {
                    preserveState: true,
                    preserveScroll: true,
                    only: [prop],
                    replace,
                    onStart: () => setLoading(true),
                    onFinish: () => {
                        // A newer visit cancels this one: leave its bookkeeping alone.
                        if (inFlight.current.id === id) {
                            inFlight.current.state = null;
                            setLoading(false);
                        }
                    },
                },
            );
        },
        [url, prop],
    );

    return {
        rows: data.rows,
        state,
        meta: data.meta,
        ignored: data.ignored,
        operators: data.operators,
        loading,
        setSearch: (search) => visit(withSearch(base(), search), true),
        setFilters: (filters) => visit(withFilters(base(), filters)),
        toggleSort: (key) => visit(withSort(base(), key)),
        setPage: (page) => visit(withPage(base(), page)),
        setPerPage: (perPage) => visit(withPerPage(base(), perPage)),
        reset: () => visit(withReset(base())),
    };
}
