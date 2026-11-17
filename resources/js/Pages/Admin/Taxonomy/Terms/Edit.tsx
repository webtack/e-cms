import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { TermForm, type ImageFile, type ParentOption, type TermTexts, type TermValues } from '@/Components/Admin/Taxonomy/TermForm';
import AdminLayout from '@/Layouts/AdminLayout';
import type { TranslationState } from '@/types/translation';
import { useTranslation } from '@/hooks/useTranslation';
import { PageTitle } from '@/Components/Admin/PageTitle';

type Props = {
    vocabulary: { key: string; name: string; max_depth: number | null };
    term: TermValues;
    imageFile: ImageFile | null;
    /** Terms except this one and its descendants, and only those that keep the subtree within the maximum depth. */
    parentOptions: ParentOption[];
    /** Switcher and translation-mode state; `null` when no switcher is shown. */
    translation: TranslationState<TermTexts> | null;
};

/** Edit form of a term (`GET /admin/taxonomy/{key}/terms/{uuid}/edit`). */
export default function Edit({ vocabulary, term, imageFile, parentOptions, translation }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Edit term')} />
            <PageTitle className="mb-6">{t('Edit term')}</PageTitle>
            <TermForm
                // A new language or freshly saved values remount the form so that it starts clean.
                key={`${translation?.lang ?? 'base'}:${translation?.exists ? 1 : 0}:${JSON.stringify(translation?.values ?? null)}`}
                mode="edit"
                vocabularyKey={vocabulary.key}
                term={term}
                imageFile={imageFile}
                parentOptions={parentOptions}
                translation={translation}
            />
        </>
    );
}

Edit.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
