import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { FormActionBar } from '@/Components/Admin/FormActionBar';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';
import { FormSection } from '@/Components/Admin/FormSection';

/** Values of a menu the form edits. */
export type MenuValues = {
    key: string;
    name: string;
    description: string | null;
    /** `null` means the depth of the item tree is not limited. */
    max_depth: number | null;
};

type Props = {
    /** `create` posts a new menu; `edit` updates `menu` and shows its key read-only. */
    mode: 'create' | 'edit';
    /** The menu being edited (required in `edit` mode). */
    menu?: MenuValues;
};

/**
 * Shared form of the create and edit pages of a menu: key, name, description and maximum depth.
 * Server validation errors are shown next to their fields; the key is read-only when editing,
 * because it never changes.
 *
 * @example
 * <MenuForm mode="create" />
 * <MenuForm mode="edit" menu={menu} />
 */
export function MenuForm({ mode, menu }: Props) {
    const { t } = useTranslation();
    const editing = mode === 'edit';
    const { data, setData, post, put, processing, errors } = useForm({
        key: menu?.key ?? '',
        name: menu?.name ?? '',
        description: menu?.description ?? '',
        max_depth: menu?.max_depth?.toString() ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (editing) {
            put(`/admin/menus/${menu?.key}`);
        } else {
            post('/admin/menus');
        }
    };

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

                <div className="space-y-2">
                    <Label htmlFor="max_depth">{t('Maximum depth')}</Label>
                    <Input
                        id="max_depth"
                        type="number"
                        min={1}
                        value={data.max_depth}
                        aria-invalid={!!errors.max_depth}
                        onChange={(e) => setData('max_depth', e.target.value)}
                    />
                    <p className="text-sm text-muted-foreground">{t('How many levels the item tree may have. Leave empty for no limit.')}</p>
                    {error('max_depth')}
                </div>
            </FormSection>
            <FormActionBar cancelHref="/admin/menus" processing={processing} />
        </form>
    );
}
