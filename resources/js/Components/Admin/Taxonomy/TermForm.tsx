import { useForm } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';

import { FileUpload, type UploadedFile } from '@/Components/Admin/FileUpload/FileUpload';
import { FormActionBar } from '@/Components/Admin/FormActionBar';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';
import { FormSection } from '@/Components/Admin/FormSection';
import { LanguageSwitcher } from '@/Components/Admin/Translation/LanguageSwitcher';
import { ClearTranslationButton } from '@/Components/Admin/Translation/ClearTranslationButton';
import type { TranslationState } from '@/types/translation';

/** A term that can be chosen as the parent; `depth` (a root has 1) drives the indent. */
export type ParentOption = { uuid: string; name: string | null; depth: number };

/** The translatable fields of a term in one language. */
export type TermTexts = { name: string; description: string };

/** Values of a term the form edits. */
export type TermValues = {
    uuid: string;
    slug: string;
    weight: number;
    parent_uuid: string | null;
    image: string | null;
    /** Name in the base language. */
    name: string;
    /** Description in the base language. */
    description: string;
};

/** The stored image as the server describes it (to show its name and preview). */
export type ImageFile = {
    uuid: string;
    name: string;
    description: string | null;
    mime: string;
    size: number;
    url: string;
    preview: string | null;
};

type Props = {
    /** `create` posts a new term; `edit` updates `term`. */
    mode: 'create' | 'edit';
    vocabularyKey: string;
    parentOptions: ParentOption[];
    /** The translation-mode state of the edit page; `null` when no switcher is shown (and always in `create`). */
    translation?: TranslationState<TermTexts> | null;
    /** The term being edited (required in `edit` mode). */
    term?: TermValues;
    /** The stored image of the term being edited. */
    imageFile?: ImageFile | null;
    /** The weight prefilled in `create` mode: the new term goes last among its siblings. */
    defaultWeight?: number;
};

/** The Select cannot hold an empty value, so "no parent" travels under this placeholder. */
const NO_PARENT = '__none__';

/**
 * Form of a term for the create and edit pages. In `create` and in the base mode of `edit` it edits the shared
 * attributes and the name and description in the base language (slug, parent, weight, one image). The server hands
 * over the parent options already without the term's own subtree and with the depth limit applied. When the edit
 * page has a language switcher, it is shown above the form. With `translation.lang` set the same form is the translation
 * mode: the name and description show that language and are the only editable fields; the slug, parent, weight and image
 * keep their place, shown inert and dimmed with their base values, and "Save" sends only the two texts (see
 * `docs/admin-translation-mode.md`). Server validation errors are shown next to their fields.
 *
 * @example
 * <TermForm mode="create" vocabularyKey="categories" parentOptions={parentOptions} defaultWeight={0} />
 * <TermForm mode="edit" vocabularyKey="categories" term={term} imageFile={imageFile} parentOptions={parentOptions} translation={translation} />
 */
