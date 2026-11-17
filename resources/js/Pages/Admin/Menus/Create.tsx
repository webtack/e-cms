import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { MenuForm } from '@/Components/Admin/Menus/MenuForm';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { PageTitle } from '@/Components/Admin/PageTitle';

/** Create form of a menu (`GET /admin/menus/create`). */
export default function Create() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Create menu')} />
            <PageTitle className="mb-6">{t('Create menu')}</PageTitle>
            <MenuForm mode="create" />
        </>
    );
}

Create.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
