import { useForm } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';

import { FileUpload, type UploadedFile } from '@/Components/Admin/FileUpload/FileUpload';
import { FormActionBar } from '@/Components/Admin/FormActionBar';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';
import { FormSection } from '@/Components/Admin/FormSection';
import { LanguageSwitcher } from '@/Components/Admin/Translation/LanguageSwitcher';
import { ClearTranslationButton } from '@/Components/Admin/Translation/ClearTranslationButton';
import type { TranslationState } from '@/types/translation';

/** An item that can be chosen as the parent; `depth` (a root has 1) drives the indent. */
export type ParentOption = { uuid: string; name: string | null; depth: number };

/** The translatable fields of a menu item in one language. */
export type ItemTexts = { title: string; description: string };

/** Values of a menu item the form edits. */
export type MenuItemValues = {
    uuid: string;
    weight: number;
    parent_uuid: string | null;
    url: string | null;
    target: '_self' | '_blank';
    css_class: string | null;
    image: string | null;
    is_active: boolean;
    /** `YYYY-MM-DDTHH:MM` (UTC) as a `datetime-local` input takes it. */
    published_at: string | null;
    unpublished_at: string | null;
    /** Title in the base language. */
    title: string;
    /** Description in the base language. */
    description: string;
};

/** The stored image as the server describes it (to show its name and preview). */
export type ImageFile = { uuid: string; name: string; description: string | null; mime: string; size: number; url: string; preview: string | null };

type Props = {
    /** `create` posts a new item; `edit` updates `item`. */
    mode: 'create' | 'edit';
    menuKey: string;
    parentOptions: ParentOption[];
    /** The translation-mode state of the edit page; `null` when no switcher is shown (and always in `create`). */
    translation?: TranslationState<ItemTexts> | null;
    /** The item being edited (required in `edit` mode). */
    item?: MenuItemValues;
    /** The stored image of the item being edited. */
    imageFile?: ImageFile | null;
    /** The weight prefilled in `create` mode: the new item goes last among its siblings. */
    defaultWeight?: number;
};

/** The Select cannot hold an empty value, so "no parent" travels under this placeholder. */
const NO_PARENT = '__none__';

/**
 * Form of a menu item for the create and edit pages. In `create` and in the base mode of `edit` it edits the
 * shared attributes and the title and description in the base language (address, target, CSS class, image, parent,
 * weight, the active switch with the publication window in UTC). An empty address makes the item a group heading.
 * When the edit page has a language switcher, it is shown above the form. With `translation.lang` set the same form is the
 * translation mode: the title and description show that language and are the only editable fields; every other field and
 * the activity section keep their place, shown inert and dimmed with their base values, and "Save" sends only the two
 * texts (see `docs/admin-translation-mode.md`). Server validation errors are shown next to their fields.
 *
 * @example
 * <MenuItemForm mode="create" menuKey="main" parentOptions={parentOptions} defaultWeight={0} />
 * <MenuItemForm mode="edit" menuKey="main" item={item} imageFile={imageFile} parentOptions={parentOptions} translation={translation} />
 */