export function TermForm({ mode, vocabularyKey, parentOptions, term, imageFile = null, defaultWeight = 0, translation = null }: Props) {
    const { t } = useTranslation();
    const editing = mode === 'edit';
    const lang = editing ? (translation?.lang ?? null) : null;
    const translating = lang !== null;
    const base = `/admin/taxonomy/${vocabularyKey}/terms`;
    const [preview, setPreview] = useState<ImageFile | null>(imageFile);

    const { data, setData, post, put, processing, errors, isDirty, transform } = useForm({
        slug: term?.slug ?? '',
        weight: String(term?.weight ?? defaultWeight),
        parent_uuid: term?.parent_uuid ?? '',
        image: term?.image ?? '',
        translations: {
            default: {
                name: term?.name ?? '',
                description: term?.description ?? '',
            },
        },
        // The name and description of the language being translated, prefilled by the server (translation mode only).
        text: { name: translation?.values?.name ?? '', description: translation?.values?.description ?? '' },
    });

    // A translation sends its two texts only; the shared attributes stay out of it.
    transform((form) => (translating ? { name: form.text.name, description: form.text.description } : { ...form, text: undefined }));

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (translating) {
            put(`${base}/${term?.uuid}/translations/${encodeURIComponent(lang)}`);
        } else if (editing) {
            put(`${base}/${term?.uuid}`);
        } else {
            post(base);
        }
    };

    const setText = (key: 'name' | 'description', value: string) =>
        translating ? setData('text', { ...data.text, [key]: value }) : setData('translations', { default: { ...data.translations.default, [key]: value } });
    const textOf = (key: 'name' | 'description') => (translating ? data.text[key] : data.translations.default[key]);
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
            file
                ? {
                      uuid: file.uuid,
                      name: file.name,
                      description: null,
                      mime: file.mime,
                      size: file.size,
                      url: file.url,
                      preview: file.preview,
                  }
                : null,
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

    /** Name and description fields of the base language. */
    const textFields = (
        <div className="space-y-4" data-testid={`term-texts-${lang ?? 'default'}`}>
            <div className="space-y-2">
                <Label htmlFor={`name${suffix}`}>{t('Name')}</Label>
                <Input
                    id={`name${suffix}`}
                    value={textOf('name')}
                    aria-invalid={!!errors[errorKey('name') as keyof typeof errors]}
                    onChange={(e) => setText('name', e.target.value)}
                />
                {error(errorKey('name'))}
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
            {editing && translation && <LanguageSwitcher translation={translation} editUrl={`${base}/${term?.uuid}/edit`} dirty={isDirty} />}
            <form onSubmit={submit} noValidate>
                <FormSection className="mb-6 max-w-xl space-y-4">
                    {textFields}

                    {lock(
    <div className="space-y-2">
                        <Label htmlFor="slug">{t('Slug')}</Label>
                        <Input id="slug" value={data.slug} aria-invalid={!!errors.slug} onChange={(e) => setData('slug', e.target.value)} />
                        <p className="text-sm text-muted-foreground">
                            {t('Lowercase letters, digits and hyphens, unique in the vocabulary. Leave empty to generate it from the name.')}
                        </p>
                        {error('slug')}
                    </div>

                    )}

                    {lock(
    <div className="space-y-2">
                        <Label htmlFor="parent_uuid">{t('Parent term')}</Label>
                        <Select
                            value={data.parent_uuid === '' ? NO_PARENT : data.parent_uuid}
                            onValueChange={(value) => setData('parent_uuid', value === NO_PARENT ? '' : value)}
                        >
                            <SelectTrigger id="parent_uuid" className="w-full" aria-invalid={!!errors.parent_uuid}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NO_PARENT}>{t('No parent term')}</SelectItem>
                                {parentOptions.map((option) => (
                                    <SelectItem key={option.uuid} value={option.uuid}>
                                        <span
                                            style={{
                                                paddingLeft: `${(option.depth - 1) * 1}rem`,
                                            }}
                                        >
                                            {option.name}
                                        </span>
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
                        <p className="text-sm text-muted-foreground">{t('Terms with the same parent are ordered by weight, the lowest first.')}</p>
                        {error('weight')}
                    </div>

                    )}

                    {lock(
    <div className="space-y-2">
                        <Label htmlFor="image">{t('Image')}</Label>
                        <FileUpload profile="image" id="image" value={selectedImage} onChange={onImage} error={errors.image} data-testid="file-upload-image" />
                    </div>
                    )}
                </FormSection>

                <FormActionBar cancelHref={base} processing={processing} saveDisabled={translating && !isDirty}>
                    {translating && translation?.exists && <ClearTranslationButton clearUrl={`${base}/${term?.uuid}/translations/${encodeURIComponent(lang)}`} />}
                </FormActionBar>
            </form>
        </>
    );
}
