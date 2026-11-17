import { useForm } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';

import { NodeFieldFrame } from '@/Components/Admin/ContentNodes/NodeFieldFrame';
import { NodeFieldsPanel } from '@/Components/Admin/ContentNodes/NodeFieldsPanel';
import { initialFileMeta, translationFileMeta } from '@/Components/Admin/ContentNodes/fieldValues';
import type { NodeFieldValue, NodeFileMeta, NodeFileMetaEditor, NodeFiles } from '@/Components/Admin/ContentNodes/types';
import {
    PublicDisplaySettingsSection,
    toPublicSettings,
    type PublicDisplayOptions,
    type PublicDisplayValue,
} from '@/Components/Admin/ContentTypes/PublicDisplaySettingsSection';
import type { TypeField } from '@/Components/Admin/ContentTypes/TypeValuesPanels';
import type { UploadedFile } from '@/Components/Admin/FileUpload/FileUpload';
import { FormActionBar } from '@/Components/Admin/FormActionBar';
import { ClearTranslationButton } from '@/Components/Admin/Translation/ClearTranslationButton';
import { LanguageSwitcher } from '@/Components/Admin/Translation/LanguageSwitcher';
import { useTranslation } from '@/hooks/useTranslation';
import type { TranslationState } from '@/types/translation';

import type { TypeSection } from './section';

type Props = {
    section: TypeSection;
    /** Key of the type being edited. */
    typeKey: string;
    /** Fields of the "Section" panel (kind `content` only; empty for `page` and `block`). */
    fields: TypeField[];
    /** Stored base-language values by field key. */
    values: Record<string, unknown>;
    /** Names, previews and URLs of the stored files by `{uuid}.{ext}` value. */
    files?: NodeFiles;
    /** What the "Public display" section offers and its current state. */
    publicOptions: PublicDisplayOptions;
    /** The language switcher state (base mode); `null` or absent when the tab has nothing to translate or no switcher is shown. */
    translation?: TranslationState | null;
};

/**
 * The form of the "Display" tab: the "Section" values of the type itself (kind `content` only, base language; the language switcher stands above the form when the tab has one; with `translation.lang` the same form is the translation mode: the values show that language, the "Public display" section keeps its place inert and dimmed, "Save" sends `panel` and `values` to `PUT {basePath}/{key}/values/translations/{lang}`, and "Clear translation" joins the bar) and the
 * "Public display" settings (`settings.public`, every kind), saved together by one button with
 * `PUT {basePath}/{key}/display`; the server saves both or neither. Errors of the values stand next to their field
 * (`values.{key}`), those of `settings.public` below the section, as in the settings form. The sticky action bar is
 * the last child of the form.
 *
 * @example
 * <TypeDisplayForm section={section} typeKey="news" fields={typeFields} values={typeValues} files={typeFiles} publicOptions={publicOptions} />
 */
export function TypeDisplayForm({ section, typeKey, fields, values, files = {}, publicOptions, translation = null }: Props) {
    const { t } = useTranslation();
    const lang = translation?.lang ?? null;
    const translating = lang !== null;
    const { data, setData, put, transform, processing, errors, isDirty } = useForm<{
        values: Record<string, string>;
        settings: { public: PublicDisplayValue };
        file_meta: Record<string, NodeFileMeta>;
    }>({
        // In the translation mode the server hands over the prefilled values of that language (the translation, else the base).
        values: Object.fromEntries(fields.map((field) => { const source = translating ? translation?.values : values; return [field.key, source?.[field.key] == null ? '' : String(source[field.key])]; })),
        settings: { public: publicOptions.current },
        // The names and descriptions of the files: prefilled for the language in the translation mode, not sent in the base mode.
        file_meta: initialFileMeta(files),
    });
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
    // The model's errors come under `settings` and `settings.public.*`; the per-page one is shown next to its field.
    const publicErrors = Object.entries(errors as Record<string, string>)
        .filter(([key]) => key === 'settings' || (key.startsWith('settings.public') && key !== 'settings.public.per_page'))
        .map(([, message]) => message);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        // A translation sends the panel and its values only; the public display settings are not translated.
        transform((form) => (translating ? { panel: 'section', values: form.values, file_meta: translationFileMeta(fields, form.values, form.file_meta) } : { ...form, file_meta: undefined, settings: { public: toPublicSettings(section.kind, form.settings.public) } }));
        put(translating ? translationUrl : `${section.basePath}/${typeKey}/display`, { preserveScroll: true });
    };

    const translationUrl = `${section.basePath}/${typeKey}/values/translations/${encodeURIComponent(lang ?? '')}`;
    /** In the translation mode what is not translated keeps its place, shows the base value and is out of reach. */
    const lock = (node: ReactNode) =>
        translating ? (
            <div inert aria-disabled="true" className="select-none opacity-60" data-testid="public-display-locked">
                {node}
            </div>
        ) : (
            node
        );

    return (
        <>
        {translation && <LanguageSwitcher translation={translation} editUrl={`${section.basePath}/${typeKey}/display`} dirty={isDirty} />}
        <form onSubmit={submit} noValidate data-testid="type-display-form">
            {fields.length > 0 && (
                <div className="mb-6 max-w-2xl">
                    <NodeFieldsPanel name="section" title={t('Section')} description={t('The heading, text and cover of the section of this type on the site.')}>
                        {fields.map((field) => (
                            <NodeFieldFrame
                                key={field.key}
                                field={field}
                                typeKey={typeKey}
                                value={data.values[field.key]}
                                onChange={(next: NodeFieldValue) => setData('values', { ...data.values, [field.key]: typeof next === 'string' ? next : '' })}
                                files={known}
                                onFile={rememberFile}
                                fileMeta={fileMeta}
                                error={error}
                            />
                        ))}
                    </NodeFieldsPanel>
                </div>
            )}
            {lock(
                <PublicDisplaySettingsSection
                    kind={section.kind}
                    options={publicOptions}
                    value={data.settings.public}
                    onChange={(publicValue) => setData('settings', { public: publicValue })}
                    errors={publicErrors}
                    perPageError={error('settings.public.per_page')}
                />,
            )}
            <FormActionBar cancelHref={section.basePath} processing={processing} saveDisabled={translating && !isDirty}>
                {translating && translation?.exists && <ClearTranslationButton clearUrl={`${translationUrl}?panel=section`} />}
            </FormActionBar>
        </form>
        </>
    );
}
