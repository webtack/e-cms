import { Filter, Plus, X } from 'lucide-react';
import { useState } from 'react';

import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';

import { isFilterComplete } from './params';
import type { Column, FilterOperator, OperatorsByType, TableFilter } from './types';

/** Translation keys of the operator names. */
const OPERATOR_LABELS: Record<FilterOperator, string> = {
    eq: 'Equals',
    contains: 'Contains',
    gt: 'Greater than',
    gte: 'At least',
    lt: 'Less than',
    lte: 'At most',
    between: 'Between',
};

type DraftFilter = {
    /** Empty until the user picks a column. */
    column: string;
    operator: FilterOperator;
    value: string | [string, string];
};

type Props<Row> = {
    columns: Column<Row>[];
    operators: OperatorsByType;
    filters: TableFilter[];
    onChange: (filters: TableFilter[]) => void;
};

const emptyValue = (operator: FilterOperator): DraftFilter['value'] => (operator === 'between' ? ['', ''] : '');

/**
 * Button with a popover to build filters: each row is column → operator →
 * value, with an input that fits the column type (text, number, date, yes/no,
 * list; `between` shows two fields). Nothing is applied until "Apply", and only
 * complete rows are applied. Operators come from the server (`operators`).
 *
 * @example
 * <DataTableFilters columns={columns} operators={controller.operators}
 *     filters={controller.state.filters} onChange={controller.setFilters} />
 */
