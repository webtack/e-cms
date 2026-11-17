import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { VocabularyForm, type VocabularyValues } from '@/Components/Admin/Taxonomy/VocabularyForm';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { PageTitle } from '@/Components/Admin/PageTitle';

type Props = {
    vocabulary: VocabularyValues;
};

/** Edit form of a vocabulary (`GET /admin/taxonomy/{key}/edit`); the key is shown read-only. */
export default function Edit({ vocabulary }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Edit vocabulary')} />
            <PageTitle className="mb-6">{t('Edit vocabulary')}</PageTitle>
            <VocabularyForm mode="edit" vocabulary={vocabulary} />
        </>
    );
}

Edit.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
