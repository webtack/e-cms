import { Head, Link, useForm } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';

import { FileUpload, type UploadedFile } from '@/Components/Admin/FileUpload/FileUpload';
import { FieldGroup } from '@/Components/Admin/FieldGroup';
import { FormActionBar } from '@/Components/Admin/FormActionBar';
import { SortableList } from '@/Components/Admin/Settings/SortableList';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { FormSection } from '@/Components/Admin/FormSection';
import { PageTitle } from '@/Components/Admin/PageTitle';
import { ClearTranslationButton } from '@/Components/Admin/Translation/ClearTranslationButton';
import { LanguageSwitcher } from '@/Components/Admin/Translation/LanguageSwitcher';
import type { TranslationState } from '@/types/translation';

type LabelledRow = { value: string; label: string };
type SocialRow = { network: string; url: string; label: string };
type ScheduleRow = string[];

/** Values of the `default` language, as the form edits them. */
type Values = {
    name: string;
    short_name: string;
    description: string;
    address: string;
    logo_large: string | null;
    logo_small: string | null;
    emails: LabelledRow[];
    phones: LabelledRow[];
    socials: SocialRow[];
    schedule: ScheduleRow[];
    indexable: boolean;
};

/** The translatable settings of one language, prefilled by the server (list rows are the base rows). */
type TranslatedValues = Pick<Values, 'name' | 'short_name' | 'description' | 'address' | 'emails' | 'phones' | 'socials' | 'schedule'>;

/** Keys a translation sends; everything else of the form is base-only. */
const TRANSLATABLE: (keyof TranslatedValues)[] = ['name', 'short_name', 'description', 'address', 'emails', 'phones', 'socials', 'schedule'];

type LogoKey = 'logo_large' | 'logo_small';

/** A stored logo, as answered by the controller. */
type LogoFile = { uuid: string; name: string; description: string | null; mime: string; size: number; url: string; preview: string | null };

type Props = {
    values: Values;
    logoFiles: Record<LogoKey, LogoFile | null>;
    networkOptions: { value: string; label: string }[];
    /** Bounds of the form, mirrored from the form request (the server enforces them). */
    limits: { name: number; short_name: number; description: number; address: number; list: number; text: number };
    /** Switcher and translation-mode state; `null` when no switcher is shown. */
    translation: TranslationState<TranslatedValues> | null;
};

const SCHEDULE_COLUMNS = 4;

/**
 * Site settings page (`GET /admin/settings`): one form with the general data of the site, saved in one go.
 * The base mode edits the `default` language: email, phone, social and schedule lists can be extended, shortened
 * and reordered. With `translation.lang` set the same form is the translation mode (see
 * `docs/admin-translation-mode.md`): the name, short name, description, address, schedule and the labels of the
 * contact and social rows show that language and are the only editable controls; the logos, the indexing switch and
 * the identity of every row keep their place, shown inert and dimmed with their base values, and the rows cannot be
 * added, removed or moved. Server errors are shown next to the row field they belong to.
 */
export default function Site(props: Props) {
    const { translation } = props;

    // A new language or freshly saved values remount the form so that it starts clean.
    return <SiteForm key={`${translation?.lang ?? 'base'}:${translation?.exists ? 1 : 0}:${JSON.stringify(translation?.values ?? null)}`} {...props} />;
}

