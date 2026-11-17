import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { UserForm, type UserValues } from '@/Components/Admin/Users/UserForm';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { PageTitle } from '@/Components/Admin/PageTitle';

type Props = {
    roles: { id: number; name: string }[];
    user: UserValues;
};

/** Edit form of a user (`GET /admin/users/{id}/edit`). */
export default function Edit({ roles, user }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Edit user')} />
            <PageTitle className="mb-6">{t('Edit user')}</PageTitle>
            <UserForm mode="edit" roles={roles} user={user} />
        </>
    );
}

Edit.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
