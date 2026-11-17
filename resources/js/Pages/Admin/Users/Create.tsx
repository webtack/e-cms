import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { UserForm } from '@/Components/Admin/Users/UserForm';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { PageTitle } from '@/Components/Admin/PageTitle';

type Props = {
    roles: { id: number; name: string }[];
};

/** Create form of a user (`GET /admin/users/create`). */
export default function Create({ roles }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Create user')} />
            <PageTitle className="mb-6">{t('Create user')}</PageTitle>
            <UserForm mode="create" roles={roles} />
        </>
    );
}

Create.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
