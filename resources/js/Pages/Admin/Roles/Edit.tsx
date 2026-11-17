import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import type { TypeGroup } from '@/Components/Admin/Roles/PermissionMatrix';
import { RoleForm, type RoleValues } from '@/Components/Admin/Roles/RoleForm';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { PageTitle } from '@/Components/Admin/PageTitle';

type Props = {
    role: RoleValues;
    groups: TypeGroup[];
};

/** Edit form of a role (`GET /admin/roles/{id}/edit`); the system role is shown read-only. */
export default function Edit({ role, groups }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Edit role')} />
            <PageTitle className="mb-6">{t('Edit role')}</PageTitle>
            <RoleForm mode="edit" role={role} groups={groups} />
        </>
    );
}

Edit.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
