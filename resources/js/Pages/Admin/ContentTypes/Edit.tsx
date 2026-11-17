import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { ContentTypeNav, type NavType } from '@/Components/Admin/ContentTypes/ContentTypeNav';
import { ContentTypeForm, type ContentTypeValues, type ParentOption } from '@/Components/Admin/ContentTypes/ContentTypeForm';
import type { NodeListOptions } from '@/Components/Admin/ContentTypes/NodeListSettingsSection';
import { sectionLabels, type TypeSection } from '@/Components/Admin/ContentTypes/section';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    /** The values the form edits plus the section menu data (`ContentType::navProps()`). */
    type: ContentTypeValues & NavType;
    /** Content types except this one and its descendants. */
    parentOptions: ParentOption[];
    section: TypeSection;
    /** Columns, search fields and sort options of the "Node list" section with its current state. */
    listOptions: NodeListOptions;
};

/** Edit form of a type (`GET {basePath}/{key}/edit`); the key is shown read-only. The public display and the values of the type itself live on the "Display" and "SEO" tabs. */
export default function Edit({ type, parentOptions, section, listOptions }: Props) {
    const { t } = useTranslation();
    const labels = sectionLabels(section.kind);

    return (
        <>
            <Head title={t(labels.edit)} />
            <ContentTypeNav section={section} type={type} active="settings" />
            <ContentTypeForm mode="edit" section={section} type={type} parentOptions={parentOptions} listOptions={listOptions} />
        </>
    );
}

Edit.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
