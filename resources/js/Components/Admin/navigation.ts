import { Blocks, FileStack, Image, LayoutDashboard, Menu, ShieldCheck, Table2, Tags, Users, Globe, type LucideIcon } from 'lucide-react';

import type { Permissions } from '@/types';

export type NavItem = {
    /** Translation key (English phrase); components translate it with `t()`. */
    title: string;
    href: string;
    icon: LucideIcon;
    /** Mock items have no backend route yet; they render as disabled links. */
    mock?: boolean;
    /** Whether the item is shown to a user with these permissions; absent means shown to everyone. */
    visible?: (can: Permissions) => boolean;
};

/** A titled section of the sidebar root holding management items. */
export type NavGroup = {
    /** Translation key of the group heading. */
    title: string;
    items: NavItem[];
};

/**
 * Top-level items of the sidebar that are plain links. The favorites and the admin menu sit between Dashboard and
 * Media Library in `AppSidebar`; the sections (see {@link sections}) follow them.
 *
 * To add a menu item, append an entry here or to a section of `sections` (see docs/admin-shell.md).
 */
export const navigation: NavItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard', icon: LayoutDashboard },
    { title: 'Media Library', href: '/admin/media', icon: Image, visible: (can) => can.media !== 'none' },
];

/**
 * The sections shown in the sidebar root: management of the structure and of access. Each item keeps its own
 * `visible` condition; a section without visible items is hidden.
 */
export const sections: NavGroup[] = [
    {
        title: 'Structure',
        items: [
            // A type section is shown to who manages types or who may write the nodes of at least one type of the kind.
            { title: 'Content Types', href: '/admin/content-types', icon: Table2, visible: (can) => can.system.types || can.kinds.content },
            { title: 'Page Types', href: '/admin/page-types', icon: FileStack, visible: (can) => can.system.types || can.kinds.page },
            { title: 'Block Types', href: '/admin/block-types', icon: Blocks, visible: (can) => can.system.types || can.kinds.block },
            { title: 'Taxonomy', href: '/admin/taxonomy', icon: Tags, visible: (can) => can.system.taxonomy },
            { title: 'Menus', href: '/admin/menus', icon: Menu, visible: (can) => can.system.menus },
        ],
    },
    {
        title: 'Access',
        items: [
            { title: 'Users', href: '/admin/users', icon: Users, visible: (can) => can.system.users },
            { title: 'Roles', href: '/admin/roles', icon: ShieldCheck, visible: (can) => can.system.roles },
            { title: 'Site', href: '/admin/settings', icon: Globe, visible: (can) => can.system.settings },
        ],
    },
];

/** Every leaf of the sidebar, top-level items and section items alike; breadcrumbs and active states search it. */
const leaves: NavItem[] = [...navigation, ...sections.flatMap((group) => group.items)];

/**
 * The top-level items a user may see. Hiding is a convenience: the server answers 403 to a forbidden address anyway.
 *
 * @example
 * visibleNavigation(auth.user.can).map((item) => item.title); // ['Dashboard', 'Media Library']
 */
export function visibleNavigation(can: Permissions): NavItem[] {
    return navigation.filter((item) => item.visible?.(can) ?? true);
}

/**
 * The sections with only the items a user may see; sections left empty are dropped.
 *
 * @example
 * visibleSections({ ...can, system: { ...can.system, taxonomy: true } }); // [{ title: 'Structure', items: [Taxonomy] }]
 */
export function visibleSections(can: Permissions): NavGroup[] {
    return sections
        .map((group) => ({ ...group, items: group.items.filter((item) => item.visible?.(can) ?? true) }))
        .filter((group) => group.items.length > 0);
}

/** Titles (translation keys) for routes that are not sidebar items. */
export const extraTitles: Record<string, string> = {
    '/admin/profile': 'Profile',
    '/admin/ui/data-table': 'Data table demo',
    '/admin/ui/file-upload': 'File upload demo',
    '/admin/content-types/create': 'Create content type',
    '/admin/page-types/create': 'Create page type',
    '/admin/block-types/create': 'Create block type',
    '/admin/taxonomy/create': 'Create vocabulary',
    '/admin/menus/create': 'Create menu',
    '/admin/users/create': 'Create user',
    '/admin/roles/create': 'Create role',
    // A sub-page of Site (`/admin/settings`), reached from the sidebar footer link.
    '/admin/settings/navigation': 'Admin menu',
    // A sub-page of Site, reached from a link on the Site page.
    '/admin/settings/languages': 'Languages',
};

