import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { MenuForm, type MenuValues } from '@/Components/Admin/Menus/MenuForm';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { PageTitle } from '@/Components/Admin/PageTitle';

type Props = {
    menu: MenuValues;
};

/** Edit form of a menu (`GET /admin/menus/{key}/edit`); the key is shown read-only. */
export default function Edit({ menu }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Edit menu')} />
            <PageTitle className="mb-6">{t('Edit menu')}</PageTitle>
            <MenuForm mode="edit" menu={menu} />
        </>
    );
}

Edit.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
