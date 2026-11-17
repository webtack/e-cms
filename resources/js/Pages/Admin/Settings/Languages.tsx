import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';

import { FormSection } from '@/Components/Admin/FormSection';
import { PageTitle } from '@/Components/Admin/PageTitle';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';

/** One additional content language, as the controller lists it. */
type Language = { code: string; name: string; is_active: boolean; weight: number };

type Props = {
    multilingual: boolean;
    /** The effective base code: the saved one, or the application locale while none is saved. */
    baseLang: string;
    /** The saved base code, or null while none is saved. */
    savedBaseLang: string | null;
    languages: Language[];
    /** Bounds of the forms, mirrored from the form requests (the server enforces them). */
    limits: { code: number; name: number };
};

/** The language being edited, or `null` for a new one; `undefined` while the dialog is closed. */
type Editing = Language | null | undefined;

/** An error paragraph shown under a field. */
const FieldError = ({ message }: { message?: string }) =>
    message ? (
        <p role="alert" className="text-sm text-destructive">
            {message}
        </p>
    ) : null;

/**
 * Content languages page (`GET /admin/settings/languages`): the multilingual switch with the base language code,
 * and the table of additional languages with add, edit and delete. The languages concern the content of the site,
 * never the language of the admin interface. Errors from the server appear next to the field they belong to;
 * deleting a language that has translations keeps the confirmation open with the explanation.
 */
