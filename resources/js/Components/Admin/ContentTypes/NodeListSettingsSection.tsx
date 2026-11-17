import { closestCenter, DndContext, KeyboardSensor, PointerSensor, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core';
import { arrayMove, SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { GripVertical } from 'lucide-react';

import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';
import { FieldGroup } from '@/Components/Admin/FieldGroup';
import { FormSection } from '@/Components/Admin/FormSection';

/** A choosable column, search field or sort option. A service name is an English translation key; a field's name is data. */
export type ListOption = { key: string; name: string; service: boolean };

/** What the server offers (`NodeListSettings::formOptions()`), minus the current state. */
export type NodeListOptions = { columns: ListOption[]; searchFields: ListOption[]; sortOptions: ListOption[]; current: NodeListValue };

/** The state the section edits and the form submits as `settings.list`. */
export type NodeListValue = {
    /** Selected column keys in display order; `title` is always among them, at its own position. */
    visible: string[];
    /** Keys of the text fields searched. */
    search: string[];
    sort: { field: string; direction: 'asc' | 'desc' };
};

type Props = {
    options: NodeListOptions;
    value: NodeListValue;
    onChange: (value: NodeListValue) => void;
    /** Server errors of `settings*`, shown next to the section. */
    errors?: string[];
};

/**
 * The "Node list" section of a type's settings form: which columns the list of nodes shows and in what order
 * (check boxes; the selected ones are reordered by dragging their handle, or from the keyboard: Space to lift,
 * arrows to move), which text fields the search covers and the default sort (field and direction). `title` is
 * always shown: its check box is checked and disabled, only its position changes. It is controlled: the parent
 * form owns the value and submits it as `settings.list`.
 *
 * @example
 * <NodeListSettingsSection options={listOptions} value={data.settings.list} onChange={(list) => setData('settings', { list })} />
 */
export function NodeListSettingsSection({ options, value, onChange, errors = [] }: Props) {
    const { t } = useTranslation();
    const label = (option: ListOption) => (option.service ? t(option.name) : option.name);
    const byKey = new Map(options.columns.map((option) => [option.key, option]));
    // The selected columns come first in their saved order, then the unselected ones.
    const selected = value.visible.map((key) => byKey.get(key)).filter((option): option is ListOption => option !== undefined);
    const selectedKeys = selected.map((option) => option.key);
    const unselected = options.columns.filter((option) => !value.visible.includes(option.key));

    const toggleColumn = (key: string, checked: boolean) =>
        onChange({ ...value, visible: checked ? [...value.visible, key] : value.visible.filter((item) => item !== key) });

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const onDragEnd = ({ active, over }: DragEndEvent) => {
        const from = value.visible.indexOf(String(active.id));
        const to = over ? value.visible.indexOf(String(over.id)) : -1;

        if (from >= 0 && to >= 0 && from !== to) {
            onChange({ ...value, visible: arrayMove(value.visible, from, to) });
        }
    };

    const toggleSearch = (key: string, checked: boolean) =>
        onChange({ ...value, search: checked ? [...value.search, key] : value.search.filter((item) => item !== key) });

    return (
        <FormSection as="section" aria-labelledby="node-list-heading" data-testid="node-list-settings" className="mb-6 max-w-xl space-y-6">
            <h2 id="node-list-heading" className="text-lg font-semibold">
                {t('Node list')}
            </h2>

            <FieldGroup legend={t('Columns')}>
                <p className="text-sm text-muted-foreground">{t('The title is always shown; drag a column to change its position.')}</p>
                <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
                    <SortableContext items={selectedKeys} strategy={verticalListSortingStrategy}>
                        <ul className="space-y-1">
                            {selected.map((option) => (
                                <SelectedColumn
                                    key={option.key}
                                    option={option}
                                    name={label(option)}
                                    locked={option.key === 'title'}
                                    onUncheck={() => toggleColumn(option.key, false)}
                                />
                            ))}
                        </ul>
                    </SortableContext>
                </DndContext>
                <ul className="space-y-1">
                    {unselected.map((option) => (
                        <li key={option.key} className="flex items-center gap-2" data-testid={`list-column-${option.key}`}>
                            <Checkbox id={`column-${option.key}`} checked={false} onCheckedChange={(checked) => toggleColumn(option.key, checked === true)} />
                            <Label htmlFor={`column-${option.key}`} className="flex-1 cursor-pointer text-muted-foreground">
                                {label(option)}
                            </Label>
                        </li>
                    ))}
                </ul>
            </FieldGroup>

            <FieldGroup legend={t('Search fields')}>
                <ul className="space-y-1">
                    {options.searchFields.map((option) => (
                        <li key={option.key} className="flex items-center gap-2">
                            <Checkbox
                                id={`search-${option.key}`}
                                checked={value.search.includes(option.key)}
                                onCheckedChange={(checked) => toggleSearch(option.key, checked === true)}
                            />
                            <Label htmlFor={`search-${option.key}`} className="cursor-pointer">
                                {label(option)}
                            </Label>
                        </li>
                    ))}
                </ul>
            </FieldGroup>

            <FieldGroup legend={t('Default sort')}>
                <div className="flex gap-2">
                    <div className="flex-1 space-y-1">
                        <Label htmlFor="list-sort-field">{t('Field')}</Label>
                        <Select value={value.sort.field} onValueChange={(field) => onChange({ ...value, sort: { ...value.sort, field } })}>
                            <SelectTrigger id="list-sort-field" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {options.sortOptions.map((option) => (
                                    <SelectItem key={option.key} value={option.key}>
                                        {label(option)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="w-44 space-y-1">
                        <Label htmlFor="list-sort-direction">{t('Direction')}</Label>
                        <Select
                            value={value.sort.direction}
                            onValueChange={(direction) => onChange({ ...value, sort: { ...value.sort, direction: direction as 'asc' | 'desc' } })}
                        >
                            <SelectTrigger id="list-sort-direction" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="asc">{t('Ascending')}</SelectItem>
                                <SelectItem value="desc">{t('Descending')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </FieldGroup>

            {errors.map((message) => (
                <p key={message} role="alert" className="text-sm text-destructive">
                    {message}
                </p>
            ))}
        </FormSection>
    );
}

type SelectedColumnProps = {
    option: ListOption;
    /** The label shown (translated for a service column). */
    name: string;
    /** `title`: always shown, so its check box is checked and disabled. */
    locked: boolean;
    onUncheck: () => void;
};

/** A selected column row: a drag handle (named after the column), the check box and the label. */
function SelectedColumn({ option, name, locked, onUncheck }: SelectedColumnProps) {
    const { t } = useTranslation();
    const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({ id: option.key });

    return (
        <li
            ref={setNodeRef}
            data-testid={`list-column-${option.key}`}
            className={`flex items-center gap-2${isDragging ? ' relative z-10 opacity-80' : ''}`}
            style={{ transform: transform ? `translate3d(${transform.x}px, ${transform.y}px, 0)` : undefined, transition }}
        >
            <button
                type="button"
                ref={setActivatorNodeRef}
                aria-label={t('Reorder column :name', { name })}
                className="inline-flex size-6 shrink-0 cursor-grab touch-none items-center justify-center rounded-sm text-muted-foreground hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none active:cursor-grabbing"
                {...attributes}
                {...listeners}
            >
                <GripVertical className="size-4" aria-hidden />
            </button>
            <Checkbox id={`column-${option.key}`} checked disabled={locked} onCheckedChange={onUncheck} />
            <Label htmlFor={`column-${option.key}`} className="flex-1 cursor-pointer">
                {name}
            </Label>
        </li>
    );
}