/** Titles for routes with a parameter in the path; matched after `extraTitles`. */
const patternTitles: { pattern: RegExp; title: string }[] = [
    { pattern: /^\/admin\/content-types\/[^/]+\/edit$/, title: 'Edit content type' },
    { pattern: /^\/admin\/page-types\/[^/]+\/edit$/, title: 'Edit page type' },
    { pattern: /^\/admin\/block-types\/[^/]+\/edit$/, title: 'Edit block type' },
    { pattern: /^\/admin\/(content|page|block)-types\/[^/]+\/fields$/, title: 'Fields' },
    { pattern: /^\/admin\/(content|page|block)-types\/[^/]+\/fields\/create$/, title: 'Create field' },
    { pattern: /^\/admin\/(content|page|block)-types\/[^/]+\/fields\/[^/]+\/edit$/, title: 'Edit field' },
    { pattern: /^\/admin\/(content|page|block)-types\/[^/]+\/nodes$/, title: 'Nodes' },
    { pattern: /^\/admin\/(content|page|block)-types\/[^/]+\/nodes\/create$/, title: 'Create node' },
    { pattern: /^\/admin\/(content|page|block)-types\/[^/]+\/nodes\/[^/]+\/edit$/, title: 'Edit node' },
    { pattern: /^\/admin\/taxonomy\/[^/]+\/edit$/, title: 'Edit vocabulary' },
    { pattern: /^\/admin\/taxonomy\/[^/]+\/terms$/, title: 'Terms' },
    { pattern: /^\/admin\/taxonomy\/[^/]+\/terms\/create$/, title: 'Create term' },
    { pattern: /^\/admin\/taxonomy\/[^/]+\/terms\/[^/]+\/edit$/, title: 'Edit term' },
    { pattern: /^\/admin\/users\/[^/]+\/edit$/, title: 'Edit user' },
    { pattern: /^\/admin\/roles\/[^/]+\/edit$/, title: 'Edit role' },
    { pattern: /^\/admin\/menus\/[^/]+\/edit$/, title: 'Edit menu' },
    { pattern: /^\/admin\/menus\/[^/]+\/items$/, title: 'Menu items' },
    { pattern: /^\/admin\/menus\/[^/]+\/items\/create$/, title: 'Create menu item' },
    { pattern: /^\/admin\/menus\/[^/]+\/items\/[^/]+\/edit$/, title: 'Edit menu item' },
];

/** Whether `url` (path with optional query) belongs to the nav item `href`. */
export function isActive(url: string, href: string): boolean {
    const path = url.split('?')[0];

    return path === href || path.startsWith(`${href}/`);
}

/**
 * The sidebar leaf that owns `url`: the one with the longest matching address, so nested sections resolve to the
 * most specific item.
 *
 * @example
 * activeItem('/admin/settings/navigation')?.title; // 'Site'
 * activeItem('/admin/taxonomy/news/terms')?.title; // 'Taxonomy'
 */
export function activeItem(url: string): NavItem | undefined {
    return leaves
        .filter((entry) => isActive(url, entry.href))
        .sort((a, b) => b.href.length - a.href.length)[0];
}

/**
 * Builds breadcrumb entries for a URL: always "Admin", followed by the
 * page title resolved from `navigation` / `extraTitles` / `patternTitles`. A page listed in
 * `extraTitles` or `patternTitles` that lies under a menu item gets that item as a link in between.
 * Titles are translation keys; the caller translates them, so this stays a pure function.
 *
 * @example
 * getBreadcrumbs('/admin/dashboard'); // [{ title: 'Admin', href: '/admin/dashboard' }, { title: 'Dashboard' }]
 *
 * @example
 * getBreadcrumbs('/admin/content-types/news/edit');
 * // Admin → Content Types (link) → Edit content type
 *
 * @example
 * getBreadcrumbs('/admin/content-types/news/fields/views/edit');
 * // Admin → Content Types (link) → Edit field
 *
 * @example
 * getBreadcrumbs('/admin/content-types/news/nodes/create');
 * // Admin → Content Types (link) → Create node
 *
 * @example
 * getBreadcrumbs('/admin/page-types/home/nodes/create');
 * // Admin → Page Types (link) → Create node
 *
 * @example
 * getBreadcrumbs('/admin/taxonomy/categories/terms');
 * // Admin → Taxonomy (link) → Terms
 *
 * @example
 * getBreadcrumbs('/admin/menus/main/items');
 * // Admin → Menus (link) → Menu items
 */
export function getBreadcrumbs(url: string): { title: string; href?: string }[] {
    const path = url.split('?')[0];
    const item = activeItem(path);
    const nested = extraTitles[path] ?? patternTitles.find(({ pattern }) => pattern.test(path))?.title;

    if (nested) {
        return [
            { title: 'Admin', href: '/admin/dashboard' },
            ...(item ? [{ title: item.title, href: item.href }] : []),
            { title: nested },
        ];
    }

    const title = item?.title;

    return title ? [{ title: 'Admin', href: '/admin/dashboard' }, { title }] : [{ title: 'Admin' }];
}
