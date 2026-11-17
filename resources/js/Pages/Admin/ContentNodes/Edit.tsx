import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { ContentNodeForm } from '@/Components/Admin/ContentNodes/ContentNodeForm';
import type { NodeField, NodeFiles, NodeMeta, NodeRefLabels, NodeTranslationValues, NodeValues } from '@/Components/Admin/ContentNodes/types';
import { ContentTypeNav, type NavType } from '@/Components/Admin/ContentTypes/ContentTypeNav';
import type { TypeSection } from '@/Components/Admin/ContentTypes/section';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import type { TranslationState } from '@/types/translation';

type Props = {
    section: TypeSection;
    type: NavType;
    /** The node is addressed by `uuid`; `slug` is its public identifier and is editable. */
    node: NodeMeta;
    fields: NodeField[];
    values: NodeValues;
    /** Names, previews and URLs of the stored files by `{uuid}.{ext}`. */
    files: NodeFiles;
    /** Labels of the targets of `type_ref` / `node_ref` values by stored value (type key or node uuid). */
    labels: NodeRefLabels;
    /** Fixed slug prefix of the section's nodes (`block-` for blocks). */
    slugPrefix: string;
    /** Relative public address of the saved node (`/{slug}` or `/{type slug}/{slug}`); `null` when it has none (blocks). */
    publicUrl: string | null;
    /** Switcher and translation-mode state; `null` when no switcher is shown. */
    translation: TranslationState<NodeTranslationValues> | null;
};

/** Edit form of a node (`GET {basePath}/{key}/nodes/{uuid}/edit[?lang=xx]`): the base-language values, or the translation mode of one language. */
export default function Edit({ section, type, node, fields, values, files, labels, slugPrefix, publicUrl, translation }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Edit node')} />
            <ContentTypeNav section={section} type={type} active="nodes" />
            <ContentNodeForm
                // A new language or freshly saved values remount the form so that it starts clean.
                key={`${translation?.lang ?? 'base'}:${translation?.exists ? 1 : 0}:${JSON.stringify(translation?.values ?? null)}`}
                mode="edit" section={section} typeKey={type.key} fields={fields} node={node} values={values} files={files} labels={labels} slugPrefix={slugPrefix} publicUrl={publicUrl} translation={translation} />
        </>
    );
}

Edit.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
