import type { FieldsetHTMLAttributes, ReactNode } from 'react';

import { cn } from '@/lib/utils';

type Props = Omit<FieldsetHTMLAttributes<HTMLFieldSetElement>, 'title'> & {
    /** Caption of the group; it becomes the group's accessible name. */
    legend: ReactNode;
};

/**
 * A bordered group of related controls inside a form section: a `fieldset` with a `legend`, so assistive
 * technology announces it as a group named by the legend. Use it for related controls (a list of check boxes, a
 * field/direction pair, repeated rows) instead of an unbordered heading. Spacing between children defaults to
 * `space-y-2` and can be replaced through `className`; other attributes (`data-testid`, `disabled`) go to the `fieldset`.
 *
 * @example
 * <FieldGroup legend={t('Search fields')}>
 *     <Checkbox id="search-title" />
 *     <Label htmlFor="search-title">{t('Title')}</Label>
 * </FieldGroup>
 */
export function FieldGroup({ legend, className, children, ...rest }: Props) {
    return (
        <fieldset className={cn('min-w-0 space-y-2 rounded-md border p-4 bg-card', className)} {...rest}>
            <legend className="px-1 text-sm font-medium -mb-1">{legend}</legend>
            {children}
        </fieldset>
    );
}
