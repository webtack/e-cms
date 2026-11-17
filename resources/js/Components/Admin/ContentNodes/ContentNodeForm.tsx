import { useForm } from '@inertiajs/react';
import { Eye, SlidersHorizontal } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';

import { FormActionBar } from '@/Components/Admin/FormActionBar';
import type { TypeSection } from '@/Components/Admin/ContentTypes/section';
import type { UploadedFile } from '@/Components/Admin/FileUpload/FileUpload';
import { ClearTranslationButton } from '@/Components/Admin/Translation/ClearTranslationButton';
import { LanguageSwitcher } from '@/Components/Admin/Translation/LanguageSwitcher';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/Components/ui/accordion';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle, SheetTrigger } from '@/Components/ui/sheet';
import { Textarea } from '@/Components/ui/textarea';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { useTranslation } from '@/hooks/useTranslation';
import type { TranslationState } from '@/types/translation';

import { initialFileMeta, initialValue, translationFileMeta } from './fieldValues';
import { NodeFieldFrame } from './NodeFieldFrame';
import { NodeServicePanel } from './NodeServicePanel';
import { RefLabelsContext } from './RefAutocomplete';
import { formatNow, nodeStatus } from './status';
import type { NodeField, NodeFieldValue, NodeFileMeta, NodeFileMetaEditor, NodeFiles, NodeMeta, NodeRefLabels, NodeTranslationValues, NodeValues } from './types';

type Props = {
    /** `create` posts a new node; `edit` updates `node`. */
    mode: 'create' | 'edit';
    section: TypeSection;
    /** Key of the content type that owns the node. */
    typeKey: string;
    /** The fields of the node in the order of their weight; those with `panel: 'seo'` are shown in the "SEO" section of the side column. */
    fields: NodeField[];
    /** Service attributes of the node (see `NodeMeta`); in `edit` mode it also carries the `uuid` the node is addressed by. */
    node: NodeMeta;
    /** Stored values by field key (`edit` mode), in the format of the HTML inputs. */
    values?: NodeValues;
    /** Names, previews and URLs of the files in the stored values, by `{uuid}.{ext}` (`edit` mode). */
    files?: NodeFiles;
    /** Labels of the targets in the stored values of `type_ref` / `node_ref` fields by stored value (`edit` mode), shown before any search. */
    labels?: NodeRefLabels;
    /** Fixed prefix of the slug (`block-` for blocks, `''` otherwise): shown apart from the input and added when sending. */
    slugPrefix?: string;
    /** Public address of the saved node (`edit` mode): the "Preview" button opens it in a new tab; no button when `null` or absent. */
    publicUrl?: string | null;
    /**
     * The translation-mode state of the edit page (`docs/admin-translation-mode.md`); `null` or absent when no
     * switcher is shown (and always in `create`). With `translation.lang` set the form is the translation mode.
     */
    translation?: TranslationState<NodeTranslationValues> | null;
};

/**
 * Shared form of the create and edit pages of a node: the slug and one input per field of the type, in order of
 * weight, built from data (`fields`) and not hard-coded. Every field sits in its own frame (`NodeFieldFrame`); the
 * metadata fields (`panel: 'seo'`) leave the main column for the side column, where an accordion holds the "Details"
 * section (open at first) and, when the type has such fields, the "SEO" section (closed at first); one section is open
 * at a time, each in its own border inside the shared one. A server error in a `values.meta_*` field opens "SEO" and
 * closes "Details". The accordion is rendered once: in the side column from `lg`, in the bottom sheet
 * below it. Required fields are marked. The form sends
 * `{ slug, values: { <field key>: <value | list> } }`; the values sit under their own key because a field key may
 * equal `slug`. Server errors come back keyed like the state (`slug`, `values.title`, `values.scores.1`) and are shown
 * next to their field or list item (a multiple field with a limit shows an "n / max" counter by its name); what was typed stays in the form. This form edits the base
 * language; when the edit page has a language switcher it is shown above the form. With `translation.lang` the same
 * layout is the translation mode: the fields without translations and the "Details" section are shown disabled with their
 * base values, nothing is required, "Save" stays disabled until a translatable value changes and sends only those values to
 * `PUT {basePath}/{key}/nodes/{uuid}/translations/{lang}`; "Clear translation" (when a translation exists) joins the action bar. The `image` and `file` fields upload at once (see `FileUpload`) and keep only `{uuid}.{ext}` in
 * the form, and under each stored file the form edits the file's name and description (sent as `file_meta` by file uuid, only
 * for files still in the values); the files known to the form (`files` and the ones uploaded since) tell the widgets what to show.
 *
 * @example
 * <ContentNodeForm mode="create" section={section} typeKey="news" fields={fields} />
 * <ContentNodeForm mode="edit" section={section} typeKey="news" fields={fields} node={node} values={values} files={files} publicUrl="/news/hello" />
 *
 * @example
 * // A block: the form state keeps `hero`, the request carries `block-hero`; an empty slug stays empty (generated on the server).
 * <ContentNodeForm mode="create" section={section} typeKey="banner" fields={fields} node={node} slugPrefix="block-" />
 */
