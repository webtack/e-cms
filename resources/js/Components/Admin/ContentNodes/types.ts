/** A field of the node's type as the node form needs it (`ContentNodeController::formFields()`). */
export type NodeField = {
    key: string;
    /** The field's name; a label, shown as is. */
    name: string;
    /** Instruction for the editor, shown under the field's label; `null` when absent. */
    description?: string | null;
    /** Key of the value type from the server registry (`string`, `text`, `integer`, …). */
    type: string;
    is_multiple: boolean;
    /** Upper bound of the elements of a multiple field; `0` means no limit. */
    max_items: number;
    is_required: boolean;
    /** The panel that shows the field apart from the main ones: `seo` (metadata) in the node form, also `section` on the type settings page; absent for a main field. */
    panel?: 'seo' | 'section';
    /** Translation mode only: whether the field has translations; the others are shown disabled with their base value. */
    translatable?: boolean;
    /** `min` / `max` for input attribute hints and, for a file field, the effective `mimes` and `max_size` (KB); the server stays the judge. */
    settings: {
        min?: number | string;
        max?: number | string;
        mimes?: string[];
        max_size?: number;
        terms?: NodeTermOption[];
        /** `type_ref`: the kind of the content types the field may point at. */
        kind?: string;
        /** `node_ref`: the key of the content type of the nodes the field may point at. */
        content_type?: string;
    };
};

/** A term a `term` field can take: its base-language name and its depth in the tree (a root has 1), for the indent. */
export type NodeTermOption = { uuid: string; name: string | null; depth: number };

/** The form state of a `youtube` value: the link, title and description, and the screenshot as `{uuid}.{ext}` (`''` for none). The video id is the server's business. */
export type YoutubeValue = { url: string; title: string; description: string; screen: string };

/** A `youtube` value with nothing in it (the form's state for no video). */
export const emptyYoutube = (): YoutubeValue => ({ url: '', title: '', description: '', screen: '' });

/** The form state of a `url` value: the link and the optional title (`''` for none). */
export type UrlValue = { url: string; title: string };

/** A `url` value with nothing in it (the form's state for no link). */
export const emptyUrl = (): UrlValue => ({ url: '', title: '' });

/** The form state of one element: a string, a boolean (checkbox) or, for a `youtube` / `url` field, an object. */
export type NodeFieldItem = string | boolean | YoutubeValue | UrlValue;

/** The form state of one field: a list for a multiple field, a scalar otherwise (`''` means no value). */
export type NodeFieldValue = NodeFieldItem | NodeFieldItem[];

/** Labels of the targets in the stored values of a node's reference fields, by stored value (a type key or a node uuid). */
export type NodeRefLabels = Record<string, string>;

/** The values of all fields of a node by field key, as the server sends them for the edit form. */
export type NodeValues = Record<string, unknown>;

/** What the form shows about a stored file, by its `{uuid}.{ext}` value (`ContentNodeController::fileInfo()`). */
export type NodeFileInfo = { uuid: string; name: string; description?: string | null; mime: string; size: number; url: string; preview?: string | null };

/** The editable metadata of a file as the form holds it: its name (`media.original_name`) and description (`''` for none). */
export type NodeFileMeta = { name: string; description: string };

/**
 * What a file input needs to edit the file's name and description: the metadata by file uuid, a change handler and
 * the server error of one of its fields (`file_meta.{uuid}.name`).
 */
export type NodeFileMetaEditor = {
    values: Record<string, NodeFileMeta>;
    onChange: (uuid: string, patch: Partial<NodeFileMeta>) => void;
    errorAt: (uuid: string, key: keyof NodeFileMeta) => string | undefined;
};

/** Known files by stored value: the ones sent with the page plus those uploaded in this form. */
export type NodeFiles = Record<string, NodeFileInfo>;

/** Service attributes of a node as the server sends them to the form (`ContentNodeController::create()/edit()`). */
export type NodeMeta = {
    /** Numeric database id; present when editing only (read-only display, never sent back). */
    id?: number;
    /** Present when editing; the node is addressed by it. */
    uuid?: string;
    slug: string;
    is_active: boolean;
    /** `YYYY-MM-DDTHH:mm`, UTC, no zone. */
    published_at: string;
    /** `YYYY-MM-DDTHH:mm`, UTC; `null` or absent when the node has no end date. */
    unpublished_at?: string | null;
    /** `YYYY-MM-DDTHH:mm`; `null` for a node that is not saved yet. */
    updated_at: string | null;
    author: { name: string; avatar: string | null } | null;
};

/** The prefilled values of the translatable fields of a node in the translation mode (`translation.values`), by field key. */
export type NodeTranslationValues = Record<string, unknown>;
