import { Head, Link, router } from '@inertiajs/react';
import { Star } from 'lucide-react';
import type { ReactNode } from 'react';

import { ContentTypeNav, type NavType } from '@/Components/Admin/ContentTypes/ContentTypeNav';
import type { ParentOption } from '@/Components/Admin/ContentTypes/ContentTypeForm';
import { ContentTypeRowActions } from '@/Components/Admin/ContentTypes/ContentTypeRowActions';
import { sectionLabels, type TypeSection } from '@/Components/Admin/ContentTypes/section';
import { DataTable } from '@/Components/Admin/DataTable/DataTable';
import type { Column, TablePageProps } from '@/Components/Admin/DataTable/types';
import { Button } from '@/Components/ui/button';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { PageTitle } from '@/Components/Admin/PageTitle';

type ContentTypeRow = {
    key: string;
    name: string;
    slug: string | null;
    /** Key of the parent; the link of the parent column. */
    parent_type: string | null;
    /** Name of the parent; the displayed and sorted value of the parent column. */
    parent_name: string | null;
    fields_count: number;
    nodes_count: number;
    /** Number of direct child types: a column for those who manage types, also read by the delete dialog. */
    children_count: number;
    /** Service value; false when the user has no access to the nodes of the type, so there is no star. */
    can_favorite: boolean;
    /** Server-only column: searched, shown under the name. */
    description: string | null;
};

type Props = {
    table: TablePageProps<ContentTypeRow>;
    section: TypeSection;
    /** False without the `types` permission: the list then only leads to the nodes (no settings, fields, create, delete). */
    canManage: boolean;
    /** Keys of the types in the user's favorites. */
    favoriteKeys: string[];
    /** Parent choices of the clone dialog; empty without the `types` permission. */
    parentOptions: ParentOption[];
    /** Set on the "Child types" tab: the type whose direct children the table lists. */
    parent?: { key: string; name: string; slug: string | null };
    /** Section menu data of `parent` (`ContentType::navProps()`); sent together with `parent`. */
    type?: NavType;
};

const columns: Column<ContentTypeRow>[] = [
    {
        key: 'name',
        header: 'Name',
        type: 'text',
        sortable: true,
        hideable: false,
        width: '24rem',
        // `width` only bounds the cell; each line is cut to one line here.
        cell: (row) => (
            <div>
                <div className="truncate" title={row.name}>
                    {row.name}
                </div>
                {row.description && (
                    <div className="truncate text-xs text-muted-foreground" title={row.description}>
                        {row.description}
                    </div>
                )}
            </div>
        ),
    },
    { key: 'key', header: 'Key', type: 'text', sortable: true, hideable: false },
    { key: 'parent_name', header: 'Parent type', type: 'text', sortable: true, searchable: false },
    { key: 'children_count', header: 'Child types', type: 'number', sortable: true },
    { key: 'fields_count', header: 'Fields', type: 'number', sortable: true },
    { key: 'nodes_count', header: 'Nodes', type: 'number', sortable: true },
];

/** The node count links to the node list of the type in the current section. */
const linkedNodesColumn = (section: TypeSection): Column<ContentTypeRow> => ({
    key: 'nodes_count',
    header: 'Nodes',
    type: 'number',
    sortable: true,
    cell: (row) => (
        <Link href={`${section.basePath}/${row.key}/nodes`} className="text-link underline underline-offset-4">
            {row.nodes_count}
        </Link>
    ),
});

/** The parent name links to the node list of the parent; without the `types` right it is plain text. */
const parentColumn = (section: TypeSection, canManage: boolean): Column<ContentTypeRow> => ({
    key: 'parent_name',
    header: 'Parent type',
    type: 'text',
    sortable: true,
    searchable: false,
    cell: (row) =>
        row.parent_type && canManage ? (
            <Link href={`${section.basePath}/${row.parent_type}/nodes`} className="text-link underline underline-offset-4">
                {row.parent_name}
            </Link>
        ) : (
            row.parent_name
        ),
});

