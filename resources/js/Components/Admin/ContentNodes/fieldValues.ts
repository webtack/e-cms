import type { NodeField, NodeFieldItem, NodeFieldValue, NodeFileMeta, NodeFiles } from './types';

/**
 * The starting form state of a field: a list for a multiple field, `false` for a boolean, `''` for no value, an object
 * for a `youtube` or `url` value.
 *
 * @example
 * initialValue(field, ['a', 'b']); // ['a', 'b'] for a multiple string field
 * initialValue(field, null);       // '' for a single string field
 */
export function initialValue(field: NodeField, stored: unknown): NodeFieldValue {
    const text = (value: unknown) => (value == null ? '' : String(value));
    const one = (value: unknown): NodeFieldItem => {
        if (field.type === 'youtube') {
            const stored = (value ?? {}) as Record<string, unknown>;

            return { url: text(stored.url), title: text(stored.title), description: text(stored.description), screen: text(stored.screen) };
        }

        if (field.type === 'url') {
            const stored = (value ?? {}) as Record<string, unknown>;

            return { url: text(stored.url), title: text(stored.title) };
        }

        return field.type === 'boolean' ? value === true : text(value);
    };

    return field.is_multiple ? (Array.isArray(stored) ? stored.map(one) : []) : one(stored);
}

/**
 * The starting `file_meta` state of a form: the name and description (`''` for none) of every known file by its uuid.
 * In a translation mode the server sends the prefilled (translated, else base) name and description of the language.
 *
 * @example
 * initialFileMeta({ '0190….png': { uuid: '0190…', name: 'cover.png', description: null, … } }); // { '0190…': { name: 'cover.png', description: '' } }
 */
export function initialFileMeta(files: NodeFiles): Record<string, NodeFileMeta> {
    return Object.fromEntries(Object.values(files).map((file) => [file.uuid, { name: file.name, description: file.description ?? '' }]));
}

/**
 * The `file_meta` a translation sends: the display name and the description of the files that are in the form's values
 * now, whether or not their field is translatable. The server stores a value only when it differs from the base one.
 *
 * @param values Form values by field key; `image` and `file` fields hold `{uuid}.{ext}` or a list of them.
 *
 * @example
 * translationFileMeta(fields, { cover: '0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png' }, meta); // { '0190a1b27c3e7f0a8b1c2d3e4f5a6b7c': { name: 'Cover', description: 'Front cover' } }
 */
export function translationFileMeta(fields: NodeField[], values: Record<string, unknown>, meta: Record<string, NodeFileMeta>): Record<string, NodeFileMeta> {
    const uuids = fields
        .filter((field) => field.type === 'image' || field.type === 'file')
        .flatMap((field) => [values[field.key]].flat())
        .filter((value): value is string => typeof value === 'string' && value !== '')
        .map((value) => value.slice(0, 32));

    return Object.fromEntries([...new Set(uuids)].filter((uuid) => meta[uuid] !== undefined).map((uuid) => [uuid, { name: meta[uuid].name, description: meta[uuid].description }]));
}
