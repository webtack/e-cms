import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Switch } from '@/Components/ui/switch';
import { useTranslation } from '@/hooks/useTranslation';
import { FieldGroup } from '@/Components/Admin/FieldGroup';
import { FormSection } from '@/Components/Admin/FormSection';

/** A choosable field or sort option. A service name is an English translation key; a field's name is data. */
export type PublicOption = { key: string; name: string; service: boolean };

/** What the server offers (`PublicDisplaySettings::formOptions()`), minus the current state. */
export type PublicDisplayOptions = {
    /** The added fields (user fields holding node values). */
    fields: PublicOption[];
    /** Sortable service keys and added fields; empty for kinds without a list. */
    sortOptions: PublicOption[];
    /** The dates a list can be split into the main list and the archive by (service dates and added date fields); empty for kinds without a list. */
    archiveOptions: PublicOption[];
    current: PublicDisplayValue;
    /** Upper bound of `per_page`. */
    maxPerPage: number;
};

/** The archive choice: the date the list is split by and the sort of the archive list. */
export type ArchiveValue = { field: string; sort: { field: string; direction: 'asc' | 'desc' } };

/** Select value of the empty "Do not use" choice (a Radix item cannot have an empty value). */
const NO_ARCHIVE = '__none__';

/**
 * The state the section edits. `list_fields`, `sort`, `per_page`, `pagination` and `archive` exist for kind `content` only.
 * `per_page` is kept as typed so that an empty or invalid entry reaches the server's validation.
 */
export type PublicDisplayValue = {
    /** "All added fields" for the page: submitted as a missing `page_fields`. */
    all_page_fields: boolean;
    page_fields: string[];
    list_fields?: string[];
    sort?: { field: string; direction: 'asc' | 'desc' };
    per_page?: number | string;
    pagination?: boolean;
    /** `null` while the list has no archive. */
    archive?: ArchiveValue | null;
};

type Props = {
    /** Kind of the type: only `content` has a public list (card fields, sort, count, paginator). */
    kind: string;
    options: PublicDisplayOptions;
    value: PublicDisplayValue;
    onChange: (value: PublicDisplayValue) => void;
    /** Server errors of `settings*` other than the per-page one, shown below the section. */
    errors?: string[];
    /** Server error of the per-page field. */
    perPageError?: string;
};

/**
 * Converts the section state to the `settings.public` the server expects: `page_fields` is left out while "All
 * added fields" is on, and kinds without a list send the page fields only. The meta data choice is not part of it:
 * it is saved from the "SEO" tab (`MetaDataSection`).
 *
 * @example
 * toPublicSettings('content', { all_page_fields: true, page_fields: ['price'], per_page: '0' });
 * // { per_page: 0 } (plus the other list keys that are set)
 * toPublicSettings('content', { all_page_fields: true, page_fields: [], archive: { field: 'date', sort: { field: 'date', direction: 'desc' } } });
 * // { ..., archive: { field: 'date', sort: { field: 'date', direction: 'desc' } } } (no `archive` key while none is chosen)
 * toPublicSettings('page', { all_page_fields: false, page_fields: ['intro'] });
 * // { page_fields: ['intro'] }
 */
export function toPublicSettings(kind: string, value: PublicDisplayValue) {
    const settings: Record<string, unknown> = value.all_page_fields ? {} : { page_fields: value.page_fields };
    if (kind !== 'content') {
        return settings;
    }

    const perPage = String(value.per_page ?? '').trim();

    return {
        ...settings,
        list_fields: value.list_fields ?? [],
        sort: value.sort,
        // A number when it is one; anything else travels as typed and fails validation next to the field.
        per_page: perPage !== '' && /^-?\d+$/.test(perPage) ? Number(perPage) : perPage,
        pagination: value.pagination ?? true,
        // Sent only while a field is chosen: a cleared choice removes the key on the server.
        ...(value.archive ? { archive: value.archive } : {}),
    };
}

