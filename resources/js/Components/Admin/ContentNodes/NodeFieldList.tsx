import { closestCenter, DndContext, KeyboardSensor, PointerSensor, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core';
import { arrayMove, SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { GripVertical, Plus, X } from 'lucide-react';
import { useRef, useState } from 'react';

import { FileUpload, type FileFailure, type UploadedFile } from '@/Components/Admin/FileUpload/FileUpload';
import { Button } from '@/Components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';

import { NodeFieldInput } from './NodeFieldInput';
import { YoutubeFieldDialog } from './YoutubeFieldDialog';
import { emptyUrl, emptyYoutube, type NodeField, type NodeFieldItem, type NodeFileMetaEditor, type NodeFiles } from './types';

type Item = NodeFieldItem;

type Props = {
    field: NodeField;
    /** Key of the content type that owns the field (the file widgets upload by it). */
    typeKey: string;
    /** Known files by stored value, and the callback for a file uploaded in an item (`image` / `file` fields). */
    files?: NodeFiles;
    onFile?: (file: UploadedFile) => void;
    /** Editor of the files' names and descriptions (`image` / `file` fields). */
    fileMeta?: NodeFileMetaEditor;
    /** Base DOM id; the items get `{id}-{n}`. */
    id: string;
    value: Item[];
    onChange: (value: Item[]) => void;
    /** Server error of the item at an index (`values.{key}.{index}`), if any. */
    errorAt: (index: number) => string | undefined;
};

/**
 * Editor of a multiple field: a list of inputs (the widget follows the field's value type) to which items can be
 * added, from which they can be removed, and whose order can be changed by dragging the handle with the mouse or
 * with the keyboard (space to pick up, arrows to move, space to drop). The order of the list is the order that is
 * saved: the index becomes the value's `weight`.
 *
 * A multiple `image` or `file` field also gets a zone that takes several files at once: each is uploaded in its own
 * request and the ones that went through are appended to the end of the list as new items, so the items can then be
 * reordered or removed like any other. Such a field has no "Add item" button (an empty widget has no use) and the
 * file block of a row has no remove button of its own: the row's "Remove item" button removes the file from the list.
 * Other multiple fields keep the "Add item" button.
 *
 * A field with `max_items` above 0 is full once the list has that many items: "Add item" is disabled, the multiple
 * upload zone is not shown (a hint about the reached limit takes its place; it comes back when an item is removed), the
 * files that did not fit stay listed under the field, and a batch picked while there is still room is cut to the free places before
 * anything is uploaded (the files that did not fit are named with the limit). The server enforces the limit too.
 *
 * Values may repeat, so they cannot identify items; every item gets a client-only id that is never sent
 * to the server.
 *
 * @example
 * <NodeFieldList field={tags} typeKey="news" id="field-tags" value={['a', 'b']} onChange={setTags} errorAt={(i) => errors[`values.tags.${i}`]} />
 */
export function NodeFieldList({ field, typeKey, files, onFile, fileMeta, id, value, onChange, errorAt }: Props) {
    const { t } = useTranslation();
    const isFile = field.type === 'image' || field.type === 'file';
    const isVideo = field.type === 'youtube';
    const [adding, setAdding] = useState(false);
    // Refused files of the last batch; kept here, not in the widget, because the widget is hidden once the field is full.
    const [failures, setFailures] = useState<FileFailure[]>([]);
    const counter = useRef(0);
    const nextId = () => `${id}-${counter.current++}`;
    const [ids, setIds] = useState<string[]>(() => value.map(nextId));
    // Should the value ever outgrow the ids (a reset from outside), the missing ones are made up on the fly.
    const full = field.max_items > 0 && value.length >= field.max_items;
    const keys = value.map((_, index) => ids[index] ?? `${id}-extra-${index}`);
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 4 } }), useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }));

    const add = () => {
        onChange([...value, field.type === 'boolean' ? false : field.type === 'url' ? emptyUrl() : '']);
        setIds([...keys, nextId()]);
    };

    /** Appends the files of one finished batch in a single change, so no item is lost to a stale `value`. */
    const addFiles = (uploaded: UploadedFile[]) => {
        uploaded.forEach((file) => onFile?.(file));
        onChange([...value, ...uploaded.map((file) => file.value)]);
        setIds([...keys, ...uploaded.map(nextId)]);
    };

    const remove = (index: number) => {
        setFailures([]);
        onChange(value.filter((_, i) => i !== index));
        setIds(keys.filter((_, i) => i !== index));
    };

    const onDragEnd = ({ active, over }: DragEndEvent) => {
        const from = keys.indexOf(String(active.id));
        const to = over ? keys.indexOf(String(over.id)) : -1;

        if (from >= 0 && to >= 0 && from !== to) {
            onChange(arrayMove(value, from, to));
            setIds(arrayMove(keys, from, to));
        }
    };

    return (
        <div className="space-y-2">
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
                <SortableContext items={keys} strategy={verticalListSortingStrategy}>
                    <ul className="space-y-2">
                        {value.map((item, index) => (
                            <ListItem
                                key={keys[index]}
                                itemId={keys[index]}
                                field={field}
                                typeKey={typeKey}
                                files={files}
                                onFile={onFile}
                                fileMeta={fileMeta}
                                value={item}
                                error={errorAt(index)}
                                label={t(':name, item :number', { name: field.name, number: index + 1 })}
                                onChange={(next) => onChange(value.map((current, i) => (i === index ? next : current)))}
                                onRemove={() => remove(index)}
                            />
                        ))}
                    </ul>
                </SortableContext>
            </DndContext>
            {isFile && !full && (
                <FileUpload
                    multiple
                    field={{ contentType: typeKey, fieldKey: field.key }}
                    id={`${id}-many`}
                    aria-label={t(':name, several files', { name: field.name })}
                    accept={field.settings.mimes?.join(',')}
                    maxSize={field.settings.max_size}
                    data-testid={`file-upload-${field.key}-many`}
                    disabled={full}
                    capacity={field.max_items > 0 ? field.max_items - value.length : undefined}
                    capacityMessage={t('Limit: :max', { max: field.max_items })}
                    onUploadedMany={addFiles}
                    onFailures={setFailures}
                />
            )}
            {isFile && failures.length > 0 && (
                <ul className="text-destructive space-y-1 text-sm" role="alert" data-testid={`file-upload-${field.key}-many-failures`}>
                    {failures.map((failure, index) => (
                        <li key={`${failure.name}-${index}`}>{t(':name: :message', { name: failure.name, message: failure.message })}</li>
                    ))}
                </ul>
            )}
            {isFile && full && (
                <p className="text-sm text-muted-foreground" data-testid={`${id}-limit`}>
                    {t('The limit of :max items is reached.', { max: field.max_items })}
                </p>
            )}
            {!isFile && (
                <div className={'border-t pt-3 mt-4'}>
                    <Button type="button" variant="outline" size="sm" disabled={full} onClick={isVideo ? () => setAdding(true) : add} data-testid={isVideo ? `youtube-add-${field.key}` : undefined}>
                        <Plus aria-hidden />
                        {isVideo ? t('Add video') : t('Add item')}
                    </Button>
                </div>
            )}
            {isVideo && adding && (
                <YoutubeFieldDialog
                    field={field}
                    typeKey={typeKey}
                    id={`${id}-new`}
                    initial={emptyYoutube()}
                    files={files}
                    onFile={onFile}
                    onDone={(video) => {
                        onChange([...value, video]);
                        setIds([...keys, nextId()]);
                        setAdding(false);
                    }}
                    onClose={() => setAdding(false)}
                />
            )}
        </div>
    );
}

