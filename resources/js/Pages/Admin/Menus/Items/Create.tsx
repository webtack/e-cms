import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { MenuItemForm, type ParentOption } from '@/Components/Admin/Menus/MenuItemForm';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { PageTitle } from '@/Components/Admin/PageTitle';

type Props = {
    menu: { key: string; name: string; max_depth: number | null };
    parentOptions: ParentOption[];
    defaultWeight: number;
};

/** Create form of a menu item (`GET /admin/menus/{key}/items/create`). */
export default function Create({ menu, parentOptions, defaultWeight }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Create menu item')} />
            <PageTitle className="mb-6">{t('Create menu item')}</PageTitle>
            <MenuItemForm mode="create" menuKey={menu.key} parentOptions={parentOptions} defaultWeight={defaultWeight} />
        </>
    );
}

Create.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
