import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { TermForm, type ParentOption } from '@/Components/Admin/Taxonomy/TermForm';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { PageTitle } from '@/Components/Admin/PageTitle';

type Props = {
    vocabulary: { key: string; name: string; max_depth: number | null };
    parentOptions: ParentOption[];
    defaultWeight: number;
};

/** Create form of a term (`GET /admin/taxonomy/{key}/terms/create`). */
export default function Create({ vocabulary, parentOptions, defaultWeight }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Create term')} />
            <PageTitle className="mb-6">{t('Create term')}</PageTitle>
            <TermForm mode="create" vocabularyKey={vocabulary.key} parentOptions={parentOptions} defaultWeight={defaultWeight} />
        </>
    );
}

Create.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
