import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { MenuItemForm, type ImageFile, type ItemTexts, type MenuItemValues, type ParentOption } from '@/Components/Admin/Menus/MenuItemForm';
import AdminLayout from '@/Layouts/AdminLayout';
import type { TranslationState } from '@/types/translation';
import { useTranslation } from '@/hooks/useTranslation';
import { PageTitle } from '@/Components/Admin/PageTitle';

type Props = {
    menu: { key: string; name: string; max_depth: number | null };
    item: MenuItemValues;
    imageFile: ImageFile | null;
    /** Items except this one and its descendants, and only those that keep the subtree within the maximum depth. */
    parentOptions: ParentOption[];
    /** Switcher and translation-mode state; `null` when no switcher is shown. */
    translation: TranslationState<ItemTexts> | null;
};

/** Edit form of a menu item (`GET /admin/menus/{key}/items/{uuid}/edit`). */
export default function Edit({ menu, item, imageFile, parentOptions, translation }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Edit menu item')} />
            <PageTitle className="mb-6">{t('Edit menu item')}</PageTitle>
            <MenuItemForm
                // A new language or freshly saved values remount the form so that it starts clean.
                key={`${translation?.lang ?? 'base'}:${translation?.exists ? 1 : 0}:${JSON.stringify(translation?.values ?? null)}`}
                mode="edit"
                menuKey={menu.key}
                item={item}
                imageFile={imageFile}
                parentOptions={parentOptions}
                translation={translation}
            />
        </>
    );
}

Edit.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
