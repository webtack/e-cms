import { Columns3 } from 'lucide-react';

import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { useTranslation } from '@/hooks/useTranslation';

import type { Column } from './types';

type Props<Row> = {
    columns: Column<Row>[];
    isVisible: (column: Column<Row>) => boolean;
    onToggle: (key: string) => void;
};

/**
 * Popover with a checkbox per column. A column marked `hideable: false` is
 * shown checked and disabled.
 *
 * @example
 * <DataTableColumnMenu columns={columns} isVisible={isVisible} onToggle={toggle} />
 */
export function DataTableColumnMenu<Row>({ columns, isVisible, onToggle }: Props<Row>) {
    const { t } = useTranslation();

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button variant="outline">
                    <Columns3 aria-hidden />
                    {t('Columns')}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-56">
                <ul className="flex flex-col gap-2" aria-label={t('Visible columns')}>
                    {columns.map((column) => (
                        <li key={column.key}>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={isVisible(column)}
                                    disabled={column.hideable === false}
                                    onCheckedChange={() => onToggle(column.key)}
                                />
                                {t(column.header)}
                            </label>
                        </li>
                    ))}
                </ul>
            </PopoverContent>
        </Popover>
    );
}
