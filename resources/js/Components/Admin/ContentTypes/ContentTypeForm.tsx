import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import type { TypeSection } from '@/Components/Admin/ContentTypes/section';
import { NodeListSettingsSection, type NodeListOptions, type NodeListValue } from '@/Components/Admin/ContentTypes/NodeListSettingsSection';
import { FormActionBar } from '@/Components/Admin/FormActionBar';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';
import { FormSection } from '@/Components/Admin/FormSection';

/** A selectable parent type; `slug` tells the prefix a child slug has to start with (null: the parent has no slug). */
export type ParentOption = { key: string; name: string; slug: string | null };

/** Values of a content type the form edits. */
export type ContentTypeValues = {
    key: string;
    name: string;
    slug: string | null;
    parent_type: string | null;
    description: string | null;
};

type Props = {
    /** `create` posts a new type; `edit` updates `type` and shows its key read-only. */
    mode: 'create' | 'edit';
    /** Section the form belongs to: gives the address to submit to and decides whether the slug is shown. */
    section: TypeSection;
    parentOptions: ParentOption[];
    /** Parent selected when the form opens in `create` mode (the "Child types" tab passes its type). */
    defaultParent?: string | null;
    /** The type being edited (required in `edit` mode). */
    type?: ContentTypeValues;
    /** What the "Node list" section offers and its current state; only the `edit` mode shows the section. */
    listOptions?: NodeListOptions;
};

/** The Select cannot hold an empty value, so "no parent" travels under this placeholder. */
const NO_PARENT = '__none__';

/**
 * Shared form of the create and edit pages: name, key, slug (kind `content` only; several segments separated by `/`,
 * with the prefix hint of the selected parent), parent type and
 * description; when editing also the "Node list" (`settings.list`) section. The kind is not a field: it comes from
 * the section. Server validation errors are shown next to their fields; the key is read-only when editing,
 * because it never changes.
 *
 * @example
 * <ContentTypeForm mode="create" section={section} parentOptions={parentOptions} />
 * <ContentTypeForm mode="create" section={section} parentOptions={parentOptions} defaultParent="common" />
 * <ContentTypeForm mode="edit" section={section} type={type} parentOptions={parentOptions} listOptions={listOptions} />
 */
export function ContentTypeForm({ mode, section, parentOptions, defaultParent, type, listOptions }: Props) {
    const { t } = useTranslation();
    const editing = mode === 'edit';
    const { data, setData, post, put, transform, processing, errors } = useForm({
        name: type?.name ?? '',
        key: type?.key ?? '',
        slug: type?.slug ?? '',
        parent_type: type?.parent_type ?? defaultParent ?? '',
        description: type?.description ?? '',
        settings: {
            list: listOptions?.current ?? ({ visible: [], search: [], sort: { field: 'created_at', direction: 'asc' } } as NodeListValue),
        },
    });
    // Server errors of the list settings come under `settings` and `settings.list.*` keys.
    const listErrors = Object.entries(errors as Record<string, string>)
        .filter(([key]) => key === 'settings' || key.startsWith('settings.'))
        .map(([, message]) => message);

    const submit = (e: FormEvent) => {
        e.preventDefault();

        // Only the edit form has the list section; a new type has no fields to list yet.
        transform((values) =>
            editing ? { ...values, settings: { list: values.settings.list } } : { ...values, settings: undefined },
        );

        if (editing) {
            put(`${section.basePath}/${type?.key}`);
        } else {
            post(section.basePath);
        }
    };

    // A child slug must start with the parent's slug and `/`; a parent without a slug cannot have slugged children.
    const parent = parentOptions.find((option) => option.key === data.parent_type);

    const error = (field: keyof typeof errors) =>
        errors[field] && (
            <p role="alert" className="text-sm text-destructive">
                {errors[field]}
            </p>
        );

    return (
        <form onSubmit={submit} noValidate>
            <FormSection className="mb-6 max-w-xl space-y-4">
                <div className="space-y-2">
                    <Label htmlFor="name">{t('Name')}</Label>
                    <Input id="name" value={data.name} aria-invalid={!!errors.name} onChange={(e) => setData('name', e.target.value)} />
                    {error('name')}
                </div>

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

                {section.kind === 'content' && (
                    <div className="space-y-2">
                        <Label htmlFor="slug">{t('Slug')}</Label>
                        <Input id="slug" value={data.slug} aria-invalid={!!errors.slug} onChange={(e) => setData('slug', e.target.value)} />
                        <p className="text-sm text-muted-foreground">
                            {t('Public path of the type list on the site: lowercase letters, digits and hyphens in each segment, segments separated by "/". Leave empty if the type has no public list.')}
                        </p>
                        {parent && (
                            <p className="text-sm text-muted-foreground" data-testid="slug-parent-hint">
                                {parent.slug
                                    ? t('The slug must start with ":prefix".', { prefix: `${parent.slug}/` })
                                    : t('The parent type has no slug. Give it a slug before this type can have one.')}
                            </p>
                        )}
                        {error('slug')}
                    </div>
                )}

                <div className="space-y-2">
                    <Label htmlFor="parent_type">{t('Parent type')}</Label>
                    <Select
                        value={data.parent_type === '' ? NO_PARENT : data.parent_type}
                        onValueChange={(value) => setData('parent_type', value === NO_PARENT ? '' : value)}
                    >
                        <SelectTrigger id="parent_type" className="w-full" aria-invalid={!!errors.parent_type}>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NO_PARENT}>{t('No parent type')}</SelectItem>
                            {parentOptions.map((option) => (
                                <SelectItem key={option.key} value={option.key}>
                                    {option.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {error('parent_type')}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="description">{t('Description')}</Label>
                    <Textarea
                        id="description"
                        rows={4}
                        value={data.description}
                        aria-invalid={!!errors.description}
                        onChange={(e) => setData('description', e.target.value)}
                    />
                    {error('description')}
                </div>
            </FormSection>
            {editing && listOptions && (
                <NodeListSettingsSection
                    options={listOptions}
                    value={data.settings.list}
                    onChange={(list) => setData('settings', { ...data.settings, list })}
                    errors={listErrors}
                />
            )}
            <FormActionBar cancelHref={section.basePath} processing={processing} />
        </form>
    );
}
