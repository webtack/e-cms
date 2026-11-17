import { useCallback, useState } from 'react';

import type { Column } from './types';

const storageKey = (tableId: string) => `admin.table.${tableId}.columns`;

/** Hidden column keys saved for the table, or `null` when there is no usable saved choice. */
function readHidden(tableId: string): string[] | null {
    try {
        const raw = window.localStorage.getItem(storageKey(tableId));

        if (raw === null) {
            return null;
        }

        const parsed: unknown = JSON.parse(raw);

        return Array.isArray(parsed) ? parsed.filter((key): key is string => typeof key === 'string') : null;
    } catch {
        return null;
    }
}

function writeHidden(tableId: string, hidden: string[]): void {
    try {
        window.localStorage.setItem(storageKey(tableId), JSON.stringify(hidden));
    } catch {
        // Storage is blocked or full: the choice then lasts until the page is left.
    }
}

/**
 * Which columns are shown. The choice is kept per table in `localStorage`
 * (`admin.table.{tableId}.columns` → array of hidden keys, so columns added
 * later appear by default). Without a saved choice each column's
 * `defaultVisible` applies; `hideable: false` columns are always shown. Storage
 * errors are swallowed and the choice then lives in memory only.
 *
 * Visibility never touches the address or the request: hidden columns stay
 * usable for filters and sorting.
 *
 * @example
 * const { visibleColumns, isVisible, toggle } = useColumnVisibility('languages', columns);
 * toggle('code'); // hides the column, or shows it again
 */
export function useColumnVisibility<Row>(tableId: string, columns: Column<Row>[]) {
    const [hidden, setHidden] = useState<string[]>(
        () => readHidden(tableId) ?? columns.filter((c) => c.defaultVisible === false).map((c) => c.key),
    );

    const isVisible = useCallback(
        (column: Column<Row>) => column.hideable === false || !hidden.includes(column.key),
        [hidden],
    );

    const toggle = useCallback(
        (key: string) => {
            const column = columns.find((c) => c.key === key);

            if (column === undefined || column.hideable === false) {
                return;
            }

            const next = hidden.includes(key) ? hidden.filter((k) => k !== key) : [...hidden, key];

            setHidden(next);
            writeHidden(tableId, next);
        },
        [columns, hidden, tableId],
    );

    return { visibleColumns: columns.filter(isVisible), isVisible, toggle };
}
