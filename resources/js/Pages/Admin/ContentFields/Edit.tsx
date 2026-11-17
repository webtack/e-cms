import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import type { TypeSection } from '@/Components/Admin/ContentTypes/section';
import { ContentTypeNav, type NavType } from '@/Components/Admin/ContentTypes/ContentTypeNav';
import { ContentFieldForm, type ContentFieldValues, type ContentTypeOption, type FileTypeProfile, type VocabularyOption } from '@/Components/Admin/ContentFields/ContentFieldForm';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import type { TranslationState } from '@/types/translation';

type Props = {
    section: TypeSection;
    type: NavType;
    field: ContentFieldValues;
    typeOptions: string[];
    /** Whole profile of each file-bearing type (`image`, `file`). */
    fileTypes: Record<string, FileTypeProfile>;
    /** Vocabularies a `term` field can point at. */
    vocabularies: VocabularyOption[];
    /** Kinds of content types a `type_ref` field can point at. */
    kinds: string[];
    /** Every content type, for the type search of a `node_ref` field. */
    contentTypes: ContentTypeOption[];
    /** Switcher and translation-mode state of the label; `null` when no switcher is shown. */
    translation: TranslationState<{ name: string }> | null;
};

/** Edit form of a content field (`GET {basePath}/{key}/fields/{field}/edit[?lang=xx]`); the key is shown read-only. With `lang` it is the translation mode of the label. */
export default function Edit({ section, type, field, typeOptions, fileTypes, vocabularies, kinds, contentTypes, translation }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Edit field')} />
            <ContentTypeNav section={section} type={type} active="fields" />
            <ContentFieldForm
                // A new language or a freshly saved label remounts the form so that it starts clean.
                key={`${translation?.lang ?? 'base'}:${translation?.exists ? 1 : 0}:${JSON.stringify(translation?.values ?? null)}`}
                mode="edit" section={section} typeKey={type.key} typeOptions={typeOptions} fileTypes={fileTypes} vocabularies={vocabularies} kinds={kinds} contentTypes={contentTypes} field={field} translation={translation} />
        </>
    );
}

Edit.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
