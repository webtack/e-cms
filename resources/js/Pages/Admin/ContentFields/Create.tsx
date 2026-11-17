import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import type { TypeSection } from '@/Components/Admin/ContentTypes/section';
import { ContentTypeNav, type NavType } from '@/Components/Admin/ContentTypes/ContentTypeNav';
import { ContentFieldForm, type ContentTypeOption, type FileTypeProfile, type VocabularyOption } from '@/Components/Admin/ContentFields/ContentFieldForm';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    section: TypeSection;
    type: NavType;
    typeOptions: string[];
    /** Whole profile of each file-bearing type (`image`, `file`). */
    fileTypes: Record<string, FileTypeProfile>;
    /** Vocabularies a `term` field can point at. */
    vocabularies: VocabularyOption[];
    /** Kinds of content types a `type_ref` field can point at. */
    kinds: string[];
    /** Every content type, for the type search of a `node_ref` field. */
    contentTypes: ContentTypeOption[];
    /** The highest weight of the type's fields plus one. */
    defaultWeight: number;
};

/** Create form of a content field (`GET {basePath}/{key}/fields/create`). */
export default function Create({ section, type, typeOptions, fileTypes, vocabularies, kinds, contentTypes, defaultWeight }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Create field')} />
            <ContentTypeNav section={section} type={type} active="fields" />
            <ContentFieldForm mode="create" section={section} typeKey={type.key} typeOptions={typeOptions} fileTypes={fileTypes} vocabularies={vocabularies} kinds={kinds} contentTypes={contentTypes} defaultWeight={defaultWeight} />
        </>
    );
}

Create.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
