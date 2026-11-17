import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { DataTable } from '@/Components/Admin/DataTable/DataTable';
import type { Column, TablePageProps } from '@/Components/Admin/DataTable/types';
import { RoleRowActions } from '@/Components/Admin/Roles/RoleRowActions';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { PageTitle } from '@/Components/Admin/PageTitle';

type RoleRow = {
    id: number;
    name: string;
    users_count: number;
    is_system: boolean;
};

type Props = {
    table: TablePageProps<RoleRow>;
};

/**
 * Roles list (`GET /admin/roles`): a server `DataTable` (state in the address, no filters) with a Create button
 * and a row menu (edit, delete). The system role is marked with a badge and cannot be deleted.
 */
export default function Index({ table }: Props) {
    const { t } = useTranslation();

    const columns: Column<RoleRow>[] = [
        {
            key: 'name',
            header: 'Name',
            type: 'text',
            sortable: true,
            hideable: false,
            cell: (row) => (
                <span className="inline-flex items-center gap-2">
                    {row.name}
                    {row.is_system && <Badge variant="info">{t('System')}</Badge>}
                </span>
            ),
        },
        { key: 'users_count', header: 'Users', type: 'number', sortable: true, searchable: false },
        { key: 'is_system', header: 'System', type: 'boolean', sortable: true, searchable: false },
    ];

    return (
        <>
            <Head title={t('Roles')} />
            <PageTitle>{t('Roles')}</PageTitle>

            <div className="mt-6">
                <DataTable
                    tableId="roles"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    data={table}
                    rowActions={(row) => <RoleRowActions row={row} />}
                    toolbarActions={
                        <Button asChild>
                            <Link href="/admin/roles/create">{t('Create')}</Link>
                        </Button>
                    }
                />
            </div>
        </>
    );
}

Index.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
