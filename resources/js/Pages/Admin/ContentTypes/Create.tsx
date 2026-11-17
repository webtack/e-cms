import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { ContentTypeForm, type ParentOption } from '@/Components/Admin/ContentTypes/ContentTypeForm';
import { sectionLabels, type TypeSection } from '@/Components/Admin/ContentTypes/section';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { PageTitle } from '@/Components/Admin/PageTitle';

type Props = {
    parentOptions: ParentOption[];
    /** Key of the parent to preselect (from `?parent_type=`), `null` when absent or not offered. */
    defaultParent: string | null;
    section: TypeSection;
};

/** Create form of a type (`GET /admin/content-types/create`, `/admin/page-types/create` or `/admin/block-types/create`); the kind comes from the section. */
export default function Create({ parentOptions, defaultParent, section }: Props) {
    const { t } = useTranslation();
    const labels = sectionLabels(section.kind);

    return (
        <>
            <Head title={t(labels.create)} />
            <PageTitle className="mb-6">{t(labels.create)}</PageTitle>
            <ContentTypeForm mode="create" section={section} parentOptions={parentOptions} defaultParent={defaultParent} />
        </>
    );
}

Create.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
