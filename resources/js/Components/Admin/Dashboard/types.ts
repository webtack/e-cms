/** Kind of a content type; decides the admin section of its nodes. */
export type NodeKind = 'content' | 'page' | 'block';

/** A node row of a dashboard list (`DashboardData::row()`). */
export type DashboardNode = {
    uuid: string;
    title: string;
    type: { key: string; name: string; kind: NodeKind };
    /** Name of the author, `null` when the user is gone. */
    author: string | null;
    views: number;
    /** ISO 8601 moments. */
    updated_at: string | null;
    published_at: string | null;
    unpublished_at: string | null;
    /** The edit form address; `null` when the user may not edit the node (the row is shown without a link). */
    edit_url: string | null;
};

export type QuickCreateType = { key: string; name: string; kind: NodeKind; url: string };

export type FavoriteTile = { key: string; name: string; kind: NodeKind; href: string; count: number };

export type CounterRow = { key: string; name: string; kind: NodeKind; href: string; total: number; published: number; drafts: number };

export type MediaSummary = {
    count: number;
    /** Total size in bytes. */
    size: number;
    latest: { uuid: string; name: string; size: number; created_at: string | null; url: string }[];
};

/** Props of `Pages/Admin/Dashboard.tsx` (`DashboardData::forUser()`). */
export type DashboardProps = {
    recent: DashboardNode[];
    drafts: DashboardNode[];
    create: QuickCreateType[];
    /** `true` when the administrator configured the Create menu: render `create` as one flat list in the given order. */
    create_flat: boolean;
    favorites: FavoriteTile[];
    counters: CounterRow[];
    top: DashboardNode[];
    scheduled: DashboardNode[];
    expired: DashboardNode[];
    /** `null` when the media level is `none`. */
    media: MediaSummary | null;
};
