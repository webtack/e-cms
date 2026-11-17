import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { ContentNodeForm } from '@/Components/Admin/ContentNodes/ContentNodeForm';
import type { NodeField, NodeMeta } from '@/Components/Admin/ContentNodes/types';
import { ContentTypeNav, type NavType } from '@/Components/Admin/ContentTypes/ContentTypeNav';
import type { TypeSection } from '@/Components/Admin/ContentTypes/section';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    section: TypeSection;
    type: NavType;
    fields: NodeField[];
    node: NodeMeta;
    /** Fixed slug prefix of the section's nodes (`block-` for blocks). */
    slugPrefix: string;
};

/** Create form of a node (`GET {basePath}/{key}/nodes/create`). */
export default function Create({ section, type, fields, node, slugPrefix }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Create node')} />
            <ContentTypeNav section={section} type={type} active="nodes" />
            <ContentNodeForm mode="create" section={section} typeKey={type.key} fields={fields} node={node} slugPrefix={slugPrefix} />
        </>
    );
}

Create.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