export default function Languages({ multilingual, baseLang, savedBaseLang, languages, limits }: Props) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState<Editing>(undefined);
    const [deleting, setDeleting] = useState<Language | null>(null);
    const [deleteError, setDeleteError] = useState<string | null>(null);
    const settings = useForm({ multilingual, base_lang: savedBaseLang ?? '' });

    const saveSettings = (e: FormEvent) => {
        e.preventDefault();
        settings.put('/admin/settings/languages', { preserveScroll: true });
    };

    const confirmDelete = () => {
        if (!deleting) {
            return;
        }

        router.delete(`/admin/settings/languages/${encodeURIComponent(deleting.code)}`, {
            preserveScroll: true,
            onSuccess: () => setDeleting(null),
            onError: (errors) => setDeleteError(errors.language ?? Object.values(errors)[0] ?? null),
        });
    };

    return (
        <>
            <Head title={t('Languages')} />
            <PageTitle className="mb-2">{t('Languages')}</PageTitle>
            <p className="mb-6 max-w-3xl text-sm text-muted-foreground">
                {t('These languages concern the content of the site. They do not change the language of the admin interface.')}
            </p>

            <form onSubmit={saveSettings} noValidate data-testid="languages-settings-form">
                <FormSection as="section" title={t('Multilingual content')} titleId="multilingual-heading" className="mb-6 max-w-3xl space-y-4">
                    <div className="flex items-center gap-3">
                        <Switch
                            id="multilingual"
                            className="cursor-pointer"
                            checked={settings.data.multilingual}
                            aria-invalid={!!settings.errors.multilingual}
                            onCheckedChange={(checked) => settings.setData('multilingual', checked)}
                        />
                        <Label htmlFor="multilingual" className="cursor-pointer">
                            {t('Multilingual mode')}
                        </Label>
                    </div>
                    <FieldError message={settings.errors.multilingual} />

                    <div className="space-y-2">
                        <Label htmlFor="base_lang">{t('Base language code')}</Label>
                        <Input
                            id="base_lang"
                            className="max-w-40"
                            value={settings.data.base_lang}
                            maxLength={limits.code}
                            placeholder={baseLang}
                            aria-invalid={!!settings.errors.base_lang}
                            onChange={(e) => settings.setData('base_lang', e.target.value)}
                        />
                        <p className="text-sm text-muted-foreground">
                            {t('The language the main values are written in, for example "uk". It must be saved before multilingual mode can be turned on and cannot match an additional language.')}
                        </p>
                        {savedBaseLang === null && (
                            <p className="text-sm text-muted-foreground" data-testid="base-lang-unsaved">
                                {t('Not saved yet: the application locale ":code" is used until a code is saved.', { code: baseLang })}
                            </p>
                        )}
                        <FieldError message={settings.errors.base_lang} />
                    </div>

                    <Button type="submit" disabled={settings.processing}>
                        {t('Save')}
                    </Button>
                </FormSection>
            </form>

            <FormSection as="section" title={t('Additional languages')} titleId="languages-heading" className="max-w-3xl space-y-4">
                <div className="flex justify-end">
                    <Button type="button" size="sm" onClick={() => setEditing(null)} data-testid="add-language">
                        <Plus aria-hidden />
                        {t('Add language')}
                    </Button>
                </div>

                <Table data-testid="languages-table">
                    <TableHeader>
                        <TableRow>
                            <TableHead>{t('Code')}</TableHead>
                            <TableHead>{t('Name')}</TableHead>
                            <TableHead>{t('Active')}</TableHead>
                            <TableHead>{t('Weight')}</TableHead>
                            <TableHead className="text-right">{t('Actions')}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {languages.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={5} className="text-muted-foreground">
                                    {t('No additional languages yet.')}
                                </TableCell>
                            </TableRow>
                        )}
                        {languages.map((language) => (
                            <TableRow key={language.code} data-testid={`language-row-${language.code}`}>
                                <TableCell className="font-mono">{language.code}</TableCell>
                                <TableCell>{language.name}</TableCell>
                                <TableCell>{language.is_active ? t('Yes') : t('No')}</TableCell>
                                <TableCell>{language.weight}</TableCell>
                                <TableCell className="text-right">
                                    <div className="inline-flex gap-2">
                                        <Button type="button" variant="outline" size="sm" onClick={() => setEditing(language)} aria-label={`${t('Edit')}: ${language.code}`}>
                                            <Pencil aria-hidden />
                                            {t('Edit')}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                setDeleteError(null);
                                                setDeleting(language);
                                            }}
                                            aria-label={`${t('Delete')}: ${language.code}`}
                                        >
                                            <Trash2 aria-hidden />
                                            {t('Delete')}
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </FormSection>

            {editing !== undefined && <LanguageDialog key={editing?.code ?? 'new'} language={editing} nextWeight={languages.length} limits={limits} onClose={() => setEditing(undefined)} />}

            <AlertDialog open={deleting !== null} onOpenChange={(open) => !open && setDeleting(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t('Delete language')}</AlertDialogTitle>
                        <AlertDialogDescription>{t('Delete language ":name"?', { name: deleting?.name ?? '' })}</AlertDialogDescription>
                    </AlertDialogHeader>
                    {deleteError && (
                        <p role="alert" className="text-sm text-destructive" data-testid="delete-language-error">
                            {deleteError}
                        </p>
                    )}
                    <AlertDialogFooter>
                        <AlertDialogCancel>{t('Cancel')}</AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            onClick={(e) => {
                                // Keep the dialog open: a rejection is explained inside it.
                                e.preventDefault();
                                confirmDelete();
                            }}
                        >
                            {t('Delete')}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

type DialogProps = {
    /** The language to edit, or `null` to add a new one. */
    language: Language | null;
    /** Weight offered for a new language: the end of the list. */
    nextWeight: number;
    limits: Props['limits'];
    onClose: () => void;
};

/**
 * The add/edit dialog of one additional language. The code can be set only when adding; the dialog stays open
 * with the entered values and the errors until the server accepts the form.
 *
 * @example
 * <LanguageDialog language={null} nextWeight={2} limits={{ code: 16, name: 255 }} onClose={close} />
 */
function LanguageDialog({ language, nextWeight, limits, onClose }: DialogProps) {
    const { t } = useTranslation();
    const form = useForm({
        code: language?.code ?? '',
        name: language?.name ?? '',
        is_active: language?.is_active ?? false,
        weight: String(language?.weight ?? nextWeight),
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (language) {
            form.transform(({ code: _code, ...rest }) => ({ ...rest, weight: Number(rest.weight) }));
            form.put(`/admin/settings/languages/${encodeURIComponent(language.code)}`, options);
        } else {
            form.transform(({ weight: _weight, ...rest }) => rest);
            form.post('/admin/settings/languages', options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent data-testid="language-dialog">
                <form onSubmit={submit} noValidate className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>{language ? t('Edit language') : t('Add language')}</DialogTitle>
                        <DialogDescription>{t('The code is a language code such as "en" or "pt-BR". It cannot be changed later.')}</DialogDescription>
                    </DialogHeader>

                    <div className="space-y-2">
                        <Label htmlFor="language-code">{t('Code')}</Label>
                        <Input
                            id="language-code"
                            value={form.data.code}
                            maxLength={limits.code}
                            disabled={language !== null}
                            aria-invalid={!!form.errors.code}
                            onChange={(e) => form.setData('code', e.target.value)}
                        />
                        <FieldError message={form.errors.code} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="language-name">{t('Name')}</Label>
                        <Input id="language-name" value={form.data.name} maxLength={limits.name} aria-invalid={!!form.errors.name} onChange={(e) => form.setData('name', e.target.value)} />
                        <FieldError message={form.errors.name} />
                    </div>

                    {language && (
                        <div className="space-y-2">
                            <Label htmlFor="language-weight">{t('Weight')}</Label>
                            <Input
                                id="language-weight"
                                type="number"
                                min={0}
                                className="max-w-32"
                                value={form.data.weight}
                                aria-invalid={!!form.errors.weight}
                                onChange={(e) => form.setData('weight', e.target.value)}
                            />
                            <FieldError message={form.errors.weight} />
                        </div>
                    )}

                    <div className="flex items-center gap-3">
                        <Switch id="language-active" className="cursor-pointer" checked={form.data.is_active} onCheckedChange={(checked) => form.setData('is_active', checked)} />
                        <Label htmlFor="language-active" className="cursor-pointer">
                            {t('Active')}
                        </Label>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {t('Save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

Languages.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
