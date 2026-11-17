import { useSortable } from '@dnd-kit/sortable';
import { GripVertical } from 'lucide-react';
import type { CSSProperties, ReactNode } from 'react';

import { BooleanBadge } from '@/Components/Admin/BooleanBadge';
import { TableCell, TableRow } from '@/Components/ui/table';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

import type { Column } from './types';

/** Reorder mode of a row: its id in the sorting context and whether dragging is currently off. */
export type SortableRowConfig = {
    id: string;
    disabled: boolean;
    /** Tooltip of the disabled handle: how to make dragging available. */
    disabledHint: string;
};

type Props<Row> = {
    row: Row;
    columns: Column<Row>[];
    /** Optional leading cell (custom content; the drag handle comes before it). */
    rowHandle?: (row: Row) => ReactNode;
    /** Optional trailing actions cell (e.g. a row menu). */
    rowActions?: (row: Row) => ReactNode;
    /** Makes the row draggable by a handle in the leading cell (see `reorder` of `DataTable`). */
    sortable?: SortableRowConfig;
};

/**
 * One table row.
 *
 * Default cell rendering: booleans as Yes/No, `select` values as their option
 * label, empty values as nothing; a column's `cell` renderer overrides it.
 * With `sortable` the row becomes a draggable item of the surrounding sorting context and
 * the leading cell holds the handle (mouse and keyboard); a disabled handle shows `disabledHint`.
 *
 * @example
 * <DataTableRow row={row} columns={visibleColumns} />
 * <DataTableRow row={row} columns={visibleColumns} sortable={{ id: 'a', disabled: false, disabledHint: '' }} />
 */
export function DataTableRow<Row extends object>({ sortable, ...props }: Props<Row>) {
    return sortable ? <SortableRow {...props} sortable={sortable} /> : <RowView {...props} />;
}

type ViewProps<Row> = Omit<Props<Row>, 'sortable'> & {
    /** The drag handle, when the row is sortable. */
    handle?: ReactNode;
    rowRef?: (node: HTMLElement | null) => void;
    style?: CSSProperties;
    dragging?: boolean;
};

function SortableRow<Row extends object>({ sortable, ...props }: Omit<Props<Row>, 'sortable'> & { sortable: SortableRowConfig }) {
    const { t } = useTranslation();
    const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({
        id: sortable.id,
        disabled: sortable.disabled,
    });

    const handle = sortable.disabled ? (
        <button
            type="button"
            disabled
            title={sortable.disabledHint}
            aria-label={t('Drag to reorder')}
            className="inline-flex size-6 cursor-not-allowed items-center justify-center rounded-sm text-muted-foreground opacity-40"
        >
            <GripVertical className="size-4" aria-hidden />
        </button>
    ) : (
        <button
            type="button"
            ref={setActivatorNodeRef}
            aria-label={t('Drag to reorder')}
            className="inline-flex size-6 cursor-grab touch-none items-center justify-center rounded-sm text-muted-foreground hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none active:cursor-grabbing"
            {...attributes}
            {...listeners}
        >
            <GripVertical className="size-4" aria-hidden />
        </button>
    );

    return (
        <RowView
            {...props}
            handle={handle}
            rowRef={setNodeRef}
            dragging={isDragging}
            style={{ transform: transform ? `translate3d(${transform.x}px, ${transform.y}px, 0)` : undefined, transition }}
        />
    );
}

function RowView<Row extends object>({ row, columns, rowHandle, rowActions, handle, rowRef, style, dragging }: ViewProps<Row>) {
    const { t } = useTranslation();

    const render = (column: Column<Row>): ReactNode => {
        if (column.cell) {
            return column.cell(row);
        }

        const value = (row as Record<string, unknown>)[column.key];

        if (value === null || value === undefined || value === '') {
            return null;
        }

        if (typeof value === 'boolean') {
            return <BooleanBadge value={value} tone={column.booleanTone} />;
        }

        const option = column.options?.find((o) => o.value === String(value));

        return option ? t(option.label) : String(value);
    };

    return (
        <TableRow ref={rowRef} style={style} className={cn(dragging && 'relative z-10 bg-muted shadow-md hover:bg-muted')}>
            {(rowHandle || handle) && (
                <TableCell className="w-8">
                    <div className="flex items-center gap-1">
                        {handle}
                        {rowHandle?.(row)}
                    </div>
                </TableCell>
            )}
            {columns.map((column) => (
                <TableCell key={column.key}>
                    {column.width ? (
                        // The wrapper, not the <td>, carries the limit: max-width on table cells is unreliable.
                        <div className="truncate" style={{ maxWidth: column.width }}>
                            {render(column)}
                        </div>
                    ) : (
                        render(column)
                    )}
                </TableCell>
            ))}
            {rowActions && <TableCell className="w-10 text-right">{rowActions(row)}</TableCell>}
        </TableRow>
    );
}
