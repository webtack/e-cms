import { useForm } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';

import type { UploadedFile } from '@/Components/Admin/FileUpload/FileUpload';
import { FormActionBar } from '@/Components/Admin/FormActionBar';
import { ClearTranslationButton } from '@/Components/Admin/Translation/ClearTranslationButton';
import { LanguageSwitcher } from '@/Components/Admin/Translation/LanguageSwitcher';
import { NodeFieldFrame } from '@/Components/Admin/ContentNodes/NodeFieldFrame';
import { NodeFieldsPanel } from '@/Components/Admin/ContentNodes/NodeFieldsPanel';
import { initialFileMeta, translationFileMeta } from '@/Components/Admin/ContentNodes/fieldValues';
import type { NodeField, NodeFieldValue, NodeFileMeta, NodeFileMetaEditor, NodeFiles } from '@/Components/Admin/ContentNodes/types';
import { useTranslation } from '@/hooks/useTranslation';
import type { TranslationState } from '@/types/translation';

import { MetaDataSection, type MetaChoice, type MetaOptions } from './MetaDataSection';
import type { TypeSection } from './section';
import {FieldGroup} from "@/Components/Admin/FieldGroup.tsx";

/** A field of a type panel: a node form field that also names its panel. */
export type TypeField = NodeField & { panel: 'section' | 'seo' };

type Props = {
    section: TypeSection;
    /** Key of the content type whose own values are edited. */
    typeKey: string;
    /** Which panel this form edits; only "SEO" has its own form, "Section" lives on the "Display" tab. */
    panel: 'seo';
    /** The fields of that panel (`typeFields` of the tab page), in order of weight. */
    fields: TypeField[];
    /** Stored base-language values by field key (`null` for none). */
    values: Record<string, unknown>;
    /** Names, previews and URLs of the stored files by `{uuid}.{ext}` value. */
    files?: NodeFiles;
    /** The "Meta data" block below the values; saved by the same button (`settings.public.meta`). */
    metaOptions: MetaOptions;
    /** The language switcher state (base mode); `null` or absent when no switcher is shown. */
    translation?: TranslationState | null;
};

/**
 * The one form of the "SEO" tab of a content or page type: the values of the type itself (not of its nodes), edited
 * in the base language (the language switcher stands above the form when the tab has one; with `translation.lang` the same form is the translation mode: the values show that language, the "Meta data" block keeps its place inert and dimmed, "Save" sends `panel` and `values` to `PUT {basePath}/{key}/values/translations/{lang}`, and "Clear translation" joins the bar), and the "Meta data" block below them. One `PUT {basePath}/{key}/values` sends the values
 * of its own panel and `settings.public.meta`, so the server saves both or neither and saving the tab never changes
 * the other one; an emptied field clears its value, a server error stands next to its field (`values.{key}`) or, for
 * the choice (`settings*`), below the block, and what was typed stays. The fields are the usual `NodeFieldFrame`s inside a `NodeFieldsPanel`, so
 * inputs, uploads and errors work as in the node form. The sticky action bar is the last child of the form.
 *
 * @example
 * <TypeValuesPanels section={section} typeKey="news" panel="seo" fields={typeFields} values={typeValues} files={typeFiles} metaOptions={metaOptions} />
 */
