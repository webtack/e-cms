import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import type { ParentOption } from '@/Components/Admin/ContentTypes/ContentTypeForm';
import type { TypeSection } from '@/Components/Admin/ContentTypes/section';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    section: TypeSection;
    /** The type being cloned. */
    source: { key: string; name: string; parent_type: string | null };
    parentOptions: ParentOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/** The Select cannot hold an empty value, so "no parent" travels under this placeholder. */
const NO_PARENT = '__none__';

/**
 * Dialog of the "Clone" row action: asks for the `name`, `key`, parent type and (kind `content` only) `slug` of the copy
 * and posts them to `{basePath}/{key}/clone`; the server copies the fields and settings and redirects to the fields
 * page of the new type. `name`, `key` and `slug` start empty, the parent is pre-filled from the source. Server errors
 * are shown next to the fields and the entered values are kept.
 *
 * @example
 * <CloneTypeDialog section={section} source={row} parentOptions={parentOptions} open={open} onOpenChange={setOpen} />
 */
export function CloneTypeDialog({ section, source, parentOptions, open, onOpenChange }: Props) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, clearErrors } = useForm({
        name: '',
        key: '',
        slug: '',
        parent_type: source.parent_type ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(`${section.basePath}/${source.key}/clone`, { preserveScroll: true });
    };

    const error = (field: keyof typeof errors) =>
        errors[field] && (
            <p role="alert" className="text-sm text-destructive">
                {errors[field]}
            </p>
        );

    const parent = parentOptions.find((option) => option.key === data.parent_type);

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    clearErrors();
                }
                onOpenChange(next);
            }}
        >
            <DialogContent>
                <form onSubmit={submit} noValidate className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>{t('Clone type')}</DialogTitle>
                        <DialogDescription>
                            {t('The fields and settings of ":name" are copied. Nodes and values are not.', { name: source.name })}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-2">
                        <Label htmlFor="clone-name">{t('Name')}</Label>
                        <Input id="clone-name" value={data.name} aria-invalid={!!errors.name} onChange={(e) => setData('name', e.target.value)} />
                        {error('name')}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="clone-key">{t('Key')}</Label>
                        <Input id="clone-key" value={data.key} aria-invalid={!!errors.key} onChange={(e) => setData('key', e.target.value)} />
                        {error('key')}
                    </div>

                    {section.kind === 'content' && (
                        <div className="space-y-2">
                            <Label htmlFor="clone-slug">{t('Slug')}</Label>
                            <Input id="clone-slug" value={data.slug} aria-invalid={!!errors.slug} onChange={(e) => setData('slug', e.target.value)} />
                            {parent && (
                                <p className="text-sm text-muted-foreground">
                                    {parent.slug
                                        ? t('The slug must start with ":prefix".', { prefix: `${parent.slug}/` })
                                        : t('The parent type has no slug. Give it a slug before this type can have one.')}
                                </p>
                            )}
                            {error('slug')}
                        </div>
                    )}

                    <div className="space-y-2">
                        <Label htmlFor="clone-parent_type">{t('Parent type')}</Label>
                        <Select
                            value={data.parent_type === '' ? NO_PARENT : data.parent_type}
                            onValueChange={(value) => setData('parent_type', value === NO_PARENT ? '' : value)}
                        >
                            <SelectTrigger id="clone-parent_type" className="w-full" aria-invalid={!!errors.parent_type}>
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

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {t('Clone')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
