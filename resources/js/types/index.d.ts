import type { PageProps as InertiaPageProps } from '@inertiajs/core';

/** Level of a type or of the media library; `own` is limited to the user's own records. */
export type AccessLevel = 'none' | 'own' | 'any';

/**
 * Permissions of the current user, shared by `HandleInertiaRequests`. Used only to show or hide elements: the
 * server decides every request again (see docs/access-control.md).
 */
export type Permissions = {
    system: { users: boolean; roles: boolean; menus: boolean; settings: boolean; taxonomy: boolean; types: boolean };
    /** Level per content type key. */
    nodes: Record<string, AccessLevel>;
    /** Whether the user has `own` or `any` on at least one type of the kind. */
    kinds: { content: boolean; page: boolean; block: boolean };
    media: AccessLevel;
    /** Whether the user may write anything (a type or media). */
    edit: boolean;
};

export type AuthUser = {
    id: number;
    name: string;
    email: string;
    /** URL of the user's avatar, or `null` (the initials are shown). */
    avatar: string | null;
    can: Permissions;
};

/**
 * A link in the favorites or the admin menu (already filtered by the user's access): to the node list of a type, or,
 * when `node` is true, to the edit form of one node.
 */
export type NavigationLink = {
    type?: 'link';
    /** `true` for a link to a node: `key` is then the node uuid and `name` its title. */
    node?: true;
    key: string;
    name: string;
    /** Kind of the type (of the node's type for a node link). */
    kind: 'content' | 'page' | 'block';
    href: string;
};

/** A group of the admin menu; its `title` is already in the user's language. */
export type NavigationGroup = {
    type: 'group';
    id: number;
    title: string;
    children: NavigationLink[];
};

/** The favorites of the user and the admin menu shared by `HandleInertiaRequests` (`null` for a guest). */
export type NavigationProps = {
    favorites: NavigationLink[];
    menu: (NavigationLink | NavigationGroup)[];
};

export type LocaleOption = {
    code: string;
    name: string;
};

/** Interface language state shared by `HandleInertiaRequests`. */
export type LocaleProps = {
    current: string;
    available: LocaleOption[];
    /** Contents of `lang/{current}.json`: English phrase → translation. */
    messages: Record<string, string>;
};

/** Props shared with every page by `HandleInertiaRequests`. */
export type SharedProps = {
    auth: { user: AuthUser | null };
    locale: LocaleProps;
    navigation: NavigationProps | null;
    /** Site name from the site settings in the current language, or the application name; available to guests too. */
    site: { name: string };
    /** Rich text settings from `config/content.php`: where an iframe may point (host with an optional path prefix). */
    richtext: { iframeHosts: string[] };
    /** `id` is unique per response that carries a message (`null` when there is none); it triggers the toast. */
    flash: { id: string | null; success: string | null; error: string | null };
};

declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: SharedProps;
    }
}