export function TypeValuesPanels({ section, typeKey, panel, fields, values, files = {}, metaOptions, translation = null }: Props) {
    const { t } = useTranslation();
    const lang = translation?.lang ?? null;
    const translating = lang !== null;
    const { data, setData, put, transform, processing, errors, isDirty } = useForm<{ values: Record<string, string>; meta: MetaChoice; file_meta: Record<string, NodeFileMeta> }>({
        // In the translation mode the server hands over the prefilled values of that language (the translation, else the base).
        values: Object.fromEntries(fields.map((field) => { const source = translating ? translation?.values : values; return [field.key, source?.[field.key] == null ? '' : String(source[field.key])]; })),
        meta: { ...metaOptions.current },
        // The names and descriptions of the files: prefilled for the language in the translation mode, not sent in the base mode.
        file_meta: initialFileMeta(files),
    });
    const metaErrors = Object.entries(errors as Record<string, string>)
        .filter(([key]) => key === 'settings' || key.startsWith('settings.'))
        .map(([, message]) => message);
    const [known, setKnown] = useState<NodeFiles>(files);
    const error = (path: string) => (errors as Record<string, string | undefined>)[path];
    const rememberFile = (file: UploadedFile) => {
        setKnown((current) => ({ ...current, [file.value]: { uuid: file.uuid, name: file.name, mime: file.mime, size: file.size, url: file.url, preview: file.preview } }));
        setData((current) => ({ ...current, file_meta: { ...current.file_meta, [file.uuid]: { name: file.name, description: '' } } }));
    };
    // In the translation mode a file can get a display name and a description in the language.
    const fileMeta: NodeFileMetaEditor | undefined = translating
        ? {
              values: data.file_meta,
              onChange: (uuid, patch) => setData((current) => ({ ...current, file_meta: { ...current.file_meta, [uuid]: { ...current.file_meta[uuid], ...patch } } })),
              errorAt: (uuid, key) => error(`file_meta.${uuid}.${key}`),
          }
        : undefined;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        // Empty choices are not sent; the server drops them as well. A translation sends the panel and its values only.
        transform((form) =>
            translating
                ? { panel, values: form.values, file_meta: translationFileMeta(fields, form.values, form.file_meta) }
                : {
                      values: form.values,
                      file_meta: undefined,
                      settings: { public: { meta: Object.fromEntries(Object.entries(form.meta).filter(([, key]) => key)) } },
                  },
        );
        put(translating ? translationUrl : `${section.basePath}/${typeKey}/values`, { preserveScroll: true });
    };

    const translationUrl = `${section.basePath}/${typeKey}/values/translations/${encodeURIComponent(lang ?? '')}`;
    /** In the translation mode what is not translated keeps its place, shows the base value and is out of reach. */
    const lock = (node: ReactNode) =>
        translating ? (
            <div inert aria-disabled="true" className="select-none opacity-60" data-testid="meta-locked">
                {node}
            </div>
        ) : (
            node
        );

    return (
        <>
        {translation && <LanguageSwitcher translation={translation} editUrl={`${section.basePath}/${typeKey}/seo`} dirty={isDirty} />}
        <form onSubmit={submit} noValidate data-testid="type-values-form">
            <div className="mb-6 max-w-2xl ">
                <div data-testid={`${panel}-panel`} className={'bg-card p-3 border space-y-3 rounded-md'}>
                    <h2 className={'font-bold text-lg mb-1'}>{t('Seo')}</h2>
                    <p className="text-sm text-muted-foreground">
                        {t('Shown to search engines and when the page is shared.')}
                    </p>
                    {fields.map((field) => (
                        <NodeFieldFrame
                            key={field.key}
                            field={field}
                            typeKey={typeKey}
                            value={data.values[field.key]}
                            onChange={(next: NodeFieldValue) => setData('values', {
                                ...data.values,
                                [field.key]: typeof next === 'string' ? next : ''
                            })}
                            files={known}
                            onFile={rememberFile}
                            fileMeta={fileMeta}
                            error={error}
                        />
                    ))}
                </div>
            </div>
            {lock(<MetaDataSection options={metaOptions} value={data.meta} onChange={(meta) => setData('meta', meta)} errors={metaErrors} />)}
            <FormActionBar cancelHref={section.basePath} processing={processing} saveDisabled={translating && !isDirty}>
                {translating && translation?.exists && <ClearTranslationButton clearUrl={`${translationUrl}?panel=${panel}`} />}
            </FormActionBar>
        </form>
        </>
    );
}