type ListItemProps = {
    itemId: string;
    field: NodeField;
    typeKey: string;
    files?: NodeFiles;
    onFile?: (file: UploadedFile) => void;
    fileMeta?: NodeFileMetaEditor;
    value: Item;
    error?: string;
    label: string;
    onChange: (value: Item) => void;
    onRemove: () => void;
};

function ListItem({ itemId, field, typeKey, files, onFile, fileMeta, value, error, label, onChange, onRemove }: ListItemProps) {
    const { t } = useTranslation();
    const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({ id: itemId });

    return (
        <li
            ref={setNodeRef}
            data-testid="node-list-item"
            className={isDragging ? 'relative z-10 opacity-80' : undefined}
            style={{ transform: transform ? `translate3d(${transform.x}px, ${transform.y}px, 0)` : undefined, transition }}
        >
            <div className="flex items-center gap-2">
                <button
                    type="button"
                    ref={setActivatorNodeRef}
                    aria-label={t('Drag to reorder')}
                    className="inline-flex size-6 shrink-0 cursor-grab touch-none items-center justify-center rounded-sm text-muted-foreground hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none active:cursor-grabbing"
                    {...attributes}
                    {...listeners}
                >
                    <GripVertical className="size-4" aria-hidden />
                </button>
                <div className="min-w-0 flex-1">
                    <NodeFieldInput field={field} typeKey={typeKey} files={files} onFile={onFile} fileMeta={fileMeta} removable={false} id={itemId} value={value} aria-label={label} invalid={!!error} onChange={onChange} />
                </div>
                <Button type="button" variant="ghost" size="icon-sm" aria-label={t('Remove item')} onClick={onRemove}>
                    <X aria-hidden />
                </Button>
            </div>
            {error && (
                <p role="alert" className="mt-1 ml-8 text-sm text-destructive">
                    {error}
                </p>
            )}
        </li>
    );
}
