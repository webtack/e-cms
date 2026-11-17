import { Head } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import { useState, type ReactNode } from 'react';

import { DataTable } from '@/Components/Admin/DataTable/DataTable';
import type { Column, TablePageProps } from '@/Components/Admin/DataTable/types';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { PageTitle } from '@/Components/Admin/PageTitle';

type Product = {
    id: number;
    name: string;
    price: number;
    stock: number;
    released: string;
    active: boolean;
    status: string;
    note: string | null;
};

type Props = {
    table: TablePageProps<Product>;
    clientRows: Product[];
};

const statusLabels: Record<string, string> = { draft: 'Draft', review: 'In review', published: 'Published' };

/** Status cell: a translated badge, to show a custom `cell` renderer. */
function StatusBadge({ status }: { status: string }) {
    const { t } = useTranslation();

    return <Badge variant={status === 'published' ? 'default' : 'secondary'}>{t(statusLabels[status] ?? status)}</Badge>;
}

/** Row menu: shows the `rowActions` extension point with a single working item. */
function ProductRowActions() {
    const { t } = useTranslation();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon-sm" aria-label={t('Actions')}>
                    <MoreHorizontal aria-hidden />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem>{t('Details')}</DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

const columns: Column<Product>[] = [
    { key: 'id', header: 'ID', type: 'number', sortable: true, filterable: true, hideable: false },
    { key: 'name', header: 'Name', type: 'text', sortable: true, filterable: true, hideable: false },
    { key: 'price', header: 'Price', type: 'number', sortable: true, filterable: true },
    { key: 'stock', header: 'Stock', type: 'number', sortable: true, filterable: true },
    { key: 'released', header: 'Released', type: 'date', sortable: true, filterable: true },
    { key: 'active', header: 'Active', type: 'boolean', sortable: true, filterable: true },
    {
        key: 'status',
        header: 'Status',
        type: 'select',
        sortable: true,
        filterable: true,
        options: Object.entries(statusLabels).map(([value, label]) => ({ value, label })),
        cell: (row) => <StatusBadge status={row.status} />,
    },
    { key: 'note', header: 'Note', type: 'text', sortable: true, defaultVisible: false, width: '12rem' },
];

/**
 * Developer demo of `DataTable`: a server table (42 rows, state in the address)
 * a client table (8 rows, state in the browser) and a client table whose rows can be reordered by
 * dragging (5 rows). Exists outside production only.
 */
export default function DataTableDemo({ table, clientRows }: Props) {
    const { t } = useTranslation();
    // The reorderable table owns the order of its rows (nothing is saved in the demo).
    const [orderedRows, setOrderedRows] = useState(() => clientRows.slice(0, 5));

    return (
        <>
            <Head title={t('Data table demo')} />
            <PageTitle>{t('Data table demo')}</PageTitle>

            <section className="mt-6 flex flex-col gap-3">
                <h2 className="text-lg font-medium">{t('Server table')}</h2>
                <DataTable
                    tableId="demo-server"
                    filterable
                    columns={columns}
                    getRowId={(row) => row.id}
                    data={table}
                    rowActions={() => <ProductRowActions />}
                    toolbarActions={<Button size="sm">{t('Create')}</Button>}
                />
            </section>

            <section className="mt-10 flex flex-col gap-3">
                <h2 className="text-lg font-medium">{t('Client table')}</h2>
                <DataTable
                    mode="client"
                    tableId="demo-client"
                    filterable
                    columns={columns}
                    getRowId={(row) => row.id}
                    rows={clientRows}
                    rowActions={() => <ProductRowActions />}
                    toolbarActions={<Button size="sm">{t('Create')}</Button>}
                />
            </section>

            <section className="mt-10 flex flex-col gap-3">
                <h2 className="text-lg font-medium">{t('Reorderable table')}</h2>
                <DataTable
                    mode="client"
                    tableId="demo-reorder"
                    columns={columns}
                    getRowId={(row) => row.id}
                    rows={orderedRows}
                    reorder={{ onReorder: (ids) => setOrderedRows(ids.map((id) => orderedRows.find((row) => String(row.id) === id)!)) }}
                />
            </section>
        </>
    );
}

DataTableDemo.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
