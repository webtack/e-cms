import { FieldGroup } from '@/Components/Admin/FieldGroup';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';

/** The meta data a type can take from its fields (`settings.public.meta`). */
export type MetaName = 'title' | 'description' | 'image';

/** A field a meta choice may use. A service option (the node's own `title`) has an English translation key as `name`. */
export type MetaFieldOption = { key: string; name: string; service: boolean };

/** What the server offers (`PublicDisplaySettings::metaOptions()`) and the stored choice. */
export type MetaOptions = {
    /** Per meta name, the fields of an allowed type; the title also starts with the node's own `title`. */
    metaFields: Record<MetaName, MetaFieldOption[]>;
    /** Chosen field keys by name; a missing entry means "not chosen". */
    current: Partial<Record<MetaName, string>>;
};

/** The choice being edited: chosen field key by name; an empty or missing entry means "not chosen". */
export type MetaChoice = Partial<Record<MetaName, string>>;

type Props = {
    options: MetaOptions;
    /** The current choice, held by the form of the tab. */
    value: MetaChoice;
    onChange: (value: MetaChoice) => void;
    /** Server errors of the choice (keys `settings*`), shown below the selects. */
    errors?: string[];
};

/** Value of the "Not chosen" item of a select: Radix items cannot have an empty value. */
const NO_META = '__none__';

/** Meta names in the order of the form, with the label of each choice. */
const META_CHOICES: { name: MetaName; label: string }[] = [
    { name: 'title', label: 'Title' },
    { name: 'description', label: 'Description' },
    { name: 'image', label: 'Image' },
];

/**
 * The "Meta data" block of the "SEO" tab: which field of the type's records supplies the page's title (the node's
 * own `title` or a string field), description (text and rich text fields) and image (image and YouTube fields), each
 * with "Not chosen". It is a bordered fieldset without a button or form of its own: the form of the tab holds the
 * choice and saves it with the type's `meta_*` values by the one "Save" of its sticky bar.
 *
 * @example
 * <MetaDataSection options={metaOptions} value={data.settings.public.meta} onChange={(meta) => setData(...)} errors={messages} />
 */
export function MetaDataSection({ options, value, onChange, errors = [] }: Props) {
    const { t } = useTranslation();
    const label = (option: MetaFieldOption) => (option.service ? t(option.name) : option.name);

    return (
        <div className="mb-6 max-w-2xl">
            <div data-testid="public-meta" className={'space-y-4 rounded-lg bg-card border p-3'}>
                <h2 className={'font-bold text-lg mb-1'}>{t('Meta data')}</h2>
                <p className="text-sm text-muted-foreground">
                    {t('The chosen fields of a record supply its page meta data; an empty value falls back to the SEO values of the type, then to the site settings.')}
                </p>
                {META_CHOICES.map(({name, label: choice}) => (
                    <div key={name} className="space-y-1">
                        <Label htmlFor={`public-meta-${name}`}>{t(choice)}</Label>
                        <Select value={value[name] || NO_META}
                                onValueChange={(key) => onChange({...value, [name]: key === NO_META ? '' : key})}>
                            <SelectTrigger id={`public-meta-${name}`} className="w-full">
                                <SelectValue/>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NO_META}>{t('Not chosen')}</SelectItem>
                                {options.metaFields[name].map((option) => (
                                    <SelectItem key={option.key} value={option.key}>
                                        {label(option)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                ))}
                {errors.map((message) => (
                    <p key={message} role="alert" className="text-sm text-destructive">
                        {message}
                    </p>
                ))}
            </div>
        </div>
    );
}
