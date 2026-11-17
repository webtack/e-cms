import { closestCenter, DndContext, KeyboardSensor, PointerSensor, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core';
import { arrayMove, SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { router } from '@inertiajs/react';
import { GripVertical, Plus, X } from 'lucide-react';
import { useState, type FormEvent } from 'react';

import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';

export type CreateMenuType = { key: string; name: string; kind: 'content' | 'page' | 'block' };

/**
 * Saves the whole ordered list; an empty list clears the setting on the server (the default dashboard menu applies).
 */
const save = (types: CreateMenuType[]) =>
    router.put('/admin/settings/navigation/create-types', { types: types.map((type) => type.key) }, { preserveScroll: true, preserveState: true });

/**
 * The "Create menu" section of the menu settings page: the types shown in the dashboard "Create" menu and their order.
 * A type is added with the select, removed with its button and moved with its handle (mouse or keyboard); every
 * change sends the full list in one `PUT /admin/settings/navigation/create-types`. It has its own `DndContext`, so it
 * does not interact with the menu tree.
 *
 * @example
 * <CreateMenuSettings types={[{ key: 'page', name: 'Page', kind: 'page' }]} availableTypes={[{ key: 'news', name: 'News', kind: 'content' }]} />
 */
export function CreateMenuSettings({ types: savedTypes, availableTypes }: { types: CreateMenuType[]; availableTypes: CreateMenuType[] }) {
    const { t } = useTranslation();
    // Shown order while dragging; follows the server after each save (a new `types` prop).
    const [types, setTypes] = useState(savedTypes);
    const [syncedWith, setSyncedWith] = useState(savedTypes);
    const [picked, setPicked] = useState('');
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 4 } }), useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }));

    if (syncedWith !== savedTypes) {
        setSyncedWith(savedTypes);
        setTypes(savedTypes);
    }

    /** Shows the new list at once and saves it, so a second change before the response builds on the first. */
    const change = (next: CreateMenuType[]) => {
        setTypes(next);
        save(next);
    };

    const add = (e: FormEvent) => {
        e.preventDefault();
        const type = availableTypes.find((candidate) => candidate.key === picked);

        if (type) {
            setPicked('');
            change([...types, type]);
        }
    };

    const onDragEnd = ({ active, over }: DragEndEvent) => {
        if (!over || active.id === over.id) {
            return;
        }

        const next = arrayMove(
            types,
            types.findIndex((type) => type.key === active.id),
            types.findIndex((type) => type.key === over.id),
        );

        change(next);
    };

    return (
        <section className="mt-10 max-w-3xl space-y-3" data-testid="create-menu">
            <h2 className="text-lg font-semibold">{t('Create menu')}</h2>
            <p className="text-sm text-muted-foreground">
                {t('The types in the "Create" menu of the dashboard, in this order. When the list is empty, the menu shows every type the user may create, grouped by kind.')}
            </p>

            <form onSubmit={add} noValidate className="space-y-2" data-testid="create-menu-form">
                <Label htmlFor="create-menu-type">{t('Add a type to the Create menu')}</Label>
                <div className="flex gap-2">
                    <Select value={picked} onValueChange={setPicked}>
                        <SelectTrigger id="create-menu-type" className="min-w-40 flex-1" data-testid="create-menu-type">
                            <SelectValue placeholder={t('Select a type')} />
                        </SelectTrigger>
                        <SelectContent>
                            {availableTypes.map((type) => (
                                <SelectItem key={type.key} value={type.key}>
                                    {type.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button type="submit" disabled={picked === ''} data-testid="create-menu-add">
                        <Plus aria-hidden />
                        {t('Add')}
                    </Button>
                </div>
            </form>

            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
                <SortableContext items={types.map((type) => type.key)} strategy={verticalListSortingStrategy}>
                    <ul className="space-y-2" data-testid="create-menu-list">
                        {types.map((type) => (
                            <CreateMenuRow key={type.key} type={type} onRemove={() => change(types.filter((candidate) => candidate.key !== type.key))} />
                        ))}
                        {types.length === 0 && <li className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">{t('The Create menu is not configured.')}</li>}
                    </ul>
                </SortableContext>
            </DndContext>
        </section>
    );
}

/** A sortable row of the list: handle, type name and remove button. */
function CreateMenuRow({ type, onRemove }: { type: CreateMenuType; onRemove: () => void }) {
    const { t } = useTranslation();
    const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({ id: type.key });

    return (
        <li
            ref={setNodeRef}
            data-testid="create-menu-item"
            data-type-key={type.key}
            className={`flex items-center gap-2 rounded-md border bg-card p-2 ${isDragging ? 'relative z-10 opacity-60' : ''}`}
            style={{ transform: transform ? `translate3d(${transform.x}px, ${transform.y}px, 0)` : undefined, transition }}
        >
            <button
                type="button"
                ref={setActivatorNodeRef}
                aria-label={`${t('Drag to reorder')}: ${type.name}`}
                data-testid="create-menu-handle"
                className="inline-flex size-6 shrink-0 cursor-grab touch-none items-center justify-center rounded-sm text-muted-foreground hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none active:cursor-grabbing"
                {...attributes}
                {...listeners}
            >
                <GripVertical className="size-4" aria-hidden />
            </button>
            <span className="flex-1 truncate" data-testid="create-menu-name">
                {type.name}
            </span>
            <Button type="button" variant="ghost" size="icon-sm" aria-label={`${t('Remove item')}: ${type.name}`} data-testid="create-menu-remove" onClick={onRemove}>
                <X aria-hidden />
            </Button>
        </li>
    );
}
