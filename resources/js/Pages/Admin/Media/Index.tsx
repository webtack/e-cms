import { Head } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import type { ReactNode } from 'react';

import { DataTable } from '@/Components/Admin/DataTable/DataTable';
import type { Column, TablePageProps } from '@/Components/Admin/DataTable/types';
import { MediaRowActions } from '@/Components/Admin/Media/MediaRowActions';
import { UserAvatar } from '@/Components/Admin/UserAvatar';
import { Badge } from '@/Components/ui/badge';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { PageTitle } from '@/Components/Admin/PageTitle';

/** A library row (`MediaController::row`). */
type MediaRow = {
    uuid: string;
    /** URL of the original file; the preview links to it. */
    url: string;
    /** URL of the `thumb` image style for images, `null` for other files. */
    preview: string | null;
    name: string;
    mime: string;
    /** Short type label (`docx`) shown instead of the long MIME. */
    type: string;
    /** Bytes. */
    size: number;
    uploader: string | null;
    /** URL of the uploader's avatar, or `null` (the initials are shown). */
    uploader_avatar: string | null;
    created_at: string | null;
    /** Number of places the file is used in; 0 for an unused file. */
    uses: number;
    /** Decided by the server (the `own` level covers only the user's uploads). */
    can_delete: boolean;
};

type Props = {
    table: TablePageProps<MediaRow>;
    /** Short type labels the library can be filtered by (`FileTypes::options()`). */
    typeOptions: string[];
};

function formatSize(bytes: number): string {
    return bytes >= 1024 * 1024 ? `${(bytes / (1024 * 1024)).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/**
 * The media library: every file of the storage in a server `DataTable` (search by name, sorting and paging in the
 * database, state in the address) with the preview, name, type (a short label such as `docx`, also a quick filter
 * drop-down next to the search box), size, uploader, date and the number of uses. A file
 * that is not used shows 0 highlighted. The row menu opens the details (where the file is used) and deletes an unused
 * file after confirmation.
 *
 * @example
 * // GET /admin/media?q=report&sort=uses&dir=asc
 * // GET /admin/media?filters[0][c]=mime&filters[0][o]=eq&filters[0][v]=pdf
 */
export default function Index({ table, typeOptions }: Props) {
    const { t } = useTranslation();

    const columns: Column<MediaRow>[] = [
        {
            key: 'preview',
            header: 'Preview',
            type: 'text',
            hideable: false,
            cell: (row) =>
                row.preview ? (
                    <a href={row.url} target="_blank" rel="noopener noreferrer">
                        <img src={row.preview} alt="" className="size-10 cursor-pointer rounded object-cover" loading="lazy" />
                    </a>
                ) : (
                    <FileText className="size-6 text-muted-foreground" aria-hidden />
                ),
        },
        { key: 'name', header: 'Name', type: 'text', sortable: true, hideable: false, width: '24rem' },
        {
            key: 'mime',
            header: 'Type',
            type: 'select',
            sortable: true,
            quickFilter: true,
            allLabel: 'All types',
            options: typeOptions.map((type) => ({ value: type, label: type })),
            cell: (row) => row.type,
        },
        { key: 'size', header: 'Size', type: 'number', sortable: true, cell: (row) => formatSize(row.size) },
        {
            key: 'uploader',
            header: 'Uploaded by',
            type: 'text',
            cell: (row) =>
                row.uploader ? (
                    <span className="inline-flex items-center gap-2">
                        <UserAvatar name={row.uploader} src={row.uploader_avatar} />
                        {row.uploader}
                    </span>
                ) : null,
        },
        { key: 'created_at', header: 'Uploaded', type: 'date', sortable: true },
        {
            key: 'uses',
            header: 'Uses',
            type: 'number',
            sortable: true,
            cell: (row) => <Badge variant={row.uses === 0 ? 'warning' : 'info'}>{row.uses}</Badge>, //row.uses === 0 ?
        },
    ];

    return (
        <>
            <Head title={t('Media Library')} />
            <PageTitle>{t('Media Library')}</PageTitle>

            <div className="mt-6">
                <DataTable
                    tableId="media"
                    columns={columns}
                    getRowId={(row) => row.uuid}
                    data={table}
                    emptyMessage="No files yet."
                    rowActions={(row) => <MediaRowActions row={row} />}
                />
            </div>
        </>
    );
}

Index.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