export function ContentNodeForm({ mode, section, typeKey, fields, node, values, files = {}, labels = {}, slugPrefix = '', publicUrl = null, translation = null }: Props) {
    const { t } = useTranslation();
    const editing = mode === 'edit';
    const lang = editing ? (translation?.lang ?? null) : null;
    const translating = lang !== null;
    // The metadata fields leave the main list for their own panel; the rest keep the order of their weight.
    // A translation never requires a field, so the asterisk and the "(required)" hint are not drawn in the translation mode.
    const shownFields = translating ? fields.map((field) => ({ ...field, is_required: false })) : fields;
    const mainFields = shownFields.filter((field) => field.panel !== 'seo');
    const seoFields = shownFields.filter((field) => field.panel === 'seo');
    const { data, setData, post, put, processing, errors, transform, isDirty } = useForm<{
        slug: string;
        is_active: boolean;
        published_at: string;
        unpublished_at: string;
        values: Record<string, NodeFieldValue>;
        file_meta: Record<string, NodeFileMeta>;
    }>({
        slug: node.slug.startsWith(slugPrefix) ? node.slug.slice(slugPrefix.length) : node.slug,
        is_active: node.is_active,
        published_at: node.published_at || formatNow(),
        unpublished_at: node.unpublished_at ?? '',
        values: Object.fromEntries(fields.map((field) => [field.key, initialValue(field, values?.[field.key])])),
        file_meta: initialFileMeta(files),
    });

    // Only the files that are still in the values travel with the form: a replaced or removed file keeps its metadata out of the request.
    transform((form) => {
        const used = new Set(
            fields
                .filter((field) => field.type === 'image' || field.type === 'file')
                .flatMap((field) => [form.values[field.key]].flat())
                .filter((value): value is string => typeof value === 'string' && value !== '')
                .map((value) => value.slice(0, 32)),
        );

        if (translating) {
            // Only the translatable values and the names and descriptions of the files travel (any image or file field);
            // the slug, status and dates are not part of a translation.
            return {
                values: Object.fromEntries(fields.filter((field) => field.translatable).map((field) => [field.key, form.values[field.key]])),
                file_meta: translationFileMeta(fields, form.values, form.file_meta),
            };
        }

        return { ...form, slug: form.slug === '' ? '' : slugPrefix + form.slug, file_meta: Object.fromEntries(Object.entries(form.file_meta).filter(([uuid]) => used.has(uuid))) };
    });
    const isDesktop = useMediaQuery('(min-width: 1024px)');
    const [panelOpen, setPanelOpen] = useState(false);
    const [known, setKnown] = useState<NodeFiles>(files);
    const base = `${section.basePath}/${typeKey}/nodes`;
    const rememberFile = (file: UploadedFile) => {
        setKnown((current) => ({ ...current, [file.value]: { uuid: file.uuid, name: file.name, mime: file.mime, size: file.size, url: file.url, preview: file.preview } }));
        setData((current) => ({ ...current, file_meta: { ...current.file_meta, [file.uuid]: { name: file.name, description: '' } } }));
    };
    const error = (key: string) => (errors as Record<string, string | undefined>)[key];

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (translating) {
            put(`${base}/${node.uuid}/translations/${encodeURIComponent(lang)}`);
        } else if (editing) {
            put(`${base}/${node.uuid}`);
        } else {
            post(base);
        }
    };

    const fileMeta: NodeFileMetaEditor = {
        values: data.file_meta,
        onChange: (uuid, patch) =>
            setData((current) => ({ ...current, file_meta: { ...current.file_meta, [uuid]: { ...current.file_meta[uuid], ...patch } } })),
        errorAt: (uuid, key) => error(`file_meta.${uuid}.${key}`),
    };

    const setValue = (key: string, value: NodeFieldValue) => setData('values', { ...data.values, [key]: value });

    const renderField = (field: NodeField) => {
        const frame = (
            <NodeFieldFrame
                key={field.key}
                field={field}
                typeKey={typeKey}
                value={data.values[field.key]}
                onChange={(next) => setValue(field.key, next)}
                files={known}
                onFile={rememberFile}
                fileMeta={fileMeta}
                error={error}
            />
        );

        if (!translating || field.translatable !== false) {
            return frame;
        }

        // In the translation mode a field without translations keeps its place and shows the base value, out of reach.
        // The name and description of its files are the exception: a file has them in every language.
        const stored = field.type === 'image' || field.type === 'file' ? [data.values[field.key]].flat().filter((value): value is string => typeof value === 'string' && value !== '') : [];

        return (
            <div key={field.key} className="space-y-3">
                <div inert aria-disabled="true" className="select-none opacity-60" data-testid={`field-locked-${field.key}`}>
                    {frame}
                </div>
                {stored.map((value) => {
                    const uuid = value.slice(0, 32);
                    const message = error(`file_meta.${uuid}.description`);

                    return (
                        <div key={uuid} className="space-y-1" data-testid={`file-description-${uuid}`}>
                            <Label htmlFor={`file-name-${uuid}`}>{t('Name of :name', { name: known[value]?.name ?? value })}</Label>
                            <Input
                                id={`file-name-${uuid}`}
                                value={data.file_meta[uuid]?.name ?? ''}
                                maxLength={255}
                                aria-invalid={!!error(`file_meta.${uuid}.name`) || undefined}
                                onChange={(e) => fileMeta.onChange(uuid, { name: e.target.value })}
                            />
                            <Label htmlFor={`file-description-${uuid}`}>{t('Description of :name', { name: known[value]?.name ?? value })}</Label>
                            <Textarea
                                id={`file-description-${uuid}`}
                                rows={2}
                                value={data.file_meta[uuid]?.description ?? ''}
                                aria-invalid={!!message || undefined}
                                onChange={(e) => fileMeta.onChange(uuid, { description: e.target.value })}
                            />
                            {message && (
                                <p role="alert" className="text-sm text-destructive">
                                    {message}
                                </p>
                            )}
                        </div>
                    );
                })}
            </div>
        );
    };

    const serviceErrors = errors as Record<string, string | undefined>;
    const hasSeoError = seoFields.some((field) => Object.keys(serviceErrors).some((key) => key === `values.${field.key}` || key.startsWith(`values.${field.key}.`)));
    const hasServiceError = !!(errors.slug || errors.published_at || errors.unpublished_at || errors.is_active) || hasSeoError;
    // The open section of the side column (`''` for none); the state lives here so that it survives closing the sheet.
    const [openSection, setOpenSection] = useState<string>('details');

    // A validation error in "SEO" opens it (and so closes "Details"), so that the error is visible.
    useEffect(() => {
        if (hasSeoError) {
            setOpenSection('seo');
        }
    }, [hasSeoError, errors]);

    const status = nodeStatus(data.is_active, data.published_at, data.unpublished_at);
    const panel = (
        <Accordion type="single" collapsible value={openSection} onValueChange={setOpenSection} className="rounded-md border p-3 bg-card">
            <AccordionItem value="details">
                <AccordionTrigger>{t('Details')}</AccordionTrigger>
                <AccordionContent>
                    <div inert={translating} aria-disabled={translating || undefined} className={translating ? 'select-none opacity-60' : undefined} data-testid={translating ? 'details-locked' : undefined}>
                        <NodeServicePanel
                            data={data}
                            setData={(key, value) => setData((current) => ({ ...current, [key]: value }))}
                            errors={serviceErrors}
                            slugPrefix={slugPrefix}
                            meta={node}
                            editing={editing}
                        />
                    </div>
                </AccordionContent>
            </AccordionItem>
            {seoFields.length > 0 && (
                <AccordionItem value="seo">
                    <AccordionTrigger>{t('SEO')}</AccordionTrigger>
                    <AccordionContent>
                        <div data-testid="seo-panel" className="min-w-0 space-y-4">
                            <p className="text-sm text-muted-foreground">{t('Shown to search engines and when the page is shared.')}</p>
                            {seoFields.map(renderField)}
                        </div>
                    </AccordionContent>
                </AccordionItem>
            )}
        </Accordion>
    );

    return (
        <RefLabelsContext.Provider value={labels}>
        {editing && translation && <LanguageSwitcher translation={translation} editUrl={`${base}/${node.uuid}/edit`} dirty={isDirty} />}
        <form onSubmit={submit} noValidate data-testid={translating ? `node-translation-${lang}` : undefined}>
            <div className="grid gap-6 lg:grid-cols-[minmax(0,7fr)_minmax(0,3fr)]">
                <div className="min-w-0 space-y-4 bg-card border p-3 rounded-md">
                    {mainFields.map(renderField)}
                </div>

                {/* One render of the accordion: the side column from `lg`, the sheet below it (fixed ids must not repeat).
                    The column scrolls on its own when both sections together are taller than the window. */}
                {isDesktop === true && <aside className="-mx-1 self-start overflow-y-auto px-1 lg:sticky lg:top-4 lg:max-h-[calc(100vh-10rem)]">{panel}</aside>}
            </div>

            <FormActionBar cancelHref={base} processing={processing} saveDisabled={translating && !isDirty} className="mt-10">
                {translating && translation?.exists && <ClearTranslationButton clearUrl={`${base}/${node.uuid}/translations/${encodeURIComponent(lang)}`} />}
                <div className="flex lg:hidden">
                    <Sheet open={panelOpen && isDesktop !== true} onOpenChange={setPanelOpen}>
                        <SheetTrigger asChild>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                className="relative"
                                aria-label={t('Details')}
                                title={t('Details')}
                            >
                                <SlidersHorizontal aria-hidden />
                                {hasServiceError && (
                                    <span
                                        role="img"
                                        aria-label={t('Has errors')}
                                        data-testid="service-error-dot"
                                        className="absolute -top-1 -right-1 size-3 rounded-full bg-destructive"
                                    />
                                )}
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="bottom" className="max-h-[85vh] overflow-y-auto">
                            <SheetHeader>
                                <SheetTitle className="sr-only">{t('Details')}</SheetTitle>
                                <SheetDescription className="sr-only">{t('Visibility, publication date and slug of the node.')}</SheetDescription>
                            </SheetHeader>
                            <div className="px-4">{panel}</div>
                            <SheetFooter>
                                <Button type="button" onClick={() => setPanelOpen(false)}>
                                    {t('Done')}
                                </Button>
                            </SheetFooter>
                        </SheetContent>
                    </Sheet>
                </div>
                {editing && publicUrl !== null && (
                    <Button type="button" variant="outline" asChild>
                        <a href={publicUrl} target="_blank" rel="noopener noreferrer" aria-label={t('Preview')} title={t('Preview')}>
                            <Eye aria-hidden />
                            <span className="hidden sm:inline">{t('Preview')}</span>
                        </a>
                    </Button>
                )}
            </FormActionBar>
        </form>
        </RefLabelsContext.Provider>
    );
}