function SiteForm({ values, logoFiles, networkOptions, limits, translation }: Props) {
    const { t } = useTranslation();
    const lang = translation?.lang ?? null;
    const translating = lang !== null;
    const { data, setData, put, processing, errors, isDirty, transform } = useForm<Values>({ ...values, ...(translation?.values ?? {}) });
    const base = '/admin/settings';

    // A translation sends the translatable settings only; the logos and the indexing flag stay out of it.
    transform((form) => (translating ? Object.fromEntries(TRANSLATABLE.map((key) => [key, form[key]])) : form) as Values);

    /** In the translation mode what is not translated keeps its place, shows the base value and is out of reach. */
    const lock = (node: ReactNode) =>
        translating ? (
            <div inert aria-disabled="true" className="select-none opacity-60">
                {node}
            </div>
        ) : (
            node
        );
    const [previews, setPreviews] = useState<Record<LogoKey, LogoFile | null>>(logoFiles);
    const fieldErrors = errors as Record<string, string | undefined>;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(translating ? `${base}/translations/${encodeURIComponent(lang)}` : base, { preserveScroll: true });
    };

    const error = (field: string) =>
        fieldErrors[field] && (
            <p role="alert" className="text-sm text-destructive">
                {fieldErrors[field]}
            </p>
        );

    const onLogo = (key: LogoKey, file: UploadedFile | null) => {
        setData(key, file ? file.value : null);
        setPreviews({
            ...previews,
            [key]: file ? { uuid: file.uuid, name: file.name, description: null, mime: file.mime, size: file.size, url: file.url, preview: file.preview } : null,
        });
    };

    const selectedLogo = (key: LogoKey): UploadedFile | null => {
        const preview = previews[key];
        const value = data[key];

        return value && preview
            ? {
                  uuid: preview.uuid,
                  profile: 'image',
                  name: preview.name,
                  mime: preview.mime,
                  size: preview.size,
                  width: null,
                  height: null,
                  value,
                  url: preview.url,
                  preview: preview.preview,
              }
            : null;
    };

    /** Errors of the whole list (`emails`) and of the rows' fields (`emails.0.value`) are kept apart. */
    const labelledList = (key: 'emails' | 'phones', inputType: string, valueLabel: string, addLabel: string, rowLabel: (n: number) => string) => (
        <div className="space-y-2">
            <SortableList
                items={data[key]}
                onChange={(rows) => setData(key, rows)}
                createItem={() => ({ value: '', label: '' })}
                addLabel={addLabel}
                rowLabel={(i) => rowLabel(i + 1)}
                max={limits.list}
                testId={key}
                fixed={translating}
                renderItem={(row, i, update) => (
                    <div className="grid gap-2 sm:grid-cols-2">
                        {lock(
                        <div className="space-y-1">
                            <Input
                                type={inputType}
                                value={row.value}
                                placeholder={valueLabel}
                                aria-label={`${valueLabel}: ${rowLabel(i + 1)}`}
                                aria-invalid={!!fieldErrors[`${key}.${i}.value`]}
                                data-testid={`${key}-value`}
                                onChange={(e) => update({ ...row, value: e.target.value })}
                            />
                            {error(`${key}.${i}.value`)}
                        </div>
                        )}
                        <div className="space-y-1">
                            <Input
                                value={row.label}
                                placeholder={t('Label (optional)')}
                                aria-label={`${t('Label')}: ${rowLabel(i + 1)}`}
                                aria-invalid={!!fieldErrors[`${key}.${i}.label`]}
                                data-testid={`${key}-label`}
                                onChange={(e) => update({ ...row, label: e.target.value })}
                            />
                            {error(`${key}.${i}.label`)}
                        </div>
                    </div>
                )}
            />
            {error(key)}
        </div>
    );

    const logo = (key: LogoKey, label: string) => (
        <div className="space-y-2">
            <Label htmlFor={key}>{label}</Label>
            <FileUpload
                profile="image"
                id={key}
                value={selectedLogo(key)}
                onChange={(file) => onLogo(key, file)}
                error={fieldErrors[key]}
                data-testid={`file-upload-${key}`}
            />
        </div>
    );

    return (
        <>
            <Head title={t('Site settings')} />
            <div className="mb-6 flex flex-wrap items-center justify-between gap-2">
                <PageTitle>{t('Site settings')}</PageTitle>
                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="outline" size="sm">
                        <Link href="/admin/settings/languages" data-testid="languages-link">
                            {t('Languages')}
                        </Link>
                    </Button>
                    <Button asChild variant="outline" size="sm">
                        <Link href="/admin/settings/navigation" data-testid="admin-menu-link">
                            {t('Admin menu')}
                        </Link>
                    </Button>
                </div>
            </div>

            {translation && <LanguageSwitcher translation={translation} editUrl={base} dirty={isDirty} />}
            <form onSubmit={submit} noValidate>
                <div className="mb-6 max-w-3xl space-y-8">
                    <Section title={t('General')} testId="section-general">
                        <div className="space-y-2">
                            <Label htmlFor="name">{t('Site name')}</Label>
                            <Input id="name" value={data.name} maxLength={limits.name} aria-invalid={!!errors.name} onChange={(e) => setData('name', e.target.value)} />
                            {error('name')}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="short_name">{t('Short name')}</Label>
                            <Input
                                id="short_name"
                                value={data.short_name}
                                maxLength={limits.short_name}
                                aria-invalid={!!errors.short_name}
                                onChange={(e) => setData('short_name', e.target.value)}
                            />
                            {error('short_name')}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="description">{t('Description')}</Label>
                            <Textarea
                                id="description"
                                rows={4}
                                value={data.description}
                                maxLength={limits.description}
                                aria-invalid={!!errors.description}
                                onChange={(e) => setData('description', e.target.value)}
                            />
                            {error('description')}
                        </div>
                    </Section>

                    <Section title={t('Logos')} testId="section-logos">
                        {lock(logo('logo_large', t('Large logo')))}
                        {lock(logo('logo_small', t('Small logo')))}
                    </Section>

                    <Section title={t('Contacts')} testId="section-contacts">
                        <FieldGroup legend={t('Emails')}>
                            {labelledList('emails', 'text', t('Email'), t('Add email'), (n) => t('Email :number', { number: n }))}
                        </FieldGroup>
                        <FieldGroup legend={t('Phones')}>
                            {labelledList('phones', 'text', t('Phone'), t('Add phone'), (n) => t('Phone :number', { number: n }))}
                        </FieldGroup>
                        <div className="space-y-2">
                            <Label htmlFor="address">{t('Address')}</Label>
                            <Textarea
                                id="address"
                                rows={3}
                                value={data.address}
                                maxLength={limits.address}
                                aria-invalid={!!errors.address}
                                onChange={(e) => setData('address', e.target.value)}
                            />
                            {error('address')}
                        </div>
                    </Section>

                    <Section title={t('Social networks')} testId="section-socials">
                        <div className="space-y-2">
                            <SortableList
                                items={data.socials}
                                onChange={(rows) => setData('socials', rows)}
                                createItem={() => ({ network: '', url: '', label: '' })}
                                addLabel={t('Add social network')}
                                rowLabel={(i) => t('Social network :number', { number: i + 1 })}
                                max={limits.list}
                                testId="socials"
                                fixed={translating}
                                renderItem={(row, i, update) => (
                                    <div className="grid gap-2 sm:grid-cols-3">
                                        {lock(
                                        <div className="space-y-1">
                                            <Select value={row.network} onValueChange={(network) => update({ ...row, network })}>
                                                <SelectTrigger
                                                    className="w-full"
                                                    aria-label={`${t('Network')}: ${t('Social network :number', { number: i + 1 })}`}
                                                    aria-invalid={!!fieldErrors[`socials.${i}.network`]}
                                                    data-testid="socials-network"
                                                >
                                                    <SelectValue placeholder={t('Network')} />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {networkOptions.map((option) => (
                                                        <SelectItem key={option.value} value={option.value}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {error(`socials.${i}.network`)}
                                        </div>
                                        )}
                                        {lock(
                                        <div className="space-y-1">
                                            <Input
                                                value={row.url}
                                                placeholder="https://"
                                                aria-label={`${t('Link')}: ${t('Social network :number', { number: i + 1 })}`}
                                                aria-invalid={!!fieldErrors[`socials.${i}.url`]}
                                                data-testid="socials-url"
                                                onChange={(e) => update({ ...row, url: e.target.value })}
                                            />
                                            {error(`socials.${i}.url`)}
                                        </div>
                                        )}
                                        <div className="space-y-1">
                                            <Input
                                                value={row.label}
                                                placeholder={t('Label (optional)')}
                                                aria-label={`${t('Label')}: ${t('Social network :number', { number: i + 1 })}`}
                                                aria-invalid={!!fieldErrors[`socials.${i}.label`]}
                                                data-testid="socials-label"
                                                onChange={(e) => update({ ...row, label: e.target.value })}
                                            />
                                            {error(`socials.${i}.label`)}
                                        </div>
                                    </div>
                                )}
                            />
                            {error('socials')}
                        </div>
                    </Section>

                    <Section title={t('Working hours')} testId="section-schedule">
                        <div className="space-y-2">
                            <SortableList
                                items={data.schedule}
                                onChange={(rows) => setData('schedule', rows)}
                                createItem={() => Array.from({ length: SCHEDULE_COLUMNS }, () => '')}
                                addLabel={t('Add row')}
                                rowLabel={(i) => t('Row :number', { number: i + 1 })}
                                max={limits.list}
                                testId="schedule"
                                renderItem={(row, i, update) => (
                                    <div className="grid gap-2 sm:grid-cols-4">
                                        {Array.from({ length: SCHEDULE_COLUMNS }, (_, column) => (
                                            <div key={column} className="space-y-1">
                                                <Input
                                                    value={row[column] ?? ''}
                                                    aria-label={t('Row :number, column :column', { number: i + 1, column: column + 1 })}
                                                    aria-invalid={!!fieldErrors[`schedule.${i}.${column}`]}
                                                    data-testid={`schedule-cell-${column}`}
                                                    onChange={(e) =>
                                                        update(Array.from({ length: SCHEDULE_COLUMNS }, (_, c) => (c === column ? e.target.value : (row[c] ?? ''))))
                                                    }
                                                />
                                                {error(`schedule.${i}.${column}`)}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            />
                            {error('schedule')}
                        </div>
                    </Section>

                    <Section title={t('Indexing')} testId="section-indexing">
                        {lock(
                        <>
                        <div className="flex items-center justify-between gap-2">
                            <Label htmlFor="indexable" className="cursor-pointer">
                                {t('Allow search engines to index the site')}
                            </Label>
                            <Switch
                                id="indexable"
                                className="cursor-pointer"
                                checked={data.indexable}
                                aria-invalid={!!errors.indexable}
                                onCheckedChange={(checked) => setData('indexable', checked)}
                            />
                        </div>
                        <p className="text-sm text-muted-foreground">{t('The flag is only stored; the public site decides how to use it.')}</p>
                        {error('indexable')}
                        </>
                        )}
                    </Section>
                </div>

                <FormActionBar cancelHref="/admin" processing={processing} saveDisabled={translating && !isDirty}>
                    {translating && translation?.exists && <ClearTranslationButton clearUrl={`${base}/translations/${encodeURIComponent(lang)}`} />}
                </FormActionBar>
            </form>
        </>
    );
}

function Section({ title, testId, children }: { title: string; testId: string; children: ReactNode }) {
    return (
        <FormSection as="section" data-testid={testId} className="space-y-4">
            <h2 className="text-lg font-medium">{title}</h2>
            {children}
        </FormSection>
    );
}

Site.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
