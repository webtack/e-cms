/**
 * The section of the admin a type page belongs to. The server sends it as the `section` prop:
 * `/admin/content-types/**` serves kind `content`, `/admin/page-types/**` serves kind `page`,
 * `/admin/block-types/**` serves kind `block`.
 */
export type TypeSection = {
    kind: 'content' | 'page' | 'block';
    /** Address of the section list, without a trailing slash (e.g. `/admin/page-types`). */
    basePath: string;
};

/** Translation keys of the texts that name the kind of a type. */
export type SectionLabels = {
    list: string;
    create: string;
    edit: string;
    deleteTitle: string;
    deleteConfirm: string;
    sections: string;
};

const labels: Record<TypeSection['kind'], SectionLabels> = {
    content: {
        list: 'Content Types',
        create: 'Create content type',
        edit: 'Edit content type',
        deleteTitle: 'Delete content type',
        deleteConfirm: 'Delete the content type “:name”? Its fields will be deleted too. This cannot be undone.',
        sections: 'Content type sections',
    },
    page: {
        list: 'Page Types',
        create: 'Create page type',
        edit: 'Edit page type',
        deleteTitle: 'Delete page type',
        deleteConfirm: 'Delete the page type “:name”? Its fields will be deleted too. This cannot be undone.',
        sections: 'Page type sections',
    },
    block: {
        list: 'Block Types',
        create: 'Create block type',
        edit: 'Edit block type',
        deleteTitle: 'Delete block type',
        deleteConfirm: 'Delete the block type “:name”? Its fields will be deleted too. This cannot be undone.',
        sections: 'Block type sections',
    },
};

/**
 * Translation keys for the texts of a section; callers pass them through `t()`.
 *
 * @example
 * t(sectionLabels('page').create); // "Create page type"
 */
export function sectionLabels(kind: TypeSection['kind']): SectionLabels {
    return labels[kind];
}

/**
 * Public list page of a type, `/{slug}`, or `null` when the type has none: only a `content` type with a non-empty slug
 * is shown on the site. The section menu and the row menu of the types table both use it for their "Preview" link.
 *
 * @example
 * typePublicUrl({ kind: 'content', basePath: '/admin/content-types' }, 'news'); // "/news"
 * typePublicUrl({ kind: 'page', basePath: '/admin/page-types' }, null); // null
 */
export function typePublicUrl(section: TypeSection, slug: string | null | undefined): string | null {
    return section.kind === 'content' && slug ? `/${slug}` : null;
}
