import { Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

import { FieldGroup } from '@/Components/Admin/FieldGroup';
import { FormActionBar } from '@/Components/Admin/FormActionBar';
import { LanguageSwitcher } from '@/Components/Admin/Translation/LanguageSwitcher';
import { ClearTranslationButton } from '@/Components/Admin/Translation/ClearTranslationButton';
import { RefAutocomplete, RefLabelsContext } from '@/Components/Admin/ContentNodes/RefAutocomplete';
import { sectionLabels, type TypeSection } from '@/Components/Admin/ContentTypes/section';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';
import { FormSection } from '@/Components/Admin/FormSection';
import type { TranslationState } from '@/types/translation';

/** Values of a content field the form edits. */
export type ContentFieldValues = {
    key: string;
    name: string;
    /** Instruction for the node editor, shown under the field's label; `null` or absent for none. */
    description?: string | null;
    type: string;
    is_multiple: boolean;
    /** Upper bound of the elements of a multiple field; `0` or absent means no limit. */
    max_items?: number;
    is_required: boolean;
    is_translatable: boolean;
    /** The public page does not print the field name as a label; the admin node form still does. */
    hide_label?: boolean;
    weight: number;
    /** A system field keeps its type, multiplicity and requiredness. */
    is_system: boolean;
    /** A field with stored values keeps its type. */
    has_values: boolean;
    /**
     * Narrowing of the upload profile (`image`, `file` types), the vocabulary of a `term` field, the kind of a `type_ref`
     * field or the content type of a `node_ref` field.
     */
    settings?: (FileSettings & { vocabulary?: string; kind?: string; content_type?: string }) | null;
};

/** A vocabulary a `term` field can point at. */
export type VocabularyOption = { key: string; name: string };

/** A content type a `node_ref` field can point at, as the form's type search lists it. */
export type ContentTypeOption = { key: string; name: string; kind: string };

/**
 * Settings of a file-bearing field; all are optional, without them the whole profile applies.
 * `max_width` and `max_height` (pixels) belong to image fields: a larger image is scaled down on upload.
 */
export type FileSettings = { mimes?: string[]; max_size?: number; max_width?: number; max_height?: number };

/**
 * The whole profile of a file-bearing type: what the field may narrow. `labels` maps each MIME to its short
 * extension (`docx`) shown instead of the long MIME; the form values stay full MIME types.
 */
export type FileTypeProfile = {
    mimes: string[];
    labels: Record<string, string>;
    max_size: number;
    /** Pixel limits of the profile; `null` when the profile does not restrict that side (always `null` without images). */
    max_width: number | null;
    max_height: number | null;
};

type Props = {
    /** `create` posts a new field; `edit` updates `field` and shows its key read-only. */
    mode: 'create' | 'edit';
    /** Section of the owning type; gives the address to submit to. */
    section: TypeSection;
    /** Key of the content type that owns the field. */
    typeKey: string;
    /** Keys of the registered value types. */
    typeOptions: string[];
    /** Profile rules by file-bearing type key (`image`, `file`); only these types show the file settings. */
    fileTypes: Record<string, FileTypeProfile>;
    /** Vocabularies offered by the `term` type, by name. */
    vocabularies: VocabularyOption[];
    /** Kinds of content types a `type_ref` field can point at (`content`, `page`, `block`). */
    kinds: string[];
    /** Every content type, for the type search of a `node_ref` field. */
    contentTypes: ContentTypeOption[];
    /** The field being edited (required in `edit` mode). */
    field?: ContentFieldValues;
    /** Weight preset for a new field (`create` mode). */
    defaultWeight?: number;
    /**
     * The translation-mode state of the edit page (`docs/admin-translation-mode.md`); `null` or absent when no switcher
     * is shown (and always in `create`). With `translation.lang` set the same form is the label translation: only the name is editable (up to 255 characters, an empty or unchanged label is not stored); every other control keeps its place, shown inert and dimmed with its base value, and "Save" sends the label only.
     */
    translation?: TranslationState<{ name: string }> | null;
};

/**
 * Shared form of the create and edit pages: key, name, optional description (an instruction for the node editor), value type, the multiple / required /
 * translatable flags, the maximum number of items (shown for a multiple field only; `0` means no limit) and weight. Server validation errors are shown next to their fields. In
 * `edit` mode the key and the type are read-only (the type of a field is fixed at creation),
 * and "multiple" and "required" are locked for a system field. The lock is a convenience:
 * the server rejects those changes on its own. For the file-bearing types (`fileTypes`) the form also
 * offers the allowed file types (a subset of the profile's) and the size limit; nothing chosen means
 * the whole profile, and a field with values may be narrowed without touching them. The `term` type shows a required
 * choice of the vocabulary (locked while the field has values; with no vocabularies the form explains that one must be
 * created first and links to the Taxonomy section). The `type_ref` type shows a required choice of the kind of content types
 * (`kinds`), and `node_ref` a required, searchable choice of the content type of the nodes (`contentTypes`, found by name or
 * key); both are locked while the field has values.
 *
 * @example
 * <ContentFieldForm mode="create" section={section} typeKey="news" typeOptions={['string', 'image', 'term']} fileTypes={fileTypes} vocabularies={vocabularies} kinds={kinds} contentTypes={contentTypes} defaultWeight={21} />
 * <ContentFieldForm mode="edit" section={section} typeKey="news" typeOptions={typeOptions} fileTypes={fileTypes} vocabularies={vocabularies} kinds={kinds} contentTypes={contentTypes} field={field} />
 */
export function ContentFieldForm({ mode, section, typeKey, typeOptions, fileTypes, vocabularies, kinds, contentTypes, field, defaultWeight = 0, translation = null }: Props) {
    const { t } = useTranslation();
    const editing = mode === 'edit';
    const lang = editing ? (translation?.lang ?? null) : null;
    const translating = lang !== null;
    const lockType = !!field;
    const lockFlags = !!field?.is_system;
    const { data, setData, post, put, processing, errors, transform, isDirty } = useForm({
        key: field?.key ?? '',
        name: field?.name ?? '',
        description: field?.description ?? '',
        type: field?.type ?? typeOptions[0] ?? '',
        is_multiple: field?.is_multiple ?? false,
        max_items: String(field?.max_items ?? 0),
        is_required: field?.is_required ?? false,
        is_translatable: field?.is_translatable ?? false,
        hide_label: field?.hide_label ?? false,
        weight: String(field?.weight ?? defaultWeight),
        // The label of the language being translated, prefilled by the server (translation mode only).
        text: { name: translation?.values?.name ?? field?.name ?? '' },
        settings: {
            mimes: field?.settings?.mimes ?? ([] as string[]),
            max_size: field?.settings?.max_size ? String(field.settings.max_size) : '',
            max_width: field?.settings?.max_width ? String(field.settings.max_width) : '',
            max_height: field?.settings?.max_height ? String(field.settings.max_height) : '',
            vocabulary: field?.settings?.vocabulary ?? '',
            kind: field?.settings?.kind ?? '',
            content_type: field?.settings?.content_type ?? '',
        },
    });
    const profile = fileTypes[data.type];
    // Pixel limits are offered only when the profile holds images (`image`, not `file`).
    const hasImages = !!profile && profile.mimes.some((mime) => mime.startsWith('image/'));
    // Names of the content types by key: the label of the chosen type of a `node_ref` field.
    const typeLabels = Object.fromEntries(contentTypes.map((type) => [type.key, type.name]));

    // Only a file-bearing type sends its file settings, `term` its vocabulary, `type_ref` its kind and `node_ref` its
    // content type; the server refuses settings for any other type.
    transform((values) => {
        // A translation sends its label only; every other attribute stays out of it.
        if (translating) {
            return { name: values.text.name };
        }

        const { text: _text, settings, ...rest } = values;
        const { vocabulary, kind, content_type: contentType, max_width: maxWidth, max_height: maxHeight, ...fileSettings } = settings;

        if (values.type === 'term') {
            return { ...rest, settings: { vocabulary } };
        }

        if (values.type === 'type_ref') {
            return { ...rest, settings: { kind } };
        }

        if (values.type === 'node_ref') {
            return { ...rest, settings: { content_type: contentType } };
        }

        // A profile without images takes no pixel limits: the server refuses them for such a type.
        const profileHasImages = fileTypes[values.type]?.mimes.some((mime) => mime.startsWith('image/'));

        return fileTypes[values.type] ? { ...rest, settings: profileHasImages ? { ...fileSettings, max_width: maxWidth, max_height: maxHeight } : fileSettings } : rest;
    });
    const base = `${section.basePath}/${typeKey}/fields`;

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (translating) {
            put(`${base}/${field?.key}/translations/${encodeURIComponent(lang)}`);
        } else if (editing) {
            put(`${base}/${field?.key}`);
        } else {
            post(base);
        }
    };

    /** In the translation mode what is not translated keeps its place, shows the base value and is out of reach. */
    const lock = (node: ReactNode) =>
        translating ? (
            <div inert aria-disabled="true" className="select-none opacity-60">
                {node}
            </div>
        ) : (
            node
        );

    const error = (name: keyof typeof errors) =>
        errors[name] && (
            <p role="alert" className="text-sm text-destructive">
                {errors[name]}
            </p>
        );

    // `settings.mimes.0` and the like belong next to the type list as well.
    const mimesError = Object.entries(errors).find(([key]) => key === 'settings.mimes' || key.startsWith('settings.mimes.'))?.[1];

    const toggleMime = (mime: string, checked: boolean) =>
        setData('settings', {
            ...data.settings,
            mimes: checked ? [...data.settings.mimes, mime] : data.settings.mimes.filter((item) => item !== mime),
        });

    const flag = (name: 'is_multiple' | 'is_required' | 'is_translatable' | 'hide_label', label: string, locked = false, hint?: string) => (
        <div className="space-y-1">
            <div className="flex items-center gap-2">
                <Checkbox
                    id={name}
                    checked={data[name]}
                    disabled={locked}
                    aria-invalid={!!errors[name]}
                    onCheckedChange={(checked) => {
                        setData(name, checked === true);

                        // A single field has no limit: switching "multiple" off drops it.
                        if (name === 'is_multiple' && checked !== true) {
                            setData('max_items', '0');
                        }
                    }}
                />
                <Label htmlFor={name}>{t(label)}</Label>
            </div>
            {hint && <p className="text-sm text-muted-foreground">{t(hint)}</p>}
            {error(name)}
        </div>
    );

    return (
        <>
        {editing && field && translation && <LanguageSwitcher translation={translation} editUrl={`${base}/${field.key}/edit`} dirty={isDirty} />}
        <form onSubmit={submit} noValidate>
            <FormSection className="mb-6 max-w-xl space-y-4">
                {lock(
                <div className="space-y-2">
                    <Label htmlFor="key">{t('Key')}</Label>
                    <Input id="key" value={data.key} readOnly={editing} aria-invalid={!!errors.key} onChange={(e) => setData('key', e.target.value)} />
                    <p className="text-sm text-muted-foreground">
                        {editing
                            ? t('The key cannot be changed.')
                            : t('Lowercase Latin letters, digits and underscores, starting with a letter, up to 64 characters. It cannot be changed later.')}
                    </p>
                    {error('key')}
                </div>
                )}

                <div className="space-y-2">
                    <Label htmlFor="name">{t('Name')}</Label>
                    <Input
                        id="name"
                        value={translating ? data.text.name : data.name}
                        maxLength={translating ? 255 : undefined}
                        aria-invalid={!!errors.name}
                        onChange={(e) => (translating ? setData('text', { name: e.target.value }) : setData('name', e.target.value))}
                    />
                    {error('name')}
                </div>

                {lock(
                    <div className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="description">{t('Description')}</Label>
                        <Textarea
                            id="description"
                            rows={3}
                            maxLength={1000}
                            value={data.description}
                            aria-invalid={!!errors.description}
                            onChange={(e) => setData('description', e.target.value)}
                        />
                        <p className="text-sm text-muted-foreground">{t('Optional. Shown under the field’s name in the node form, up to 1000 characters.')}</p>
                        {error('description')}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="type">{t('Value type')}</Label>
                        <Select value={data.type} disabled={lockType} onValueChange={(value) => setData('type', value)}>
                            <SelectTrigger id="type" className="w-full" aria-invalid={!!errors.type}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {typeOptions.map((option) => (
                                    <SelectItem key={option} value={option}>
                                        {option}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {lockType && <p className="text-sm text-muted-foreground">{t('The type of a field cannot be changed.')}</p>}
                        {error('type')}
                    </div>

                    {data.type === 'term' && (
                        <div className="space-y-2" data-testid="vocabulary-setting">
                            <Label htmlFor="vocabulary">{t('Vocabulary')}</Label>
                            {vocabularies.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('There are no vocabularies yet. Create one first in the section:')}{' '}
                                    <Link href="/admin/taxonomy" className="text-link underline underline-offset-4">
                                        {t('Taxonomy')}
                                    </Link>
                                </p>
                            ) : (
                                <Select value={data.settings.vocabulary} disabled={!!field?.has_values} onValueChange={(value) => setData('settings', { ...data.settings, vocabulary: value })}>
                                    <SelectTrigger id="vocabulary" className="w-full" aria-invalid={!!errors['settings.vocabulary']}>
                                        <SelectValue placeholder={t('Choose a vocabulary')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {vocabularies.map((vocabulary) => (
                                            <SelectItem key={vocabulary.key} value={vocabulary.key}>
                                                {vocabulary.name} ({vocabulary.key})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                            {field?.has_values && <p className="text-sm text-muted-foreground">{t('The vocabulary of a field that has values cannot be changed.')}</p>}
                            {error('settings.vocabulary')}
                        </div>
                    )}

                    {data.type === 'type_ref' && (
                        <div className="space-y-2" data-testid="kind-setting">
                            <Label htmlFor="kind">{t('Kind of content types')}</Label>
                            <Select value={data.settings.kind} disabled={!!field?.has_values} onValueChange={(value) => setData('settings', { ...data.settings, kind: value })}>
                                <SelectTrigger id="kind" className="w-full" aria-invalid={!!errors['settings.kind']}>
                                    <SelectValue placeholder={t('Choose a kind')} />
                                </SelectTrigger>
                                <SelectContent>
                                    {kinds.map((kind) => (
                                        <SelectItem key={kind} value={kind}>
                                            {t(sectionLabels(kind as TypeSection['kind']).list)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {field?.has_values && <p className="text-sm text-muted-foreground">{t('The kind of a field that has values cannot be changed.')}</p>}
                            {error('settings.kind')}
                        </div>
                    )}

                    {data.type === 'node_ref' && (
                        <div className="space-y-2" data-testid="content-type-setting">
                            <Label htmlFor="content_type">{t('Content type of the nodes')}</Label>
                            {field?.has_values ? (
                                <p className="text-sm">
                                    {typeLabels[data.settings.content_type] ?? data.settings.content_type} ({data.settings.content_type})
                                </p>
                            ) : (
                                <RefLabelsContext.Provider value={typeLabels}>
                                    <RefAutocomplete
                                        id="content_type"
                                        value={data.settings.content_type}
                                        invalid={!!errors['settings.content_type']}
                                        placeholder={t('Choose a content type')}
                                        clearable={false}
                                        search={async (query) => {
                                            const needle = query.trim().toLowerCase();

                                            return contentTypes
                                                .filter((type) => needle === '' || type.name.toLowerCase().includes(needle) || type.key.includes(needle))
                                                .map((type) => ({ value: type.key, label: type.name, hint: type.key }));
                                        }}
                                        onChange={(value) => setData('settings', { ...data.settings, content_type: value })}
                                    />
                                </RefLabelsContext.Provider>
                            )}
                            {field?.has_values && <p className="text-sm text-muted-foreground">{t('The content type of a field that has values cannot be changed.')}</p>}
                            {error('settings.content_type')}
                        </div>
                    )}

                    {profile && (
                        <FieldGroup legend={t('File settings')} className="space-y-4" data-testid="file-settings">

                            <div className="space-y-2">
                                <Label>{t('Allowed file types')}</Label>
                                <div className="space-y-2" role="group" aria-label={t('Allowed file types')}>
                                    {profile.mimes.map((mime) => (
                                        <div key={mime} className="flex items-center gap-2">
                                            <Checkbox
                                                id={`mime-${mime.replace(/[^a-z0-9]/gi, '-')}`}
                                                checked={data.settings.mimes.includes(mime)}
                                                onCheckedChange={(checked) => toggleMime(mime, checked === true)}
                                            />
                                            <Label htmlFor={`mime-${mime.replace(/[^a-z0-9]/gi, '-')}`} className="font-normal">
                                                {profile.labels[mime] ?? mime}
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                                <p className="text-sm text-muted-foreground">{t('Leave all unchecked to allow every type of the profile.')}</p>
                                {mimesError && (
                                    <p role="alert" className="text-sm text-destructive">
                                        {mimesError}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="max_size">{t('Maximum size (KB)')}</Label>
                                <Input
                                    id="max_size"
                                    type="number"
                                    min={1}
                                    max={profile.max_size}
                                    value={data.settings.max_size}
                                    aria-invalid={!!errors['settings.max_size']}
                                    onChange={(e) =>
                                        setData('settings', {
                                            ...data.settings,
                                            max_size: e.target.value,
                                        })
                                    }
                                />
                                <p className="text-sm text-muted-foreground">
                                    {t('Leave empty to use the limit of the profile: :max KB.', { max: String(profile.max_size) })}
                                </p>
                                {error('settings.max_size')}
                            </div>

                            {hasImages && (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {(['max_width', 'max_height'] as const).map((side) => (
                                        <div key={side} className="space-y-2">
                                            <Label htmlFor={side}>{side === 'max_width' ? t('Maximum width (px)') : t('Maximum height (px)')}</Label>
                                            <Input
                                                id={side}
                                                type="number"
                                                min={1}
                                                max={profile[side] ?? undefined}
                                                value={data.settings[side]}
                                                aria-invalid={!!errors[`settings.${side}`]}
                                                onChange={(e) =>
                                                    setData('settings', {
                                                        ...data.settings,
                                                        [side]: e.target.value,
                                                    })
                                                }
                                            />
                                            <p className="text-sm text-muted-foreground">
                                                {profile[side] === null
                                                    ? t('Leave empty for no limit. Larger images are scaled down on upload.')
                                                    : t('Leave empty to use the limit of the profile: :max px.', { max: String(profile[side]) })}
                                            </p>
                                            {error(`settings.${side}`)}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </FieldGroup>
                    )}

                    <div className="space-y-3">
                        {flag('is_multiple', 'Multiple', lockFlags)}
                        {flag('is_required', 'Required', lockFlags)}
                        {flag('is_translatable', 'Translatable')}
                        {flag('hide_label', 'Hide label', false, 'Only affects the public page: the field name is not printed above its value.')}
                    </div>

                    {data.is_multiple && (
                        <div className="space-y-2">
                            <Label htmlFor="max_items">{t('Maximum number of items')}</Label>
                            <Input
                                id="max_items"
                                type="number"
                                min={0}
                                max={65535}
                                value={data.max_items}
                                aria-invalid={!!errors.max_items}
                                onChange={(e) => setData('max_items', e.target.value)}
                            />
                            <p className="text-sm text-muted-foreground">{t('0 means no limit.')}</p>
                            {error('max_items')}
                        </div>
                    )}

                    <div className="space-y-2">
                        <Label htmlFor="weight">{t('Weight')}</Label>
                        <Input
                            id="weight"
                            type="number"
                            min={0}
                            max={65535}
                            value={data.weight}
                            aria-invalid={!!errors.weight}
                            onChange={(e) => setData('weight', e.target.value)}
                        />
                        <p className="text-sm text-muted-foreground">
                            {t('Fields are ordered by weight. It is easier to change the order by dragging rows in the field list.')}
                        </p>
                        {error('weight')}
                    </div>
                    </div>
                )}
            </FormSection>
            <FormActionBar cancelHref={base} processing={processing} saveDisabled={translating && !isDirty}>
                {translating && translation?.exists && <ClearTranslationButton clearUrl={`${base}/${field?.key}/translations/${encodeURIComponent(lang)}`} />}
            </FormActionBar>
        </form>
        </>
    );
}
