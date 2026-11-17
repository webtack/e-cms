import type { ReactNode } from 'react';

type Props = {
    /** Stable name of the panel (`seo`, `section`): the test id is `{name}-panel`, the heading id `{name}-panel-title`. */
    name: string;
    /** The panel's heading. */
    title: string;
    /** One line under the heading saying what the panel is for. */
    description?: string;
    /** The framed fields (`NodeFieldFrame`). */
    children: ReactNode;
};

/**
 * A titled group of fields set apart from the main ones: the form of the "Section" and "SEO" tabs of a content type. It
 * only draws the group; the fields in it are the usual `NodeFieldFrame`s, so values, validation and errors work
 * exactly as for the main fields. (The "SEO" of the node form is an accordion section, see `ContentNodeForm`.)
 *
 * @example
 * <NodeFieldsPanel name="seo" title={t('SEO')} description={t('Shown to search engines and in links.')}>
 *     {fields.map((field) => <NodeFieldFrame key={field.key} field={field} … />)}
 * </NodeFieldsPanel>
 */
export function NodeFieldsPanel({ name, title, description, children }: Props) {
    return (
        <section aria-labelledby={`${name}-panel-title`} data-testid={`${name}-panel`} className="min-w-0 space-y-4 rounded-lg border bg-card p-4">
            <div className="space-y-1">
                <h2 id={`${name}-panel-title`} className="text-base font-semibold">
                    {title}
                </h2>
                {description && <p className="text-sm text-muted-foreground">{description}</p>}
            </div>
            {children}
        </section>
    );
}
