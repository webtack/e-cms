import type { ReactNode } from 'react';

import { FormSection } from '@/Components/Admin/FormSection';

type Props = {
    /** Widget heading. */
    title: string;
    /** Stable id of the widget (`dashboard-{id}`) used for the heading id and `data-testid`. */
    id: string;
    children: ReactNode;
};

/**
 * The card every dashboard widget sits in: a labelled section with a heading.
 *
 * @example
 * <DashboardWidget id="recent" title={t('Recent changes')}>...</DashboardWidget>
 */
export function DashboardWidget({ title, id, children }: Props) {
    return (
        <FormSection as="section" title={title} titleId={`dashboard-${id}-heading`} data-testid={`dashboard-${id}`} className="min-w-0 space-y-3">
            {children}
        </FormSection>
    );
}
