import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { DataTable } from '@/Components/Admin/DataTable/DataTable';
import type { Column, TablePageProps } from '@/Components/Admin/DataTable/types';
import { MenuRowActions } from '@/Components/Admin/Menus/MenuRowActions';
import { Button } from '@/Components/ui/button';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { PageTitle } from '@/Components/Admin/PageTitle';

type MenuRow = {
    key: string;
    name: string;
    items_count: number;
    max_depth: number | null;
    /** Server-only column: searched, shown under the name. */
    description: string | null;
};

type Props = {
    table: TablePageProps<MenuRow>;
};

const columns: Column<MenuRow>[] = [
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
    { key: 'items_count', header: 'Items', type: 'number', sortable: true },
    { key: 'max_depth', header: 'Maximum depth', type: 'number', sortable: true, searchable: false, cell: (row) => row.max_depth ?? '' },
];

/**
 * Menus list (`GET /admin/menus`): a server `DataTable` (state in the address, no filters) with a Create
 * button linking to the create form and a row menu (items, edit, delete).
 */
export default function Index({ table }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Menus')} />
            <PageTitle>{t('Menus')}</PageTitle>

            <div className="mt-6">
                <DataTable
                    tableId="menus"
                    columns={columns}
                    getRowId={(row) => row.key}
                    data={table}
                    rowActions={(row) => <MenuRowActions row={row} />}
                    toolbarActions={
                        <Button asChild>
                            <Link href="/admin/menus/create">{t('Create')}</Link>
                        </Button>
                    }
                />
            </div>
        </>
    );
}

Index.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
