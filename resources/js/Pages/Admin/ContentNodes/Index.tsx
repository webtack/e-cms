import { Head, Link, router } from '@inertiajs/react';
import { Star } from 'lucide-react';
import type { ReactNode } from 'react';

import { BooleanBadge } from '@/Components/Admin/BooleanBadge';
import { ContentNodeRowActions } from '@/Components/Admin/ContentNodes/ContentNodeRowActions';
import { NodeStatusBadge } from '@/Components/Admin/ContentNodes/NodeServicePanel';
import type { NodeStatus } from '@/Components/Admin/ContentNodes/status';
import { ContentTypeNav, type NavType } from '@/Components/Admin/ContentTypes/ContentTypeNav';
import type { TypeSection } from '@/Components/Admin/ContentTypes/section';
import { StatusToggleBadge } from '@/Components/Admin/StatusToggleBadge';
import { UserAvatar } from '@/Components/Admin/UserAvatar';
import { CopyableText } from '@/Components/Admin/CopyableText';
import { DataTable } from '@/Components/Admin/DataTable/DataTable';
import type { Column, ColumnType, TablePageProps } from '@/Components/Admin/DataTable/types';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * A node row: `uuid`, the service values (`slug`, the derived `status`, boolean `is_active`, the dates as
 * `Y-m-d H:i` and `null` for an empty `unpublished_at`, the integer `views`, the `author` as `{name, avatar}` or `null`) plus the display value of every visible field under the field's key.
 */
type ContentNodeRow = {
    uuid: string;
    /** Decided by the server (the `own` level limits changes to the user's nodes); the row shows no actions without it. */
    can_edit: boolean;
    slug: string;
    /** Relative public address of the node (`/about`, `/news/hello`), or `null` for blocks and types without a public slug. */
    public_url: string | null;
    status: NodeStatus;
    is_active: boolean;
    created_at: string | null;
    updated_at: string | null;
    published_at: string;
    unpublished_at: string | null;
    /** View counter of the node; `0` is shown as `0`, without separators. */
    views: number;
    /** The author of the node, or `null` when unknown; shown as a round avatar with the name. */
    author: { name: string; avatar: string | null } | null;
    /** Codes of the languages the node is translated into; present only while additional languages are active. */
    translations?: string[];
    [fieldKey: string]: unknown;
};

/** The value of an `image` cell: the file's URL and its original name. */
type ImageValue = { url: string; name: string; preview?: string | null };

/** The value of a `file` cell: the file's URL, original name and lowercase extension. */
type FileValue = ImageValue & { ext: string };

/** The value of a `url` cell: the link and its optional title. */
type UrlCellValue = { url: string; title: string | null };

/** The value of a `type_ref` / `node_ref` cell: the target's label (type name, node title) and the address of its edit page. */
type RefCellValue = { label: string; href: string };

/** A column the server chose for the type: `title` first, then `settings.list.visible` in its order. */
type NodeColumn = {
    key: string;
    /** A field's name is data, shown as is; a service column's name is an English translation key. */
    name: string;
    /** The table column type, or `image` / `file` for the two file fields the page renders itself. */
    type: ColumnType | 'image' | 'file';
    sortable: boolean;
    /** True for a service column of the node (slug, state, dates, activity, views) rather than a field. */
    service?: boolean;
};

/**
 * One cell value as the server sends it: a scalar, a boolean («Yes»/«No»), an `image` / `file` / `url` / reference (`{label, href}`) object or a list
 * of these (a multiple field), which is shown as a column of values, one per line. A file the media library no
 * longer knows arrives as a plain string and is shown as text.
 */
function NodeCell({ value }: { value: unknown }) {
    if (Array.isArray(value)) {
        return (
            <ul className="space-y-1">
                {value.map((item, index) => (
                    <li key={index}>
                        <NodeCell value={item} />
                    </li>
                ))}
            </ul>
        );
    }

    if (value === null || value === undefined || value === '') {
        return null;
    }

    if (typeof value === 'boolean') {
        return <BooleanBadge value={value} />;
    }

    // A reference shows the target's label as an in-app link: it opens in the same window (no `target`).
    if (typeof value === 'object' && 'label' in value && 'href' in value) {
        const ref = value as RefCellValue;

        return (
            <Link href={ref.href} className="text-link underline-offset-4 hover:underline">
                {ref.label}
            </Link>
        );
    }

    // A link shows its title (or the address); relative links resolve against the admin's host, in a new tab.
    if (typeof value === 'object' && 'url' in value && 'title' in value) {
        const link = value as UrlCellValue;

        return (
            <a href={link.url} target="_blank" rel="noopener noreferrer" className="text-link underline-offset-4 hover:underline">
                {link.title || link.url}
            </a>
        );
    }

    if (typeof value === 'object' && 'url' in value && 'name' in value) {
        const file = value as ImageValue | FileValue;

        // An image shows its `thumb` style (the original when the server sent none); the link opens the original.
        if (!('ext' in file)) {
            return (
                <a href={file.url} target="_blank" rel="noopener noreferrer">
                    <img src={file.preview ?? file.url} alt={file.name} loading="lazy" className="h-12 w-12 cursor-pointer rounded-sm border object-cover" />
                </a>
            );
        }

        // A PDF opens in the browser; any other type is offered as a download.
        return (
            <a
                href={file.url}
                target="_blank"
                rel="noopener noreferrer"
                {...(file.ext === 'pdf' ? {} : { download: '' })}
                className="text-link underline-offset-4 hover:underline"
            >
                {file.name}
            </a>
        );
    }

    return <>{String(value)}</>;
}

