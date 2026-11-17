import {
    closestCenter,
    DndContext,
    DragOverlay,
    KeyboardSensor,
    PointerSensor,
    useDroppable,
    useSensor,
    useSensors,
    type DragEndEvent,
    type DragOverEvent,
    type DragStartEvent,
} from '@dnd-kit/core';
import { arrayMove, SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { Head, router, useForm } from '@inertiajs/react';
import { Blocks, FileStack, FileText, Folder, GripVertical, Plus, Table2, X, type LucideIcon } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';

import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { PageTitle } from '@/Components/Admin/PageTitle';
import { fetchRefOptions, RefAutocomplete } from '@/Components/Admin/ContentNodes/RefAutocomplete';
import { CreateMenuSettings, type CreateMenuType } from '@/Components/Admin/Settings/CreateMenuSettings';

type Kind = 'content' | 'page' | 'block';

/**
 * A link to a type (`type_key` set), a link to a node (`node_uuid` set) or a group (`title` set) of the admin menu,
 * as saved. For a type link `name` is the type name; for a node link it is the name of the node type and
 * `node_title` is the node title.
 */
type Entry = {
    id: number;
    type_key: string | null;
    node_uuid: string | null;
    node_title: string | null;
    name: string | null;
    kind: Kind | null;
    title: string | null;
    children: Entry[];
};

/** A group has a title; a link, to a type or to a node, has none. */
const isGroup = (entry: Entry): boolean => entry.title !== null;

/** What an entry is called in the page: the group title, the node title or the type name. */
const labelOf = (entry: Entry): string => entry.title ?? entry.node_title ?? entry.name ?? '';

type TypeOption = { key: string; name: string; kind: Kind };

type Props = {
    tree: Entry[];
    /** Types that are not in the menu yet. */
    availableTypes: TypeOption[];
    /** Every type, for choosing the type of a node to add. */
    nodeTypes: TypeOption[];
    /** The saved types of the dashboard "Create" menu, in order. */
    createTypes: CreateMenuType[];
    /** Types not yet in the "Create" menu. */
    createAvailableTypes: CreateMenuType[];
    titleMax: number;
};

const KIND_ICONS: Record<Kind, LucideIcon> = { content: Table2, page: FileStack, block: Blocks };

/** Icon of every link to a node; the same one as in the sidebar. */
const NODE_ICON: LucideIcon = FileText;

/** Value of the "Place" select that stands for the root of the menu. */
const ROOT = 'root';

/** The structure as the server stores it: root order, and the order of the links inside each group. */
const toPayload = (tree: Entry[]) => tree.map((entry) => ({ id: entry.id, children: entry.children.map((child) => child.id) }));

/**
 * Admin menu settings (`GET /admin/settings/navigation`): the tree of groups and links to types and nodes that every
 * user sees in the sidebar (access still filters it per user). A node is added by choosing its type and searching
 * the node by title. A link moves with its handle by mouse or keyboard: within
 * its list, or between the root and a group. A group moves within the root only (groups cannot be nested). The new
 * structure is saved in one request when a drag ends. Below the tree, the "Create menu" section picks and orders the
 * types of the dashboard "Create" menu ({@see CreateMenuSettings}); it is independent of the tree.
 */
export default function Navigation({ tree: savedTree, availableTypes, nodeTypes, createTypes, createAvailableTypes, titleMax }: Props) {
    const { t } = useTranslation();
    // The structure shown while dragging; it follows the server after each save (a new `tree` prop).
    const [tree, setTree] = useState(savedTree);
    const [syncedWith, setSyncedWith] = useState(savedTree);
    const [activeId, setActiveId] = useState<number | null>(null);
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 4 } }), useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }));
    const groupForm = useForm({ title: '' });
    const linkForm = useForm<{ type_key: string; parent_id: string }>({ type_key: '', parent_id: ROOT });
    const nodeForm = useForm<{ node_type: string; node_uuid: string; parent_id: string }>({ node_type: '', node_uuid: '', parent_id: ROOT });

    // Adopt the saved tree after a server round trip (adding, deleting, saving), not while a drag is in progress.
    if (syncedWith !== savedTree) {
        setSyncedWith(savedTree);
        setTree(savedTree);
    }

    const groups = tree.filter(isGroup);
    const find = (id: number): { entry: Entry; parent: Entry | null } | null => {
        for (const entry of tree) {
            if (entry.id === id) {
                return { entry, parent: null };
            }
            const child = entry.children.find((c) => c.id === id);
            if (child) {
                return { entry: child, parent: entry };
            }
        }

        return null;
    };

    const save = (next: Entry[]) =>
        router.put('/admin/settings/navigation/order', { items: toPayload(next) }, { preserveScroll: true, preserveState: true });

    /** Moves a link into another list while it is dragged over it, so the drop lands where the user sees it. */
    const onDragOver = ({ active, over }: DragOverEvent) => {
        if (!over) {
            return;
        }

        const from = find(Number(active.id));
        const overId = String(over.id);
        const overEntry = overId.startsWith('group-') ? null : find(Number(overId));
        // The list the pointer is over: a group (its row or its drop zone) or the root (a root link).
        const target = overId.startsWith('group-')
            ? Number(overId.slice(6))
            : overEntry
              ? overEntry.parent
                  ? overEntry.parent.id
                  : isGroup(overEntry.entry)
                    ? overEntry.entry.id
                    : null
              : undefined;

        if (!from || target === undefined || isGroup(from.entry) || (from.parent?.id ?? null) === target) {
            return;
        }

        // Only a link changes list; its place in the new list is settled when the drag ends.
        setTree((current) => {
            const without = current.map((entry) => ({ ...entry, children: entry.children.filter((child) => child.id !== from.entry.id) }));
            const stripped = without.filter((entry) => entry.id !== from.entry.id);

            return target === null
                ? [...stripped, from.entry].map((entry) => ({ ...entry }))
                : stripped.map((entry) => (entry.id === target ? { ...entry, children: [...entry.children, from.entry] } : entry));
        });
    };

    const onDragEnd = ({ active, over }: DragEndEvent) => {
        setActiveId(null);

        if (!over || active.id === over.id) {
            save(tree);

            return;
        }

        const from = find(Number(active.id));
        const to = String(over.id).startsWith('group-') ? null : find(Number(over.id));
        let next = tree;

        if (from && to && (from.parent?.id ?? null) === (to.parent?.id ?? null)) {
            if (from.parent === null) {
                const a = tree.findIndex((entry) => entry.id === from.entry.id);
                const b = tree.findIndex((entry) => entry.id === to.entry.id);
                next = arrayMove(tree, a, b);
            } else {
                next = tree.map((entry) => {
                    if (entry.id !== from.parent!.id) {
                        return entry;
                    }
                    const a = entry.children.findIndex((child) => child.id === from.entry.id);
                    const b = entry.children.findIndex((child) => child.id === to.entry.id);

                    return { ...entry, children: arrayMove(entry.children, a, b) };
                });
            }
        }

        setTree(next);
        save(next);
    };

    const onDragStart = ({ active }: DragStartEvent) => setActiveId(Number(active.id));

    const addGroup = (e: FormEvent) => {
        e.preventDefault();
        groupForm.post('/admin/settings/navigation/groups', { preserveScroll: true, onSuccess: () => groupForm.reset() });
    };

    const addLink = (e: FormEvent) => {
        e.preventDefault();
        linkForm.transform((data) => ({ type_key: data.type_key, parent_id: data.parent_id === ROOT ? null : Number(data.parent_id) }));
        linkForm.post('/admin/settings/navigation/links', { preserveScroll: true, onSuccess: () => linkForm.reset('type_key') });
    };

    const addNode = (e: FormEvent) => {
        e.preventDefault();
        nodeForm.transform((data) => ({ node_uuid: data.node_uuid, parent_id: data.parent_id === ROOT ? null : Number(data.parent_id) }));
        nodeForm.post('/admin/settings/navigation/links', { preserveScroll: true, onSuccess: () => nodeForm.reset('node_uuid') });
    };

    const activeEntry = activeId === null ? null : find(activeId)?.entry;

    return (
        <>
            <Head title={t('Admin menu')} />
            <PageTitle className="mb-2">{t('Admin menu')}</PageTitle>
            <p className="mb-6 max-w-3xl text-sm text-muted-foreground">
                {t('The menu every user sees in the sidebar. A user sees only the types they have access to.')}
            </p>

            <div className="mb-8 grid max-w-3xl gap-6 sm:grid-cols-2">
                <form onSubmit={addGroup} noValidate className="space-y-2" data-testid="group-form">
                    <Label htmlFor="group-title">{t('New group')}</Label>
                    <div className="flex gap-2">
                        <Input
                            id="group-title"
                            value={groupForm.data.title}
                            maxLength={titleMax}
                            placeholder={t('Group title')}
                            aria-invalid={!!groupForm.errors.title}
                            onChange={(e) => groupForm.setData('title', e.target.value)}
                        />
                        <Button type="submit" disabled={groupForm.processing} data-testid="group-add">
                            <Plus aria-hidden />
                            {t('Add')}
                        </Button>
                    </div>
                    {groupForm.errors.title && (
                        <p role="alert" className="text-sm text-destructive">
                            {groupForm.errors.title}
                        </p>
                    )}
                </form>

                <form onSubmit={addLink} noValidate className="space-y-2" data-testid="link-form">
                    <Label htmlFor="link-type">{t('New link to a type')}</Label>
                    <div className="flex flex-wrap gap-2">
                        <Select value={linkForm.data.type_key} onValueChange={(value) => linkForm.setData('type_key', value)}>
                            <SelectTrigger id="link-type" className="min-w-40 flex-1" data-testid="link-type">
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
                        <Select value={linkForm.data.parent_id} onValueChange={(value) => linkForm.setData('parent_id', value)}>
                            <SelectTrigger className="min-w-32 flex-1" aria-label={t('Place')} data-testid="link-parent">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ROOT}>{t('Top level')}</SelectItem>
                                {groups.map((group) => (
                                    <SelectItem key={group.id} value={String(group.id)}>
                                        {group.title}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button type="submit" disabled={linkForm.processing || linkForm.data.type_key === ''} data-testid="link-add">
                            <Plus aria-hidden />
                            {t('Add')}
                        </Button>
                    </div>
                    {(linkForm.errors.type_key || linkForm.errors.parent_id) && (
                        <p role="alert" className="text-sm text-destructive">
                            {linkForm.errors.type_key ?? linkForm.errors.parent_id}
                        </p>
                    )}
                </form>

                <form onSubmit={addNode} noValidate className="space-y-2 sm:col-span-2" data-testid="node-form">
                    <Label htmlFor="node-type">{t('New link to a node')}</Label>
                    <div className="flex flex-wrap gap-2">
                        <Select
                            value={nodeForm.data.node_type}
                            onValueChange={(value) => {
                                nodeForm.setData((data) => ({ ...data, node_type: value, node_uuid: '' }));
                            }}
                        >
                            <SelectTrigger id="node-type" className="min-w-40 flex-1" aria-label={t('Node type')} data-testid="node-type">
                                <SelectValue placeholder={t('Select a type')} />
                            </SelectTrigger>
                            <SelectContent>
                                {nodeTypes.map((type) => (
                                    <SelectItem key={type.key} value={type.key}>
                                        {type.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <div className="min-w-40 flex-1" data-testid="node-pick">
                            {nodeForm.data.node_type === '' ? (
                                <Button type="button" variant="outline" disabled className="w-full justify-start font-normal text-muted-foreground">
                                    {t('Select a node')}
                                </Button>
                            ) : (
                                <RefAutocomplete
                                    // A new type resets the search and the chosen node.
                                    key={nodeForm.data.node_type}
                                    id="node-uuid"
                                    value={nodeForm.data.node_uuid}
                                    onChange={(value) => nodeForm.setData('node_uuid', value)}
                                    placeholder={t('Select a node')}
                                    clearable={false}
                                    invalid={!!nodeForm.errors.node_uuid}
                                    search={(query, signal) =>
                                        fetchRefOptions<{ uuid: string; title: string }>(
                                            `/admin/lookup/nodes?content_type=${encodeURIComponent(nodeForm.data.node_type)}&q=${encodeURIComponent(query)}`,
                                            signal,
                                            (row) => ({ value: row.uuid, label: row.title }),
                                        )
                                    }
                                />
                            )}
                        </div>
                        <Select value={nodeForm.data.parent_id} onValueChange={(value) => nodeForm.setData('parent_id', value)}>
                            <SelectTrigger className="min-w-32 flex-1" aria-label={t('Place')} data-testid="node-parent">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ROOT}>{t('Top level')}</SelectItem>
                                {groups.map((group) => (
                                    <SelectItem key={group.id} value={String(group.id)}>
                                        {group.title}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button type="submit" disabled={nodeForm.processing || nodeForm.data.node_uuid === ''} data-testid="node-add">
                            <Plus aria-hidden />
                            {t('Add node')}
                        </Button>
                    </div>
                    {(nodeForm.errors.node_uuid || nodeForm.errors.parent_id) && (
                        <p role="alert" className="text-sm text-destructive">
                            {nodeForm.errors.node_uuid ?? nodeForm.errors.parent_id}
                        </p>
                    )}
                </form>
            </div>

            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragStart={onDragStart} onDragOver={onDragOver} onDragEnd={onDragEnd}>
                <SortableContext items={tree.map((entry) => entry.id)} strategy={verticalListSortingStrategy}>
                    <ul className="max-w-3xl space-y-2" data-testid="menu-tree">
                        {tree.map((entry) => (
                            <SortableEntry key={entry.id} entry={entry} testId={isGroup(entry) ? 'menu-group' : 'menu-link'}>
                                {isGroup(entry) ? <GroupBody group={entry} titleMax={titleMax} /> : <LinkBody entry={entry} />}
                            </SortableEntry>
                        ))}
                        {tree.length === 0 && <li className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">{t('The menu is empty.')}</li>}
                    </ul>
                </SortableContext>
                <DragOverlay>{activeEntry ? <div className="rounded-md border bg-background px-3 py-2 text-sm shadow">{labelOf(activeEntry)}</div> : null}</DragOverlay>
            </DndContext>

            <CreateMenuSettings types={createTypes} availableTypes={createAvailableTypes} />
        </>
    );
}

/** A sortable row with a drag handle; `children` is the row's content. */
function SortableEntry({ entry, testId, children }: { entry: Entry; testId: string; children: ReactNode }) {
    const { t } = useTranslation();
    const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({ id: entry.id });
    const label = labelOf(entry);

    return (
        <li
            ref={setNodeRef}
            data-testid={testId}
            data-entry-id={entry.id}
            className={`rounded-md border bg-card ${isDragging ? 'relative z-10 opacity-60' : ''}`}
            style={{ transform: transform ? `translate3d(${transform.x}px, ${transform.y}px, 0)` : undefined, transition }}
        >
            <div className="flex items-start gap-2 p-2">
                <button
                    type="button"
                    ref={setActivatorNodeRef}
                    aria-label={`${t('Drag to reorder')}: ${label}`}
                    data-testid="menu-handle"
                    className="mt-1 inline-flex size-6 shrink-0 cursor-grab touch-none items-center justify-center rounded-sm text-muted-foreground hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none active:cursor-grabbing"
                    {...attributes}
                    {...listeners}
                >
                    <GripVertical className="size-4" aria-hidden />
                </button>
                <div className="min-w-0 flex-1">{children}</div>
            </div>
        </li>
    );
}

function DeleteButton({ entry, label }: { entry: Entry; label: string }) {
    const { t } = useTranslation();

    return (
        <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            aria-label={`${t('Remove item')}: ${label}`}
            data-testid="menu-remove"
            onClick={() => router.delete(`/admin/settings/navigation/entries/${entry.id}`, { preserveScroll: true, preserveState: true })}
        >
            <X aria-hidden />
        </Button>
    );
}

/** A link row: a type link shows the type name; a node link shows the node icon, its title and the type name. */
function LinkBody({ entry }: { entry: Entry }) {
    const isNode = entry.node_uuid !== null;
    const Icon = isNode ? NODE_ICON : KIND_ICONS[entry.kind ?? 'content'];

    return (
        <div className="flex min-h-8 items-center gap-2" data-node={isNode || undefined}>
            <Icon className="size-4 shrink-0 text-muted-foreground" aria-hidden />
            <span className="flex-1 truncate" data-testid="menu-link-name">
                {labelOf(entry)}
            </span>
            {isNode && (
                <span className="shrink-0 text-xs text-muted-foreground" data-testid="menu-link-type">
                    {entry.name}
                </span>
            )}
            <DeleteButton entry={entry} label={labelOf(entry)} />
        </div>
    );
}

/** A group: editable title, delete button and the sortable list of its links (also a drop zone when empty). */
function GroupBody({ group, titleMax }: { group: Entry; titleMax: number }) {
    const { t } = useTranslation();
    const form = useForm({ title: group.title ?? '' });
    const { setNodeRef } = useDroppable({ id: `group-${group.id}` });
    const dirty = form.data.title !== (group.title ?? '');

    const rename = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/admin/settings/navigation/groups/${group.id}`, { preserveScroll: true, preserveState: true });
    };

    return (
        <div className="space-y-2">
            <form onSubmit={rename} noValidate className="flex items-center gap-2">
                <Folder className="size-4 shrink-0 text-muted-foreground" aria-hidden />
                <Input
                    value={form.data.title}
                    maxLength={titleMax}
                    aria-label={t('Group title')}
                    aria-invalid={!!form.errors.title}
                    data-testid="group-title"
                    onChange={(e) => form.setData('title', e.target.value)}
                />
                <Button type="submit" variant="outline" size="sm" disabled={!dirty || form.processing} data-testid="group-save">
                    {t('Save')}
                </Button>
                <DeleteButton entry={group} label={group.title ?? ''} />
            </form>
            {form.errors.title && (
                <p role="alert" className="text-sm text-destructive">
                    {form.errors.title}
                </p>
            )}
            <SortableContext items={group.children.map((child) => child.id)} strategy={verticalListSortingStrategy}>
                <ul ref={setNodeRef} className="min-h-10 space-y-2 border-l pl-3" data-testid="group-children">
                    {group.children.map((child) => (
                        <SortableEntry key={child.id} entry={child} testId="menu-link">
                            <LinkBody entry={child} />
                        </SortableEntry>
                    ))}
                    {group.children.length === 0 && <li className="py-2 text-sm text-muted-foreground">{t('Drop a link here.')}</li>}
                </ul>
            </SortableContext>
        </div>
    );
}

Navigation.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