/**
 * The "Public display" section of a type's settings form: which added fields the node page shows ("All added
 * fields" or a chosen list), and for kind `content` also the card fields of the public list, its sort, the number
 * of nodes per page (`0` is all), the paginator switch (disabled while the count is `0`) and the archive (the date the list is split by and the sort of the archive list). It is controlled: the parent form owns the value and submits it with {@link toPublicSettings}.
 *
 * @example
 * <PublicDisplaySettingsSection kind="content" options={publicOptions} value={data.settings.public} onChange={(p) => setData('settings', { ...data.settings, public: p })} />
 */
export function PublicDisplaySettingsSection({ kind, options, value, onChange, errors = [], perPageError }: Props) {
    const { t } = useTranslation();
    const label = (option: PublicOption) => (option.service ? t(option.name) : option.name);
    const isList = kind === 'content';
    const toggle = (list: string[], key: string, checked: boolean) => (checked ? [...list, key] : list.filter((item) => item !== key));
    const allNodes = String(value.per_page ?? 0).trim() === '0';

    return (
        <FormSection as="section" aria-labelledby="public-display-heading" data-testid="public-display-settings" className="mb-6 max-w-xl space-y-6">
            <h2 id="public-display-heading" className="text-lg font-semibold">
                {t('Public display')}
            </h2>
            <p className="text-sm text-muted-foreground">
                {t('The title, service attributes and meta fields are always available to the template; only added fields are chosen here.')}
            </p>

            <FieldGroup legend={t('Page fields')}>
                <div className="flex items-center gap-2">
                    <Switch id="public-all-page-fields" checked={value.all_page_fields} onCheckedChange={(checked) => onChange({ ...value, all_page_fields: checked })} />
                    <Label htmlFor="public-all-page-fields">{t('All added fields')}</Label>
                </div>
                {value.all_page_fields && <p className="text-sm text-muted-foreground">{t('A new field appears on the page automatically while this is on.')}</p>}
                {options.fields.length === 0 && <p className="text-sm text-muted-foreground">{t('The type has no added fields yet.')}</p>}
                <ul className="space-y-1">
                    {options.fields.map((option) => (
                        <li key={option.key} className="flex items-center gap-2">
                            <Checkbox
                                id={`public-page-${option.key}`}
                                checked={value.all_page_fields || value.page_fields.includes(option.key)}
                                disabled={value.all_page_fields}
                                onCheckedChange={(checked) => onChange({ ...value, page_fields: toggle(value.page_fields, option.key, checked === true) })}
                            />
                            <Label htmlFor={`public-page-${option.key}`} className="cursor-pointer">
                                {label(option)}
                            </Label>
                        </li>
                    ))}
                </ul>
            </FieldGroup>

            {isList && (
                <>
                    <FieldGroup legend={t('Card fields')}>
                        <ul className="space-y-1">
                            {options.fields.map((option) => (
                                <li key={option.key} className="flex items-center gap-2">
                                    <Checkbox
                                        id={`public-card-${option.key}`}
                                        checked={(value.list_fields ?? []).includes(option.key)}
                                        onCheckedChange={(checked) => onChange({ ...value, list_fields: toggle(value.list_fields ?? [], option.key, checked === true) })}
                                    />
                                    <Label htmlFor={`public-card-${option.key}`} className="cursor-pointer">
                                        {label(option)}
                                    </Label>
                                </li>
                            ))}
                        </ul>
                    </FieldGroup>

                    <FieldGroup legend={t('Default sort')}>
                        <div className="flex gap-2">
                            <div className="flex-1 space-y-1">
                                <Label htmlFor="public-sort-field">{t('Field')}</Label>
                                <Select value={value.sort?.field} onValueChange={(field) => onChange({ ...value, sort: { direction: 'desc', ...value.sort, field } })}>
                                    <SelectTrigger id="public-sort-field" className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options.sortOptions.map((option) => (
                                            <SelectItem key={option.key} value={option.key}>
                                                {label(option)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="w-44 space-y-1">
                                <Label htmlFor="public-sort-direction">{t('Direction')}</Label>
                                <Select
                                    value={value.sort?.direction}
                                    onValueChange={(direction) =>
                                        onChange({ ...value, sort: { field: 'published_at', ...value.sort, direction: direction as 'asc' | 'desc' } })
                                    }
                                >
                                    <SelectTrigger id="public-sort-direction" className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="asc">{t('Ascending')}</SelectItem>
                                        <SelectItem value="desc">{t('Descending')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </FieldGroup>

                    <FieldGroup legend={t('Archive')}>
                        <div className="space-y-1">
                            <Label htmlFor="public-archive-field">{t('Archive field')}</Label>
                            <Select
                                value={value.archive?.field ?? NO_ARCHIVE}
                                onValueChange={(field) =>
                                    onChange({
                                        ...value,
                                        archive:
                                            field === NO_ARCHIVE
                                                ? null
                                                : { field, sort: value.archive?.sort ?? value.sort ?? { field: 'published_at', direction: 'desc' } },
                                    })
                                }
                            >
                                <SelectTrigger id="public-archive-field" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NO_ARCHIVE}>{t('Do not use')}</SelectItem>
                                    {options.archiveOptions.map((option) => (
                                        <SelectItem key={option.key} value={option.key}>
                                            {label(option)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        {value.archive && (
                            <div className="flex gap-2">
                                <div className="flex-1 space-y-1">
                                    <Label htmlFor="public-archive-sort-field">{t('Archive sort')}</Label>
                                    <Select
                                        value={value.archive.sort.field}
                                        onValueChange={(field) => onChange({ ...value, archive: { ...value.archive!, sort: { ...value.archive!.sort, field } } })}
                                    >
                                        <SelectTrigger id="public-archive-sort-field" className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {options.sortOptions.map((option) => (
                                                <SelectItem key={option.key} value={option.key}>
                                                    {label(option)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="w-44 space-y-1">
                                    <Label htmlFor="public-archive-sort-direction">{t('Direction')}</Label>
                                    <Select
                                        value={value.archive.sort.direction}
                                        onValueChange={(direction) =>
                                            onChange({
                                                ...value,
                                                archive: { ...value.archive!, sort: { ...value.archive!.sort, direction: direction as 'asc' | 'desc' } },
                                            })
                                        }
                                    >
                                        <SelectTrigger id="public-archive-sort-direction" className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="asc">{t('Ascending')}</SelectItem>
                                            <SelectItem value="desc">{t('Descending')}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        )}
                    </FieldGroup>

                    <FieldGroup legend={t('Pagination')}>
                        <div className="space-y-2">
                            <Label htmlFor="public-per-page">{t('Nodes per page')}</Label>
                            <Input
                                id="public-per-page"
                                type="number"
                                min={0}
                                max={options.maxPerPage}
                                value={value.per_page ?? 0}
                                aria-invalid={!!perPageError}
                                onChange={(e) => onChange({ ...value, per_page: e.target.value })}
                            />
                            <p className="text-sm text-muted-foreground">{t('0 shows all nodes.')}</p>
                            {perPageError && (
                                <p role="alert" className="text-sm text-destructive">
                                    {perPageError}
                                </p>
                            )}
                        </div>

                        <div className="flex items-center gap-2">
                            <Switch
                                id="public-pagination"
                                checked={!allNodes && (value.pagination ?? true)}
                                disabled={allNodes}
                                onCheckedChange={(checked) => onChange({ ...value, pagination: checked })}
                            />
                            <Label htmlFor="public-pagination">{t('Paginator')}</Label>
                        </div>
                    </FieldGroup>
                </>
            )}

            {errors.map((message) => (
                <p key={message} role="alert" className="text-sm text-destructive">
                    {message}
                </p>
            ))}
        </FormSection>
    );
}
