import { Link, usePage } from '@inertiajs/react';
import { ChevronRight, Eye } from 'lucide-react';
import { Fragment, type ReactNode } from 'react';

import { PageTitle } from '@/Components/Admin/PageTitle';
import { sectionLabels, type TypeSection, typePublicUrl } from '@/Components/Admin/ContentTypes/section';
import { typeSectionItems } from '@/Components/Admin/ContentTypes/typeSectionItems';
import { cn } from '@/lib/utils';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * The `type` prop the server builds for the section menu (`ContentType::navProps()`): the identity of the type, the
 * number of its direct child types and its ancestors from the root down to the direct parent.
 */
export type NavType = {
    key: string;
    name: string;
    slug: string | null;
    children_count: number;
    ancestors: { key: string; name: string }[];
};

type Props = {
    section: TypeSection;
    /** The type being managed; `name` is the heading, `slug` (when set) gives the "Preview" link. */
    type: NavType;
    /** Current section; the field create and edit pages belong to `fields`, the node pages to `nodes`. */
    active: 'settings' | 'display' | 'seo' | 'sitemap' | 'fields' | 'nodes' | 'children';
    /** Shown next to the heading (for example the favorites star of the node list). */
    children?: ReactNode;
};

/**
 * Section menu of an existing type (content, page or block, per `section`): a breadcrumb trail (`type-breadcrumbs`:
 * the list of types, then the ancestors from the root down, each linking to its settings page) and a
 * horizontal menu that switches between the destinations listed by `typeSectionItems(section.kind)` (settings, display,
 * SEO for content and page types, fields, nodes, child types). The row menu of the types table renders from the same
 * list. The child types tab (`child-types-tab`) is labelled with the number of direct children, "Child types (2)".
 * Without the `types` permission only the nodes tab is shown, and there is no trail.
 *
 * Above the tabs it shows the type name as the page heading (the page `h1`, with `children` beside it), and after the
 * last tab a "Preview" tab-styled link to the public list page (`typePublicUrl`), opened in a new tab; it needs no
 * permission and is absent when the type has no public address.
 *
 * @example
 * <ContentTypeNav section={section} type={type} active="fields" />
 * @example
 * <ContentTypeNav section={section} type={type} active="nodes">{favoriteStar}</ContentTypeNav>
 */
export function ContentTypeNav({ section, type, active, children }: Props) {
    const { t } = useTranslation();
    const { can } = usePage().props.auth.user!;
    const labels = sectionLabels(section.kind);
    const manageTypes = can.system.types;
    // Without the `types` permission only the nodes tab is reachable; the rest would answer 403.
    const items = typeSectionItems(section.kind)
        .filter((item) => manageTypes || item.id === 'nodes')
        .map((item) => ({
            id: item.id,
            label: item.id === 'children' ? t('Child types (:count)', { count: type.children_count }) : t(item.label),
            href: `${section.basePath}/${type.key}/${item.path}`,
        }));
    const publicUrl = typePublicUrl(section, type.slug);

    return (
        <div className="mb-6">
            {manageTypes && (
                <nav aria-label={t('Type hierarchy')} data-testid="type-breadcrumbs">
                    <ol className="-ml-1 flex flex-wrap items-center gap-1 text-sm text-muted-foreground">
                        <li>
                            <Link href={section.basePath} className="rounded px-1 hover:text-foreground">
                                {t(labels.list)}
                            </Link>
                        </li>
                        {type.ancestors.map((ancestor) => (
                            <Fragment key={ancestor.key}>
                                <li aria-hidden>
                                    <ChevronRight className="size-4" />
                                </li>
                                <li>
                                    <Link href={`${section.basePath}/${ancestor.key}/edit`} className="rounded px-1 hover:text-foreground">
                                        {ancestor.name}
                                    </Link>
                                </li>
                            </Fragment>
                        ))}
                    </ol>
                </nav>
            )}
            <div className="mt-2 mb-4 flex items-center gap-2">
                <PageTitle className="min-w-0 truncate">{type.name}</PageTitle>
                {children}
            </div>
            <nav aria-label={t(labels.sections)} className="flex flex-wrap items-center gap-x-3 border-b sm:gap-x-4">
                {items.map((item) => (
                    <Link
                        key={item.id}
                        href={item.href}
                        aria-current={item.id === active ? 'page' : undefined}
                        data-testid={item.id === 'children' ? 'child-types-tab' : undefined}
                        className={cn(
                            '-mb-px border-b-2 px-1 pb-2 text-sm font-medium transition-colors',
                            item.id === active
                                ? 'border-primary text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {item.label}
                    </Link>
                ))}
                {publicUrl && (
                    <a
                        href={publicUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="-mb-px flex items-center gap-1 border-b-2 border-transparent px-1 pb-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                    >
                        <Eye className="size-4" aria-hidden />
                        {/* Icon only on narrow screens so the tab row keeps fitting the page width. */}
                        <span className="sr-only sm:not-sr-only">{t('Preview')}</span>
                    </a>
                )}
            </nav>
        </div>
    );
}
