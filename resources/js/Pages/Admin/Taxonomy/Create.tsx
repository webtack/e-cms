import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { VocabularyForm } from '@/Components/Admin/Taxonomy/VocabularyForm';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { PageTitle } from '@/Components/Admin/PageTitle';

/** Create form of a vocabulary (`GET /admin/taxonomy/create`). */
export default function Create() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Create vocabulary')} />
            <PageTitle className="mb-6">{t('Create vocabulary')}</PageTitle>
            <VocabularyForm mode="create" />
        </>
    );
}

Create.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
