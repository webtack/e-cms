import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import type { MetaOptions } from '@/Components/Admin/ContentTypes/MetaDataSection';
import { ContentTypeNav, type NavType } from '@/Components/Admin/ContentTypes/ContentTypeNav';
import { type TypeSection } from '@/Components/Admin/ContentTypes/section';
import { TypeValuesPanels, type TypeField } from '@/Components/Admin/ContentTypes/TypeValuesPanels';
import type { NodeFiles } from '@/Components/Admin/ContentNodes/types';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import type { TranslationState } from '@/types/translation';

type Props = {
    type: NavType;
    section: TypeSection;
    /** Fields of the "SEO" panel only (`ContentTypeController::seoTab()`). */
    typeFields: TypeField[];
    /** Stored base-language values of those fields by key. */
    typeValues: Record<string, unknown>;
    /** Known files among the values by `{uuid}.{ext}`. */
    typeFiles: NodeFiles;
    /** The "Meta data" block: the fields it offers and the current choice (`PublicDisplaySettings::metaOptions()`). */
    metaOptions: MetaOptions;
    /** Switcher and translation-mode state; `null` when no switcher is shown. */
    translation: TranslationState | null;
};

/**
 * The "SEO" tab of a content or page type (`GET {basePath}/{key}/seo`): one form with the type's own seo values and,
 * below them, the "Meta data" block, saved together by one sticky "Save". With `?lang=xx` it is the translation mode
 * of the `meta_*` values for one language (the "Meta data" block is not part of it).
 */
export default function Seo({ type, section, typeFields, typeValues, typeFiles, metaOptions, translation }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={`${t('SEO')} — ${type.name}`} />
            <ContentTypeNav section={section} type={type} active="seo" />
            <TypeValuesPanels
                // A new language or freshly saved values remount the form so that it starts clean.
                key={`${translation?.lang ?? 'base'}:${translation?.exists ? 1 : 0}:${JSON.stringify(translation?.values ?? null)}`}
                section={section} typeKey={type.key} panel="seo" fields={typeFields} values={typeValues} files={typeFiles} metaOptions={metaOptions} translation={translation} />
        </>
    );
}

Seo.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