export function DataTableFilters<Row>({ columns, operators, filters, onChange }: Props<Row>) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState<DraftFilter[]>([]);

    const filterable = columns.filter((c) => c.filterable);

    const handleOpenChange = (next: boolean) => {
        if (next) {
            setDraft(filters.map((f) => ({ ...f })));
        }

        setOpen(next);
    };

    const update = (index: number, patch: Partial<DraftFilter>) =>
        setDraft((rows) => rows.map((row, i) => (i === index ? { ...row, ...patch } : row)));

    const changeColumn = (index: number, key: string) => {
        const column = filterable.find((c) => c.key === key);
        const operator = column ? operators[column.type][0] : 'eq';

        update(index, { column: key, operator, value: emptyValue(operator) });
    };

    const changeOperator = (index: number, operator: FilterOperator) => {
        const current = draft[index].value;
        const keep = (operator === 'between') === Array.isArray(current);

        update(index, { operator, value: keep ? current : emptyValue(operator) });
    };

    const apply = () => {
        onChange(draft.filter((f) => f.column !== '' && isFilterComplete(f)));
        setOpen(false);
    };

    return (
        <Popover open={open} onOpenChange={handleOpenChange}>
            <PopoverTrigger asChild>
                <Button variant="outline" size="sm">
                    <Filter aria-hidden />
                    {t('Filters')}
                    {filters.length > 0 && <Badge variant="secondary">{filters.length}</Badge>}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-[min(40rem,calc(100vw-2rem))]">
                <div className="flex flex-col gap-3">
                    {draft.length === 0 && <p className="text-sm text-muted-foreground">{t('No filters yet.')}</p>}
                    {draft.map((row, index) => {
                        const column = filterable.find((c) => c.key === row.column);

                        return (
                            <div key={index} className="flex flex-wrap items-center gap-2" data-testid="filter-row">
                                <Select value={row.column} onValueChange={(key) => changeColumn(index, key)}>
                                    <SelectTrigger size="sm" aria-label={t('Column')} className="w-40">
                                        <SelectValue placeholder={t('Column')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {filterable.map((c) => (
                                            <SelectItem key={c.key} value={c.key}>
                                                {t(c.header)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {column && (
                                    <>
                                        <Select
                                            value={row.operator}
                                            onValueChange={(op) => changeOperator(index, op as FilterOperator)}
                                        >
                                            <SelectTrigger size="sm" aria-label={t('Operator')} className="w-36">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {operators[column.type].map((op) => (
                                                    <SelectItem key={op} value={op}>
                                                        {t(OPERATOR_LABELS[op])}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <FilterValueInput
                                            column={column}
                                            operator={row.operator}
                                            value={row.value}
                                            onChange={(value) => update(index, { value })}
                                        />
                                    </>
                                )}
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label={t('Remove filter')}
                                    onClick={() => setDraft((rows) => rows.filter((_, i) => i !== index))}
                                >
                                    <X aria-hidden />
                                </Button>
                            </div>
                        );
                    })}
                    <div className="flex items-center justify-between gap-2">
                        <Button
                            variant="ghost"
                            size="sm"
                            disabled={filterable.length === 0}
                            onClick={() => setDraft((rows) => [...rows, { column: '', operator: 'eq', value: '' }])}
                        >
                            <Plus aria-hidden />
                            {t('Add filter')}
                        </Button>
                        <Button size="sm" onClick={apply}>
                            {t('Apply')}
                        </Button>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}

type ValueInputProps<Row> = {
    column: Column<Row>;
    operator: FilterOperator;
    value: DraftFilter['value'];
    onChange: (value: DraftFilter['value']) => void;
};

/** Input element for a filter value, chosen by the column type. */
function FilterValueInput<Row>({ column, operator, value, onChange }: ValueInputProps<Row>) {
    const { t } = useTranslation();

    if (operator === 'between' && Array.isArray(value)) {
        const type = column.type === 'date' ? 'date' : 'number';

        return (
            <>
                <Input
                    type={type}
                    aria-label={t('From')}
                    placeholder={t('From')}
                    className="h-8 w-32"
                    value={value[0]}
                    onChange={(e) => onChange([e.target.value, value[1]])}
                />
                <Input
                    type={type}
                    aria-label={t('To')}
                    placeholder={t('To')}
                    className="h-8 w-32"
                    value={value[1]}
                    onChange={(e) => onChange([value[0], e.target.value])}
                />
            </>
        );
    }

    const single = Array.isArray(value) ? '' : value;

    if (column.type === 'boolean' || column.type === 'select') {
        const options =
            column.type === 'boolean'
                ? [
                      { value: 'true', label: 'Yes' },
                      { value: 'false', label: 'No' },
                  ]
                : (column.options ?? []);

        return (
            <Select value={single} onValueChange={onChange}>
                <SelectTrigger size="sm" aria-label={t('Value')} className="w-36">
                    <SelectValue placeholder={t('Value')} />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {t(option.label)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        );
    }

    return (
        <Input
            type={column.type === 'number' ? 'number' : column.type === 'date' ? 'date' : 'text'}
            aria-label={t('Value')}
            placeholder={t('Value')}
            className="h-8 w-40"
            value={single}
            onChange={(e) => onChange(e.target.value)}
        />
    );
}

/** Human-readable value of a filter for its chip. */
function describeValue<Row>(column: Column<Row> | undefined, filter: TableFilter, t: (key: string) => string): string {
    const show = (value: string) => {
        if (column?.type === 'boolean') {
            return t(value === 'true' ? 'Yes' : 'No');
        }

        const option = column?.options?.find((o) => o.value === value);

        return option ? t(option.label) : value;
    };

    return Array.isArray(filter.value) ? `${show(filter.value[0])} – ${show(filter.value[1])}` : show(filter.value);
}

type ChipsProps<Row> = {
    columns: Column<Row>[];
    filters: TableFilter[];
    onChange: (filters: TableFilter[]) => void;
};

/**
 * Chips for the active filters, each removable on its own. Shown even for
 * filters on hidden columns, which stay in effect.
 *
 * @example
 * <DataTableFilterChips columns={columns} filters={controller.state.filters} onChange={controller.setFilters} />
 */
export function DataTableFilterChips<Row>({ columns, filters, onChange }: ChipsProps<Row>) {
    const { t } = useTranslation();

    if (filters.length === 0) {
        return null;
    }

    return (
        <ul className="flex flex-wrap gap-2" aria-label={t('Active filters')}>
            {filters.map((filter, index) => {
                const column = columns.find((c) => c.key === filter.column);
                const label = `${t(column?.header ?? filter.column)} ${t(OPERATOR_LABELS[filter.operator]).toLowerCase()} ${describeValue(column, filter, t)}`;

                return (
                    <li key={`${filter.column}-${index}`}>
                        <Badge variant="secondary" className="h-6 gap-1 pr-1" data-testid="filter-chip">
                            {label}
                            <button
                                type="button"
                                className="rounded-sm p-0.5 hover:bg-foreground/10"
                                aria-label={t('Remove filter :name', { name: label })}
                                onClick={() => onChange(filters.filter((_, i) => i !== index))}
                            >
                                <X className="size-3" aria-hidden />
                            </button>
                        </Badge>
                    </li>
                );
            })}
        </ul>
    );
}
