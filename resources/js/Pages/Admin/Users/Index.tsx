import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { DataTable } from '@/Components/Admin/DataTable/DataTable';
import type { Column, TablePageProps } from '@/Components/Admin/DataTable/types';
import { UserAvatar } from '@/Components/Admin/UserAvatar';
import { UserRowActions } from '@/Components/Admin/Users/UserRowActions';
import { Button } from '@/Components/ui/button';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { PageTitle } from '@/Components/Admin/PageTitle';

type UserRow = {
    id: number;
    name: string;
    /** URL of the user's avatar, or `null` (the initials are shown). */
    avatar: string | null;
    email: string;
    role: string;
    is_active: boolean;
    /** The signed-in user cannot be deleted from their own list. */
    is_self: boolean;
    /** `false` for an administrator when the signed-in user is not one: the row has no actions. */
    can_manage: boolean;
};

type Props = {
    table: TablePageProps<UserRow>;
};

/**
 * Users list (`GET /admin/users`): a server `DataTable` (state in the address, no filters) with name, email, role
 * and activity, a Create button and a row menu (edit, delete).
 */
export default function Index({ table }: Props) {
    const { t } = useTranslation();

    const columns: Column<UserRow>[] = [
        {
            key: 'name',
            header: 'Name',
            type: 'text',
            sortable: true,
            hideable: false,
            cell: (row) => (
                <span className="inline-flex items-center gap-2">
                    <UserAvatar name={row.name} src={row.avatar} active={row.is_active} />
                    {row.name}
                </span>
            ),
        },
        { key: 'email', header: 'Email', type: 'text', sortable: true },
        { key: 'role', header: 'Role', type: 'text', sortable: true },
        {
            key: 'is_active',
            header: 'Active',
            type: 'boolean',
            sortable: true,
            searchable: false,
            booleanTone: 'warning',
        },
    ];

    return (
        <>
            <Head title={t('Users')} />
            <PageTitle>{t('Users')}</PageTitle>

            <div className="mt-6">
                <DataTable
                    tableId="users"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    data={table}
                    rowActions={(row) => <UserRowActions row={row} />}
                    toolbarActions={
                        <Button asChild>
                            <Link href="/admin/users/create">{t('Create')}</Link>
                        </Button>
                    }
                />
            </div>
        </>
    );
}

Index.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
