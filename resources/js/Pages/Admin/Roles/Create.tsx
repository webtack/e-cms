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

/** Create form of a role (`GET /admin/roles/create`): the name and an empty permission matrix. */
export default function Create({ role, groups }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Create role')} />
            <PageTitle className="mb-6">{t('Create role')}</PageTitle>
            <RoleForm mode="create" role={role} groups={groups} />
        </>
    );
}

Create.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
