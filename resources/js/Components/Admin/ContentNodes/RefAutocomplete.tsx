import { Check, ChevronsUpDown, X } from 'lucide-react';
import { createContext, useContext, useEffect, useRef, useState } from 'react';

import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

/** One choice of the list: the stored value, the label shown for it and an optional muted hint (the key of a type). */
export type RefOption = { value: string; label: string; hint?: string };

/** How long the user must stop typing before a search is sent. */
const DEBOUNCE_MS = 250;

/**
 * Labels of the values stored in the form (`value → label`), as the server sends them for the edit form, so a
 * reference shows its name or title at once, before any search. The node form provides it; without a provider
 * the labels are empty and a stored value is shown as it is.
 */
export const RefLabelsContext = createContext<Record<string, string>>({});

type Props = {
    /** The element's DOM id; the label points at it. */
    id: string;
    /** The stored value (a type key or a node uuid); `''` means nothing is chosen. */
    value: string;
    onChange: (value: string) => void;
    /**
     * Finds the options for a query (an empty query gives the first ones). It must pass `signal` on to its request:
     * an answer that is no longer awaited is cancelled.
     */
    search: (query: string, signal: AbortSignal) => Promise<RefOption[]>;
    /** Text of the trigger while nothing is chosen. */
    placeholder: string;
    /** Whether the chosen value can be cleared with a button. A row of a multiple field has its own remove button, so it passes `false`. Defaults to `true`. */
    clearable?: boolean;
    invalid?: boolean;
    /** Accessible name; needed when no `<label>` points at the element (items of a multiple field). */
    'aria-label'?: string;
};

/**
 * A drop-down with server-side search for the `type_ref` and `node_ref` fields. The trigger shows the label of the
 * chosen target; opening it shows a search box and up to the first results, and typing asks the server again after a
 * pause ({@link DEBOUNCE_MS}), cancelling the request that is no longer awaited. The widget keeps the labels it has
 * seen (the form's labels, search results, the last choice), so a chosen value keeps its label while the list changes.
 * A value without a known label is shown as it is.
 *
 * @example
 * <RefAutocomplete
 *     id="field-related"
 *     value={value}
 *     onChange={setValue}
 *     placeholder="Choose a node"
 *     search={(query, signal) => fetchOptions(`/admin/lookup/nodes?content_type=news&q=${encodeURIComponent(query)}`, signal)}
 * />
 */
export function RefAutocomplete({ id, value, onChange, search, placeholder, clearable = true, invalid, 'aria-label': ariaLabel }: Props) {
    const { t } = useTranslation();
    const formLabels = useContext(RefLabelsContext);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [options, setOptions] = useState<RefOption[]>([]);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const [seen, setSeen] = useState<Record<string, string>>({});
    const searchRef = useRef(search);
    searchRef.current = search;

    // Searches while the list is open: at once when it opens, after a pause while typing.
    useEffect(() => {
        if (!open) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(
            () => {
                setLoading(true);
                setFailed(false);
                searchRef
                    .current(query, controller.signal)
                    .then((found) => {
                        setOptions(found);
                        setSeen((current) => ({ ...current, ...Object.fromEntries(found.map((option) => [option.value, option.label])) }));
                        setLoading(false);
                    })
                    .catch((error: unknown) => {
                        if ((error as { name?: string })?.name !== 'AbortError') {
                            setFailed(true);
                            setOptions([]);
                            setLoading(false);
                        }
                    });
            },
            query === '' ? 0 : DEBOUNCE_MS,
        );

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [open, query]);

    const label = value === '' ? '' : (seen[value] ?? formLabels[value] ?? value);

    const choose = (option: RefOption) => {
        setSeen((current) => ({ ...current, [option.value]: option.label }));
        onChange(option.value);
        setOpen(false);
    };

    return (
        <div className="flex items-center gap-1">
            <Popover
                open={open}
                onOpenChange={(next) => {
                    setOpen(next);

                    if (next) {
                        setQuery('');
                    }
                }}
            >
                <PopoverTrigger asChild>
                    <Button
                        type="button"
                        id={id}
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        aria-label={ariaLabel}
                        aria-invalid={invalid || undefined}
                        data-testid={`ref-trigger-${id}`}
                        className="w-full justify-between font-normal"
                    >
                        <span className={cn('truncate', value === '' && 'text-muted-foreground')}>{value === '' ? placeholder : label}</span>
                        <ChevronsUpDown aria-hidden className="size-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent align="start" className="w-(--radix-popover-trigger-width) gap-2 p-2" data-testid={`ref-popover-${id}`}>
                    <Input
                        autoFocus
                        value={query}
                        placeholder={t('Search…')}
                        aria-label={t('Search…')}
                        data-testid={`ref-search-${id}`}
                        onChange={(e) => setQuery(e.target.value)}
                    />
                    <ul role="listbox" aria-label={ariaLabel ?? placeholder} className="max-h-60 overflow-y-auto">
                        {options.map((option) => (
                            <li key={option.value} role="option" aria-selected={option.value === value}>
                                <button
                                    type="button"
                                    className="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                                    onClick={() => choose(option)}
                                >
                                    <Check aria-hidden className={cn('size-4 shrink-0', option.value === value ? 'opacity-100' : 'opacity-0')} />
                                    <span className="truncate">{option.label}</span>
                                    {option.hint && <span className="ml-auto shrink-0 text-xs text-muted-foreground">{option.hint}</span>}
                                </button>
                            </li>
                        ))}
                    </ul>
                    {failed && (
                        <p role="alert" className="px-2 py-1 text-sm text-destructive">
                            {t('Could not load the results.')}
                        </p>
                    )}
                    {!failed && !loading && options.length === 0 && <p className="px-2 py-1 text-sm text-muted-foreground">{t('No results')}</p>}
                </PopoverContent>
            </Popover>
            {clearable && value !== '' && (
                <Button type="button" variant="ghost" size="icon-sm" aria-label={t('Clear selection')} data-testid={`ref-clear-${id}`} onClick={() => onChange('')}>
                    <X aria-hidden />
                </Button>
            )}
        </div>
    );
}

/**
 * Fetches a lookup endpoint and returns its JSON list as options. A failed request throws, so the widget shows the
 * error; an aborted one throws an `AbortError`, which the widget ignores.
 *
 * @example
 * fetchRefOptions('/admin/lookup/nodes?content_type=news&q=a', signal, (row) => ({ value: row.uuid, label: row.title }));
 */
export async function fetchRefOptions<T>(url: string, signal: AbortSignal, toOption: (row: T) => RefOption): Promise<RefOption[]> {
    const response = await fetch(url, { signal, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });

    if (!response.ok) {
        throw new Error(`Lookup failed with status ${response.status}`);
    }

    return ((await response.json()) as T[]).map(toOption);
}
