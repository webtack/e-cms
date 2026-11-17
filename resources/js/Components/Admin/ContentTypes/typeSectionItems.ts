import { Eye, FileText, ListTree, Map, Network, Pencil, Search, type LucideIcon } from 'lucide-react';

import type { TypeSection } from '@/Components/Admin/ContentTypes/section';

/** Identifier of a destination of an existing type. */
export type TypeSectionItemId = 'settings' | 'display' | 'seo' | 'sitemap' | 'fields' | 'nodes' | 'children';

/** One destination of an existing type, shared by the section tabs and the row menu. */
export type TypeSectionItem = {
    id: TypeSectionItemId;
    /** Translation key of the tab label; callers pass it through `t()`. */
    label: string;
    /** Translation key of the row menu item when it differs from `label`. */
    menuLabel?: string;
    /** Address suffix after `{basePath}/{typeKey}/`. */
    path: string;
    icon: LucideIcon;
};

const items: TypeSectionItem[] = [
    // The tab is "Settings"; the menu item is "Edit" because the main row button is already "Settings".
    { id: 'settings', label: 'Settings', menuLabel: 'Edit', path: 'edit', icon: Pencil },
    { id: 'display', label: 'Display', path: 'display', icon: Eye },
    { id: 'seo', label: 'SEO', path: 'seo', icon: Search },
    { id: 'sitemap', label: 'Sitemap', path: 'sitemap', icon: Map },
    { id: 'fields', label: 'Fields', path: 'fields', icon: ListTree },
    { id: 'nodes', label: 'Nodes', path: 'nodes', icon: FileText },
    // The tab label also carries the number of direct child types (see `ContentTypeNav`).
    { id: 'children', label: 'Child types', path: 'children', icon: Network },
];

/**
 * Ordered destinations of an existing type for a kind. Both `ContentTypeNav` (tabs) and `ContentTypeRowActions`
 * (row menu) render from this list, so a destination added here appears in both. A block type has no `seo` and
 * no `sitemap` destination (the routes answer 404). Permissions and hrefs are the caller's concern:
 * build the address as `${section.basePath}/${typeKey}/${item.path}`.
 *
 * @example
 * typeSectionItems('content').map((item) => item.id); // ['settings', 'display', 'seo', 'sitemap', 'fields', 'nodes', 'children']
 * typeSectionItems('block').map((item) => item.id); // ['settings', 'display', 'fields', 'nodes', 'children']
 */
export function typeSectionItems(kind: TypeSection['kind']): TypeSectionItem[] {
    return items.filter((item) => (item.id !== 'seo' && item.id !== 'sitemap') || kind !== 'block');
}
