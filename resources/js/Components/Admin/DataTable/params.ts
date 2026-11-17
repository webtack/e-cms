import {
    DEFAULT_PER_PAGE,
    EMPTY_STATE,
    MAX_PER_PAGE,
    OPERATORS_BY_TYPE,
    type ColumnType,
    type FilterOperator,
    type TableFilter,
    type TableState,
} from './types';

/** Address shape accepted by Inertia's `router.get(url, data)`. */
export type TableQuery = {
    q?: string;
    filters?: { c: string; o: FilterOperator; v: string | [string, string] }[];
    sort?: string;
    dir?: 'asc' | 'desc';
    page?: number;
    per_page?: number;
};

const OPERATORS = new Set<string>(Object.values(OPERATORS_BY_TYPE).flat());

/** Whether `operator` is allowed for a column of `type` (per the fallback table). */
export function isOperatorAllowed(type: ColumnType, operator: string): operator is FilterOperator {
    return (OPERATORS_BY_TYPE[type] as string[]).includes(operator);
}

/** A filter is applied only when it has a value; `between` needs both bounds. */
export function isFilterComplete(filter: TableFilter): boolean {
    if (filter.operator === 'between') {
        return Array.isArray(filter.value) && filter.value[0] !== '' && filter.value[1] !== '';
    }

    return typeof filter.value === 'string' && filter.value !== '';
}

/**
 * Serializes the state into address parameters, omitting defaults so the
 * address stays short. Filters are renumbered from 0 and incomplete ones are
 * dropped.
 *
 * @example
 * serializeTableState({ ...EMPTY_STATE, search: 'abc', sort: 'price', dir: 'desc', page: 2 });
 * // { q: 'abc', sort: 'price', dir: 'desc', page: 2 }
 *
 * @example
 * serializeTableState({ ...EMPTY_STATE, filters: [{ column: 'price', operator: 'between', value: ['1', '9'] }] });
 * // { filters: [{ c: 'price', o: 'between', v: ['1', '9'] }] }
 */
export function serializeTableState(state: TableState): TableQuery {
    const query: TableQuery = {};

    if (state.search !== '') {
        query.q = state.search;
    }

    const filters = state.filters.filter(isFilterComplete);

    if (filters.length > 0) {
        query.filters = filters.map((f) => ({ c: f.column, o: f.operator, v: f.value }));
    }

    if (state.sort !== null && state.dir !== null) {
        query.sort = state.sort;
        query.dir = state.dir;
    }

    if (state.page > 1) {
        query.page = state.page;
    }

    if (state.perPage !== DEFAULT_PER_PAGE) {
        query.per_page = state.perPage;
    }

    return query;
}

/**
 * Query string for a state, with explicit indexes (`filters[0][c]=…`,
 * `filters[0][v][0]=…`). Built by hand because Inertia's default array format
 * (`filters[][c]`) cannot be told apart per filter by PHP.
 *
 * @example
 * toQueryString({ ...EMPTY_STATE, search: 'a b', filters: [{ column: 'price', operator: 'gte', value: '5' }] });
 * // 'q=a+b&filters%5B0%5D%5Bc%5D=price&filters%5B0%5D%5Bo%5D=gte&filters%5B0%5D%5Bv%5D=5'
 */
export function toQueryString(state: TableState): string {
    const query = serializeTableState(state);
    const params = new URLSearchParams();

    if (query.q !== undefined) {
        params.set('q', query.q);
    }

    query.filters?.forEach((f, i) => {
        params.set(`filters[${i}][c]`, f.c);
        params.set(`filters[${i}][o]`, f.o);

        if (Array.isArray(f.v)) {
            params.set(`filters[${i}][v][0]`, f.v[0]);
            params.set(`filters[${i}][v][1]`, f.v[1]);
        } else {
            params.set(`filters[${i}][v]`, f.v);
        }
    });

    if (query.sort !== undefined && query.dir !== undefined) {
        params.set('sort', query.sort);
        params.set('dir', query.dir);
    }

    if (query.page !== undefined) {
        params.set('page', String(query.page));
    }

    if (query.per_page !== undefined) {
        params.set('per_page', String(query.per_page));
    }

    return params.toString();
}

/**
 * Reads the table state from a query string. Malformed pieces are skipped
 * (the server is the authority and reports them in `ignored`). A `sort`
 * without `dir` means ascending, as on the server.
 *
 * @example
 * parseTableParams('?q=abc&sort=price&dir=desc&page=2&filters[0][c]=price&filters[0][o]=gte&filters[0][v]=100');
 * // { search: 'abc', sort: 'price', dir: 'desc', page: 2, perPage: 15,
 * //   filters: [{ column: 'price', operator: 'gte', value: '100' }] }
 *
 * @example
 * parseTableParams('?filters[0][c]=date&filters[0][o]=between&filters[0][v][0]=2024-01-01&filters[0][v][1]=2024-12-31');
 * // filters: [{ column: 'date', operator: 'between', value: ['2024-01-01', '2024-12-31'] }]
 */
export function parseTableParams(queryString: string): TableState {
    const params = new URLSearchParams(queryString);
    const state: TableState = { ...EMPTY_STATE, filters: [] };

    state.search = params.get('q') ?? '';

    const dir = params.get('dir');
    const sort = params.get('sort');

    if (sort && (dir === null || dir === 'asc' || dir === 'desc')) {
        state.sort = sort;
        state.dir = dir ?? 'asc';
    }

    state.page = toPositiveInt(params.get('page'), 1);
    state.perPage = Math.min(toPositiveInt(params.get('per_page'), DEFAULT_PER_PAGE), MAX_PER_PAGE);

    const raw = new Map<string, { c?: string; o?: string; v?: string; v0?: string; v1?: string }>();

    for (const [key, value] of params) {
        const match = key.match(/^filters\[(\d+)\]\[(c|o|v)\](?:\[([01])\])?$/);

        if (!match) {
            continue;
        }

        const entry = raw.get(match[1]) ?? {};
        const field = match[2] as 'c' | 'o' | 'v';

        if (field === 'v' && match[3] !== undefined) {
            entry[match[3] === '0' ? 'v0' : 'v1'] = value;
        } else {
            entry[field] = value;
        }

        raw.set(match[1], entry);
    }

    const indexes = [...raw.keys()].sort((a, b) => Number(a) - Number(b));

    for (const index of indexes) {
        const { c, o, v, v0, v1 } = raw.get(index)!;

        if (!c || !o || !OPERATORS.has(o)) {
            continue;
        }

        const operator = o as FilterOperator;

        if (operator === 'between') {
            state.filters.push({ column: c, operator, value: [v0 ?? '', v1 ?? ''] });
        } else if (v !== undefined) {
            state.filters.push({ column: c, operator, value: v });
        }
    }

    return state;
}

function toPositiveInt(value: string | null, fallback: number): number {
    const n = Number.parseInt(value ?? '', 10);

    return Number.isFinite(n) && n > 0 ? n : fallback;
}