type Props = {
    section: TypeSection;
    /** Whether the type is in the user's favorites (the star of the title). */
    isFavorite: boolean;
    type: NavType;
    columns: NodeColumn[];
    table: TablePageProps<ContentNodeRow>;
    /** Active additional languages for the "Without translation in" filter; absent when none is active. */
    translationLanguages?: { code: string; name: string }[];
};

/**
 * Nodes of a content type: a server `DataTable` (state in the address; the only filter is the "Without translation in" quick filter of the Translations column, present while additional languages are active) whose columns come from the
 * `columns` prop in the server's order (`title`, then the type's `settings.list.visible`: fields and service
 * columns), a Create button and a row menu (edit, delete). The state column is a badge; other service columns use
 * the default cell of their type (date text, Yes/No); field cells go through `NodeCell`, which shows a multiple
 * value as a column, an image as a thumbnail link and a file as a name link. Search, sorting and paging run in the database (see
 * `ContentNodeController`). The `title` column cannot be hidden.
 *
 * @example
 * // GET /admin/content-types/news/nodes?q=lamp&sort=price&dir=desc
 */
export default function Index({ section, isFavorite, type, columns: nodeColumns, table, translationLanguages = [] }: Props) {
    const { t } = useTranslation();

    const columns: Column<ContentNodeRow>[] = nodeColumns.map((column) => ({
        key: column.key,
        header: column.name,
        // Image and file columns are plain text for the table; `NodeCell` renders them.
        type: column.type === 'image' || column.type === 'file' ? 'text' : column.type,
        sortable: column.sortable,
        hideable: column.key !== 'title',
        // Long texts and the service slug are cut to one line; the slug keeps its full value in the hint and is copied on click.
        width: column.key === 'slug' && column.service ? '16rem' : column.type === 'text' && !column.service ? '24rem' : undefined,
        ...(column.service
            ? column.key === 'translations'
                ? {
                      // The languages the node is translated into; the drop-down next to the search keeps the nodes WITHOUT a translation in the chosen one.
                      type: 'select' as const,
                      sortable: false,
                      quickFilter: true,
                      allLabel: 'All nodes',
                      options: translationLanguages.map((language) => ({
                          value: language.code,
                          label: t('Without translation in :language', { language: `${language.name} (${language.code})` }),
                      })),
                      cell: (row: ContentNodeRow) => (
                          <span className="inline-flex flex-wrap gap-1" data-testid="node-translations">
                              {(row.translations ?? []).map((code) => (
                                  <Badge key={code} variant="secondary">
                                      {code}
                                  </Badge>
                              ))}
                          </span>
                      ),
                  }
                : column.key === 'status'
                ? {
                      cell: (row: ContentNodeRow) => (
                          <StatusToggleBadge status={row.status} url={row.can_edit ? `${section.basePath}/${type.key}/nodes/${row.uuid}/active` : undefined}>
                              <NodeStatusBadge status={row.status} />
                          </StatusToggleBadge>
                      ),
                  }
                : column.key === 'slug'
                  ? { cell: (row: ContentNodeRow) => <CopyableText value={row.slug}>{row.slug}</CopyableText> }
                  : column.key === 'author'
                    ? {
                          cell: (row: ContentNodeRow) =>
                              row.author ? (
                                  <span className="inline-flex items-center gap-2">
                                      <UserAvatar name={row.author.name} src={row.author.avatar} />
                                      {row.author.name}
                                  </span>
                              ) : null,
                      }
                    : {}
            : { cell: (row: ContentNodeRow) => <NodeCell value={row[column.key]} /> }),
    }));

    return (
        <>
            <Head title={t('Nodes of “:name”', { name: type.name })} />
            <ContentTypeNav section={section} type={type} active="nodes">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-pressed={isFavorite}
                    aria-label={t(isFavorite ? 'Remove from favorites' : 'Add to favorites')}
                    data-testid="favorite-toggle"
                    // Keeps the table's filters, sorting and scroll: only the sidebar and the star change.
                    onClick={() => router[isFavorite ? 'delete' : 'post'](`/admin/favorites/${type.key}`, {}, { preserveState: true, preserveScroll: true })}
                >
                    <Star className={isFavorite ? 'fill-current text-amber-500' : ''} aria-hidden />
                </Button>
            </ContentTypeNav>

            <div>
                <DataTable
                    tableId={`content-nodes-${type.key}`}
                    columns={columns}
                    getRowId={(row) => row.uuid}
                    data={table}
                    rowActions={(row) =>
                        row.can_edit ? (
                            <ContentNodeRowActions
                                section={section}
                                typeKey={type.key}
                                row={{ uuid: row.uuid, slug: row.slug, title: row.title as string | null, publicUrl: row.public_url }}
                            />
                        ) : null
                    }
                    toolbarActions={
                        <Button asChild>
                            <Link href={`${section.basePath}/${type.key}/nodes/create`}>{t('Create')}</Link>
                        </Button>
                    }
                />
            </div>
        </>
    );
}

Index.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
