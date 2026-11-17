import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { ContentTypeNav, type NavType } from '@/Components/Admin/ContentTypes/ContentTypeNav';
import type { PublicDisplayOptions } from '@/Components/Admin/ContentTypes/PublicDisplaySettingsSection';
import { type TypeSection } from '@/Components/Admin/ContentTypes/section';
import { TypeDisplayForm } from '@/Components/Admin/ContentTypes/TypeDisplayForm';
import type { TypeField } from '@/Components/Admin/ContentTypes/TypeValuesPanels';
import type { NodeFiles } from '@/Components/Admin/ContentNodes/types';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import type { TranslationState } from '@/types/translation';

type Props = {
    type: NavType;
    section: TypeSection;
    /** Added fields, sort options and current state of the "Public display" section. */
    publicOptions: PublicDisplayOptions;
    /** Fields of the "Section" panel (`ContentTypeController::displayTab()`); empty for kinds `page` and `block`. */
    typeFields: TypeField[];
    /** Stored base-language values of those fields by key. */
    typeValues: Record<string, unknown>;
    /** Known files among the values by `{uuid}.{ext}`. */
    typeFiles: NodeFiles;
    /** Switcher and translation-mode state; `null` when the tab has no values to translate or no switcher is shown. */
    translation: TranslationState | null;
};

/**
 * The "Display" tab of a type of any kind (`GET {basePath}/{key}/display[?lang=xx]`): how the type looks on the site,
 * saved by one button, or the translation mode of the section values for one language.
 */
export default function Display({ type, section, publicOptions, typeFields, typeValues, typeFiles, translation }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={`${t('Display')} — ${type.name}`} />
            <ContentTypeNav section={section} type={type} active="display" />
            <TypeDisplayForm
                // A new language or freshly saved values remount the form so that it starts clean.
                key={`${translation?.lang ?? 'base'}:${translation?.exists ? 1 : 0}:${JSON.stringify(translation?.values ?? null)}`}
                section={section} typeKey={type.key} fields={typeFields} values={typeValues} files={typeFiles} publicOptions={publicOptions} translation={translation} />
        </>
    );
}

Display.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
