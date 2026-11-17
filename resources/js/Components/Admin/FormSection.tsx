import type { HTMLAttributes, ReactNode } from 'react';

import { cn } from '@/lib/utils';

type Props = Omit<HTMLAttributes<HTMLElement>, 'title'> & {
    /** Renders as a landmark `section` (use with a `title`, which then labels it) or as a plain `div` (default). */
    as?: 'div' | 'section';
    /** Section heading (`h2`). Its id is `titleId`; for `as="section"` the section is labelled by it. */
    title?: ReactNode;
    /** Id of the heading; required for `aria-labelledby` and for specs that look the heading up. */
    titleId?: string;
    /** Muted text under the heading. */
    description?: ReactNode;
};

/**
 * The bordered white panel that groups the fields of a form (one look for every form: same border, radius, padding and
 * `--card` surface on the muted page). Pass spacing and width through `className` (for example `mb-6 max-w-xl space-y-4`);
 * `data-testid` and other attributes go to the element as they are.
 *
 * @example
 * <FormSection as="section" title={t('Public display')} titleId="public-display-heading" className="mb-6 max-w-xl space-y-6">
 *     ...fields
 * </FormSection>
 *
 * @example
 * <FormSection className="mb-6 max-w-xl space-y-4">
 *     <Label htmlFor="name">{t('Name')}</Label>
 *     <Input id="name" />
 * </FormSection>
 */
export function FormSection({ as: Tag = 'div', title, titleId, description, className, children, ...rest }: Props) {
    return (
        <Tag aria-labelledby={Tag === 'section' && title ? titleId : undefined} className={cn('rounded-md border bg-card p-4', className)} {...rest}>
            {title && (
                <h2 id={titleId} className="text-lg font-semibold">
                    {title}
                </h2>
            )}
            {description && <p className="text-sm text-muted-foreground">{description}</p>}
            {children}
        </Tag>
    );
}