/** The child type count links to the "Child types" tab of the row's type. */
const linkedChildrenColumn = (section: TypeSection): Column<ContentTypeRow> => ({
    key: 'children_count',
    header: 'Child types',
    type: 'number',
    sortable: true,
    cell: (row) => (
        <Link href={`${section.basePath}/${row.key}/children`} className="text-link underline underline-offset-4">
            {row.children_count}
        </Link>
    ),
});

/**
 * The slug column exists only for kind `content`; types of other kinds have no slug. A long slug is cut to one line
 * (`width`) and the full value stays in the hint.
 */
const slugColumn: Column<ContentTypeRow> = {
    key: 'slug',
    header: 'Slug',
    type: 'text',
    sortable: true,
    width: '16rem',
    cell: (row) => <span title={row.slug ?? undefined}>{row.slug}</span>,
};

/**
 * Types list of one kind (`content`, `page` or `block`, from `section`): a server `DataTable` (state in the
 * address, no filters) with a Create button linking to the create form, a row menu (edit, fields, delete) and a star
 * that adds the type to, or removes it from, the user's favorites. The parent column shows the parent name (a link to
 * its settings for those who manage types) and the child types column links to the "Child types" tab (managers only).
 *
 * With the optional `parent` prop the same page is the "Child types" tab of that type: `ContentTypeNav` replaces the
 * list title, `tableId` is `{kind}-type-children`, and Create opens the form with `?parent_type={parent.key}`.
 */
export default function Index({ table, section, canManage, favoriteKeys, parentOptions, parent, type }: Props) {
    const { t } = useTranslation();
    const labels = sectionLabels(section.kind);
    const base = canManage ? columns : columns.filter((column) => column.key !== 'children_count');
    const withSlug = section.kind === 'content' ? [...base.slice(0, 2), slugColumn, ...base.slice(2)] : base;
    const tableColumns = withSlug.map((column) => {
        if (column.key === 'nodes_count') {
            return linkedNodesColumn(section);
        }

        if (column.key === 'parent_name') {
            return parentColumn(section, canManage);
        }

        return column.key === 'children_count' ? linkedChildrenColumn(section) : column;
    });

    const favoriteToggle = (row: ContentTypeRow) => {
        if (!row.can_favorite) {
            return null;
        }

        const isFavorite = favoriteKeys.includes(row.key);

        return (
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                aria-pressed={isFavorite}
                aria-label={t(isFavorite ? 'Remove from favorites' : 'Add to favorites')}
                data-testid="favorite-toggle"
                // Keeps the table's search, sorting and scroll: only the sidebar and the star change.
                onClick={() => router[isFavorite ? 'delete' : 'post'](`/admin/favorites/${row.key}`, {}, { preserveState: true, preserveScroll: true })}
            >
                <Star className={isFavorite ? 'fill-current text-amber-500' : ''} aria-hidden />
            </Button>
        );
    };

    return (
        <>
            <Head title={parent ? `${parent.name} — ${t('Child types')}` : t(labels.list)} />
            {parent && type ? <ContentTypeNav section={section} type={type} active="children" /> : <PageTitle>{t(labels.list)}</PageTitle>}

            <div className="mt-6">
                <DataTable
                    tableId={parent ? `${section.kind}-type-children` : `${section.kind}-types`}
                    columns={tableColumns}
                    getRowId={(row) => row.key}
                    data={table}
                    rowActions={(row) => (
                        <div className="flex items-center justify-end gap-1">
                            {favoriteToggle(row)}
                            {canManage ? (
                                <ContentTypeRowActions section={section} row={row} parentOptions={parentOptions} fromChildren={!!parent} />
                            ) : (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={`${section.basePath}/${row.key}/nodes`}>{t('Nodes')}</Link>
                                </Button>
                            )}
                        </div>
                    )}
                    toolbarActions={
                        canManage ? (
                            <Button asChild>
                                <Link href={parent ? `${section.basePath}/create?parent_type=${encodeURIComponent(parent.key)}` : `${section.basePath}/create`}>
                                    {t('Create')}
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />
            </div>
        </>
    );
}

Index.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
