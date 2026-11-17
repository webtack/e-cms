import { closestCenter, DndContext, KeyboardSensor, PointerSensor, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core';
import { arrayMove, SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { GripVertical, Plus, X } from 'lucide-react';
import { useRef, useState, type ReactNode } from 'react';

import { Button } from '@/Components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';

type Props<T> = {
    /** The rows of the list; the order of the array is the order that is saved. */
    items: T[];
    onChange: (items: T[]) => void;
    /** A new empty row, appended by the "Add" button. */
    createItem: () => T;
    /** Draws the content of one row; `update` replaces the row, `index` is its current position. */
    renderItem: (item: T, index: number, update: (next: T) => void) => ReactNode;
    /** Text of the "Add" button. */
    addLabel: string;
    /** Accessible name of one row for its "Remove" button, e.g. `Phone 2`. */
    rowLabel: (index: number) => string;
    /** Most rows the list may hold; "Add" is disabled once reached (the server enforces it too). */
    max?: number;
    /** Prefix of the `data-testid` values: `{testId}` (list), `{testId}-item`, `{testId}-handle`, `{testId}-remove`, `{testId}-add`. */
    testId: string;
    /** Fixed rows (the translation mode): no handle, no "Remove" and no "Add" button; only the row content is editable. */
    fixed?: boolean;
};

/**
 * Editable list with add, remove and reorder. A row moves by dragging its handle with the mouse or with the
 * keyboard (space to pick up, arrows to move, space to drop). With `fixed` the rows cannot be added, removed or moved.
 *
 * Rows may repeat, so they cannot identify themselves; every row gets a client-only id (never sent to the
 * server) to keep React keys and the drag state stable while rows are reordered.
 *
 * @example
 * <SortableList
 *     items={data.phones}
 *     onChange={(phones) => setData('phones', phones)}
 *     createItem={() => ({ value: '', label: '' })}
 *     addLabel={t('Add phone')}
 *     rowLabel={(i) => t('Phone :number', { number: i + 1 })}
 *     testId="phones"
 *     renderItem={(row, i, update) => <Input value={row.value} onChange={(e) => update({ ...row, value: e.target.value })} />}
 * />
 */
export function SortableList<T>({ items, onChange, createItem, renderItem, addLabel, rowLabel, max, testId, fixed = false }: Props<T>) {
    const { t } = useTranslation();
    const counter = useRef(0);
    const nextId = () => `${testId}-row-${counter.current++}`;
    const [ids, setIds] = useState<string[]>(() => items.map(nextId));
    // Should the items outgrow the ids (a reset from outside), the missing ids are made up on the fly.
    const keys = items.map((_, index) => ids[index] ?? `${testId}-extra-${index}`);
    const full = max !== undefined && items.length >= max;
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 4 } }), useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }));

    const add = () => {
        onChange([...items, createItem()]);
        setIds([...keys, nextId()]);
    };

    const remove = (index: number) => {
        onChange(items.filter((_, i) => i !== index));
        setIds(keys.filter((_, i) => i !== index));
    };

    const onDragEnd = ({ active, over }: DragEndEvent) => {
        const from = keys.indexOf(String(active.id));
        const to = over ? keys.indexOf(String(over.id)) : -1;

        if (from >= 0 && to >= 0 && from !== to) {
            onChange(arrayMove(items, from, to));
            setIds(arrayMove(keys, from, to));
        }
    };

    return (
        <div className="space-y-3">
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
                <SortableContext items={keys} strategy={verticalListSortingStrategy}>
                    <ul className="space-y-3" data-testid={testId}>
                        {items.map((item, index) => (
                            <Row key={keys[index]} rowId={keys[index]} testId={testId} label={rowLabel(index)} fixed={fixed} onRemove={() => remove(index)}>
                                {renderItem(item, index, (next) => onChange(items.map((current, i) => (i === index ? next : current))))}
                            </Row>
                        ))}
                    </ul>
                </SortableContext>
            </DndContext>
            {!fixed && (
                <Button type="button" variant="outline" size="sm" disabled={full} onClick={add} data-testid={`${testId}-add`}>
                    <Plus aria-hidden />
                    {addLabel}
                </Button>
            )}
            {full && !fixed && <p className="text-sm text-muted-foreground">{t('The limit of :max items is reached.', { max })}</p>}
        </div>
    );
}

type RowProps = {
    rowId: string;
    testId: string;
    label: string;
    fixed: boolean;
    onRemove: () => void;
    children: ReactNode;
};

function Row({ rowId, testId, label, fixed, onRemove, children }: RowProps) {
    const { t } = useTranslation();
    const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({ id: rowId });

    return (
        <li
            ref={setNodeRef}
            data-testid={`${testId}-item`}
            className={isDragging ? 'relative z-10 opacity-80' : undefined}
            style={{ transform: transform ? `translate3d(${transform.x}px, ${transform.y}px, 0)` : undefined, transition }}
        >
            <div className="flex items-start gap-2">
                {!fixed && (
                <button
                    type="button"
                    ref={setActivatorNodeRef}
                    aria-label={`${t('Drag to reorder')}: ${label}`}
                    data-testid={`${testId}-handle`}
                    className="mt-1.5 inline-flex size-6 shrink-0 cursor-grab touch-none items-center justify-center rounded-sm text-muted-foreground hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none active:cursor-grabbing"
                    {...attributes}
                    {...listeners}
                >
                    <GripVertical className="size-4" aria-hidden />
                </button>
                )}
                <div className="min-w-0 flex-1">{children}</div>
                {!fixed && (
                <Button type="button" variant="ghost" size="icon-sm" aria-label={`${t('Remove item')}: ${label}`} data-testid={`${testId}-remove`} onClick={onRemove}>
                    <X aria-hidden />
                </Button>
                )}
            </div>
        </li>
    );
}