export function MenuItemForm({ mode, menuKey, parentOptions, item, imageFile = null, defaultWeight = 0, translation = null }: Props) {
    const { t } = useTranslation();
    const editing = mode === 'edit';
    const lang = editing ? (translation?.lang ?? null) : null;
    const translating = lang !== null;
    const base = `/admin/menus/${menuKey}/items`;
    const [preview, setPreview] = useState<ImageFile | null>(imageFile);

    const { data, setData, post, put, processing, errors, isDirty, transform } = useForm({
        url: item?.url ?? '',
        target: item?.target ?? '_self',
        css_class: item?.css_class ?? '',
        weight: String(item?.weight ?? defaultWeight),
        parent_uuid: item?.parent_uuid ?? '',
        image: item?.image ?? '',
        is_active: item?.is_active ?? true,
        published_at: item?.published_at ?? '',
        unpublished_at: item?.unpublished_at ?? '',
        translations: { default: { title: item?.title ?? '', description: item?.description ?? '' } },
        // The title and description of the language being translated, prefilled by the server (translation mode only).
        text: { title: translation?.values?.title ?? '', description: translation?.values?.description ?? '' },
    });

    // A translation sends its two texts only; the shared attributes stay out of it.
    transform((form) => (translating ? { title: form.text.title, description: form.text.description } : { ...form, text: undefined }));

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (translating) {
            put(`${base}/${item?.uuid}/translations/${encodeURIComponent(lang)}`);
        } else if (editing) {
            put(`${base}/${item?.uuid}`);
        } else {
            post(base);
        }
    };

    const setText = (key: 'title' | 'description', value: string) =>
        translating ? setData('text', { ...data.text, [key]: value }) : setData('translations', { default: { ...data.translations.default, [key]: value } });
    const textOf = (key: 'title' | 'description') => (translating ? data.text[key] : data.translations.default[key]);
    // The same field in the translation mode has the plain id and its error comes under the plain key.
    const suffix = translating ? '' : '-default';
    const errorKey = (key: string) => (translating ? key : `translations.default.${key}`);
    /** In the translation mode what is not translated keeps its place, shows the base value and is out of reach. */
    const lock = (node: ReactNode) =>
        translating ? (
            <div inert aria-disabled="true" className="select-none opacity-60">
                {node}
            </div>
        ) : (
            node
        );

    const error = (field: string) => {
        const message = (errors as Record<string, string | undefined>)[field];

        return (
            message && (
                <p role="alert" className="text-sm text-destructive">
                    {message}
                </p>
            )
        );
    };

    const onImage = (file: UploadedFile | null) => {
        setData('image', file ? file.value : '');
        setPreview(
            file ? { uuid: file.uuid, name: file.name, description: null, mime: file.mime, size: file.size, url: file.url, preview: file.preview } : null,
        );
    };

    const selectedImage: UploadedFile | null =
        data.image === '' || preview === null
            ? null
            : {
                  uuid: preview.uuid,
                  profile: 'image',
                  name: preview.name,
                  mime: preview.mime,
                  size: preview.size,
                  width: null,
                  height: null,
                  value: data.image,
                  url: preview.url,
                  preview: preview.preview,
              };

    /** Title and description fields of the base language. */
    const textFields = (
        <div className="space-y-4" data-testid={`item-texts-${lang ?? 'default'}`}>
            <div className="space-y-2">
                <Label htmlFor={`title${suffix}`}>{t('Title')}</Label>
                <Input
                    id={`title${suffix}`}
                    value={textOf('title')}
                    aria-invalid={!!errors[errorKey('title') as keyof typeof errors]}
                    onChange={(e) => setText('title', e.target.value)}
                />
                {error(errorKey('title'))}
            </div>
            <div className="space-y-2">
                <Label htmlFor={`description${suffix}`}>{t('Description')}</Label>
                <Textarea
                    id={`description${suffix}`}
                    rows={3}
                    value={textOf('description')}
                    aria-invalid={!!errors[errorKey('description') as keyof typeof errors]}
                    onChange={(e) => setText('description', e.target.value)}
                />
                {error(errorKey('description'))}
            </div>
        </div>
    );

    return (
        <>
            {editing && translation && <LanguageSwitcher translation={translation} editUrl={`${base}/${item?.uuid}/edit`} dirty={isDirty} />}
            <form onSubmit={submit} noValidate>
                <FormSection className="mb-6 max-w-xl space-y-4">
                    {textFields}

                    {lock(
    <div className="space-y-2">
                        <Label htmlFor="url">{t('Address')}</Label>
                        <Input id="url" value={data.url} aria-invalid={!!errors.url} onChange={(e) => setData('url', e.target.value)} />
                        <p className="text-sm text-muted-foreground">
                            {t('A path starting with /, #anchor, ?query, or an http, https, mailto or tel address. Leave empty for a heading without a link.')}
                        </p>
                        {error('url')}
                    </div>

                    )}

                    {lock(
    <div className="space-y-2">
                        <Label htmlFor="target">{t('Open in')}</Label>
                        <Select value={data.target} onValueChange={(value) => setData('target', value as '_self' | '_blank')}>
                            <SelectTrigger id="target" className="w-full" aria-invalid={!!errors.target}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="_self">{t('The same window')}</SelectItem>
                                <SelectItem value="_blank">{t('A new window')}</SelectItem>
                            </SelectContent>
                        </Select>
                        {error('target')}
                    </div>

                    )}

                    {lock(
    <div className="space-y-2">
                        <Label htmlFor="css_class">{t('CSS class')}</Label>
                        <Input id="css_class" value={data.css_class} aria-invalid={!!errors.css_class} onChange={(e) => setData('css_class', e.target.value)} />
                        <p className="text-sm text-muted-foreground">{t('Class names separated by spaces: Latin letters, digits, hyphens and underscores.')}</p>
                        {error('css_class')}
                    </div>

                    )}

                    {lock(
    <div className="space-y-2">
                        <Label htmlFor="image">{t('Image')}</Label>
                        <FileUpload profile="image" id="image" value={selectedImage} onChange={onImage} error={errors.image} data-testid="file-upload-image" />
                    </div>

                    )}

                    {lock(
    <div className="space-y-2">
                        <Label htmlFor="parent_uuid">{t('Parent item')}</Label>
                        <Select
                            value={data.parent_uuid === '' ? NO_PARENT : data.parent_uuid}
                            onValueChange={(value) => setData('parent_uuid', value === NO_PARENT ? '' : value)}
                        >
                            <SelectTrigger id="parent_uuid" className="w-full" aria-invalid={!!errors.parent_uuid}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NO_PARENT}>{t('No parent item')}</SelectItem>
                                {parentOptions.map((option) => (
                                    <SelectItem key={option.uuid} value={option.uuid}>
                                        <span style={{ paddingLeft: `${(option.depth - 1) * 1}rem` }}>{option.name}</span>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {error('parent_uuid')}
                    </div>

                    )}

                    {lock(
    <div className="space-y-2">
                        <Label htmlFor="weight">{t('Weight')}</Label>
                        <Input
                            id="weight"
                            type="number"
                            min={0}
                            value={data.weight}
                            aria-invalid={!!errors.weight}
                            onChange={(e) => setData('weight', e.target.value)}
                        />
                        <p className="text-sm text-muted-foreground">{t('Items with the same parent are ordered by weight, the lowest first.')}</p>
                        {error('weight')}
                    </div>
                    )}
                </FormSection>

                {lock(
                <FormSection className="mb-6 max-w-xl space-y-4">
                    <div className="flex items-center justify-between gap-2">
                        <Label htmlFor="is_active" className="cursor-pointer">
                            {t('Active')}
                        </Label>
                        <Switch
                            id="is_active"
                            className="cursor-pointer"
                            checked={data.is_active}
                            aria-invalid={!!errors.is_active}
                            onCheckedChange={(checked) => setData('is_active', checked)}
                        />
                    </div>
                    {error('is_active')}

                    <div className="space-y-2">
                        <Label htmlFor="published_at">{t('Show from')}</Label>
                        <Input
                            id="published_at"
                            type="datetime-local"
                            value={data.published_at}
                            aria-invalid={!!errors.published_at}
                            onChange={(e) => setData('published_at', e.target.value)}
                        />
                        {error('published_at')}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="unpublished_at">{t('Show until')}</Label>
                        <Input
                            id="unpublished_at"
                            type="datetime-local"
                            value={data.unpublished_at}
                            aria-invalid={!!errors.unpublished_at}
                            onChange={(e) => setData('unpublished_at', e.target.value)}
                        />
                        <p className="text-sm text-muted-foreground">{t('Both dates are optional. Times are in UTC.')}</p>
                        {error('unpublished_at')}
                    </div>
                </FormSection>
                )}

                <FormActionBar cancelHref={base} processing={processing} saveDisabled={translating && !isDirty}>
                    {translating && translation?.exists && <ClearTranslationButton clearUrl={`${base}/${item?.uuid}/translations/${encodeURIComponent(lang)}`} />}
                </FormActionBar>
            </form>
        </>
    );
}
