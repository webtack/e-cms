import { Pencil } from 'lucide-react';
import { useState } from 'react';

import { FileUpload, type UploadedFile } from '@/Components/Admin/FileUpload/FileUpload';
import { useTranslation } from '@/hooks/useTranslation';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';

import { fetchRefOptions, RefAutocomplete } from './RefAutocomplete';
import { RichTextEditor } from './RichTextEditor';
import { UrlField } from './UrlField';
import { YoutubeField } from './YoutubeField';

import { Label } from '@/Components/ui/label';

import { emptyUrl, emptyYoutube } from './types';
import type { NodeField, NodeFieldItem, NodeFileMeta, NodeFileMetaEditor, NodeFiles, UrlValue, YoutubeValue } from './types';

/** The Select cannot hold an empty value, so "no term" travels under this placeholder. */
const NO_TERM = '__none__';

type Props = {
    field: NodeField;
    /** The element's DOM id; the label points at it. */
    id: string;
    /** A string for text-like inputs, a boolean for a checkbox, a `YoutubeValue` for `youtube`, a `UrlValue` for `url`. */
    value: unknown;
    onChange: (value: NodeFieldItem) => void;
    invalid?: boolean;
    /** Key of the content type that owns the field; the file widgets upload for the field by it. */
    typeKey: string;
    /** Known files by stored value, to show the name and the preview of a stored file (`image` / `file` fields). */
    files?: NodeFiles;
    /** Called with the file just uploaded by an `image` / `file` widget, so the form can show it. */
    onFile?: (file: UploadedFile) => void;
    /** Lets an `image` / `file` widget edit the name and description of its stored file; without it the block has no such fields. */
    fileMeta?: NodeFileMetaEditor;
    /** `false` hides the widget's own "Remove file" button (a row of a multiple field has its own remove button). Defaults to `true`. */
    removable?: boolean;
    /** Accessible name; needed when no `<label>` points at the element (items of a multiple field). */
    'aria-label'?: string;
};

/**
 * The input of one value of a node field, chosen by the field's value type. This is the only place that maps a
 * value type to a widget; an unknown type (a newly registered one) falls back to a plain text input.
 *
 * | type | widget |
 * | --- | --- |
 * | `string` | `Input` (`maxLength` from `settings.max`) |
 * | `text` | `Textarea` |
 * | `richtext` | `RichTextEditor` (Tiptap; the value is HTML, an empty editor gives `''`) |
 * | `integer` | `Input type=number step=1` (`min` / `max` from settings) |
 * | `decimal` | `Input inputMode=decimal` (text, so no precision is lost) |
 * | `boolean` | `Checkbox` |
 * | `date` | `Input type=date` |
 * | `datetime` | `Input type=datetime-local` |
 * | `youtube` | `YoutubeField` (a card with the screenshot, title link and description, with "Edit" / "Delete" buttons; adding and editing happen in a modal; the value is a `YoutubeValue`) |
 * | `url` | `UrlField` (two inline `Input`s, "Link" and "Title"; the value is a `UrlValue`) |
 * | `term` | `Select` of the terms of the field's vocabulary (`settings.terms`, in tree order, indented by depth; a single field also offers an empty choice to clear it; the value is the term's `uuid`). A multiple field is a list of such selects (`NodeFieldList`), in the order chosen |
 * | `type_ref` | `RefAutocomplete` over the content types of the kind in `settings.kind` (search by name on the server; the value is the type's `key`) |
 * | `node_ref` | `RefAutocomplete` over the nodes of the type in `settings.content_type` (search by title on the server; the value is the node's `uuid`) |
 * | `image`, `file` | `FileUpload` for the field (uploads at once; the value is `{uuid}.{ext}`; `accept` and `maxSize` come from the field's settings), with an "Edit file details" button (a modal with the "Name" and "Description" of the stored file) when `fileMeta` is given; the block shows the name and description from `fileMeta.values` (the form's state), so "Done" updates it at once |
 *
 * @example
 * <NodeFieldInput field={field} typeKey="news" id="price" value={data.values.price} onChange={(v) => setValue('price', v)} />
 */
export function NodeFieldInput({ field, typeKey, id, value, onChange, files, onFile, fileMeta, removable = true, invalid, 'aria-label': ariaLabel }: Props) {
    const { t } = useTranslation();
    const common = { id, 'aria-label': ariaLabel, 'aria-invalid': invalid || undefined };
    const text = typeof value === 'string' ? value : value == null || typeof value === 'boolean' ? '' : String(value);

    switch (field.type) {
        case 'image':
        case 'file': {
            const stored = typeof value === 'string' && value !== '' ? value : null;
            const info = stored === null ? null : (files?.[stored] ?? { uuid: stored.slice(0, 32), name: stored, mime: '', size: 0, url: '', preview: null });
            // The block shows the form's state (what "Done" applied), falling back to the known file when there is none.
            const draft = info !== null && fileMeta ? fileMeta.values[info.uuid] : undefined;
            const selected: UploadedFile | null =
                stored === null || info === null
                    ? null
                    : { uuid: info.uuid, profile: field.type === 'image' ? 'image' : 'document', name: draft?.name ?? info.name, mime: info.mime, size: info.size, width: null, height: null, value: stored, url: info.url, preview: info.preview ?? null };

            const metaErrors = selected && fileMeta ? (['name', 'description'] as const).map((key) => fileMeta.errorAt(selected.uuid, key)).filter(Boolean) : [];

            return (
                <>
                <FileUpload
                    field={{ contentType: typeKey, fieldKey: field.key }}
                    id={id}
                    aria-label={ariaLabel}
                    accept={field.settings.mimes?.join(',')}
                    maxSize={field.settings.max_size}
                    value={selected}
                    description={draft?.description}
                    removable={removable}
                    actions={selected && fileMeta ? <FileMetaDialog id={id} uuid={selected.uuid} editor={fileMeta} /> : undefined}
                    data-testid={`file-upload-${field.key}`}
                    onChange={(file) => {
                        if (file) {
                            onFile?.(file);
                        }
                        onChange(file ? file.value : '');
                    }}
                />
                {metaErrors.map((message) => (
                    <p key={message} role="alert" className="mt-1 text-sm text-destructive">
                        {message}
                    </p>
                ))}
                </>
            );
        }
        case 'youtube':
            return (
                <YoutubeField
                    field={field}
                    typeKey={typeKey}
                    id={id}
                    aria-label={ariaLabel}
                    invalid={invalid}
                    files={files}
                    onFile={onFile}
                    removable={removable}
                    value={value !== null && typeof value === 'object' ? (value as YoutubeValue) : emptyYoutube()}
                    onChange={onChange}
                />
            );
        case 'url':
            return (
                <UrlField
                    id={id}
                    aria-label={ariaLabel}
                    invalid={invalid}
                    value={value !== null && typeof value === 'object' ? (value as UrlValue) : emptyUrl()}
                    onChange={onChange}
                />
            );
        case 'type_ref':
            return (
                <RefAutocomplete
                    id={id}
                    aria-label={ariaLabel}
                    invalid={invalid}
                    clearable={removable}
                    value={text}
                    placeholder={t('Choose a content type')}
                    search={(query, signal) =>
                        fetchRefOptions<{ key: string; name: string }>(
                            `/admin/lookup/content-types?kind=${encodeURIComponent(field.settings.kind ?? '')}&q=${encodeURIComponent(query)}`,
                            signal,
                            (row) => ({ value: row.key, label: row.name, hint: row.key }),
                        )
                    }
                    onChange={onChange}
                />
            );
        case 'node_ref':
            return (
                <RefAutocomplete
                    id={id}
                    aria-label={ariaLabel}
                    invalid={invalid}
                    clearable={removable}
                    value={text}
                    placeholder={t('Choose a node')}
                    search={(query, signal) =>
                        fetchRefOptions<{ uuid: string; title: string }>(
                            `/admin/lookup/nodes?content_type=${encodeURIComponent(field.settings.content_type ?? '')}&q=${encodeURIComponent(query)}`,
                            signal,
                            (row) => ({ value: row.uuid, label: row.title }),
                        )
                    }
                    onChange={onChange}
                />
            );
        case 'term': {
            const terms = field.settings.terms ?? [];
            // A stored term that is no longer offered (its vocabulary changed or it is gone) still shows as chosen.
            const known = terms.some((term) => term.uuid === text);

            return (
                <Select value={text === '' ? NO_TERM : text} onValueChange={(next) => onChange(next === NO_TERM ? '' : next)}>
                    <SelectTrigger id={id} className="w-full" aria-label={ariaLabel} aria-invalid={invalid || undefined}>
                        <SelectValue placeholder={t('Choose a term')} />
                    </SelectTrigger>
                    <SelectContent>
                        {!field.is_multiple && <SelectItem value={NO_TERM}>{t('No term')}</SelectItem>}
                        {text !== '' && !known && <SelectItem value={text}>{text}</SelectItem>}
                        {terms.map((term) => (
                            <SelectItem key={term.uuid} value={term.uuid}>
                                <span style={{ paddingLeft: `${(term.depth - 1) * 1}rem` }}>{term.name}</span>
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            );
        }
        case 'text':
            return <Textarea {...common} rows={4} value={text} onChange={(e) => onChange(e.target.value)} />;
        case 'richtext':
            return <RichTextEditor contentType={typeKey} fieldKey={field.key} id={id} aria-label={ariaLabel} invalid={invalid} value={text} onChange={onChange} />;
        case 'integer':
            return (
                <Input
                    {...common}
                    type="number"
                    step={1}
                    min={field.settings.min}
                    max={field.settings.max}
                    value={text}
                    onChange={(e) => onChange(e.target.value)}
                />
            );
        case 'decimal':
            return <Input {...common} inputMode="decimal" value={text} onChange={(e) => onChange(e.target.value)} />;
        case 'boolean':
            return <Checkbox {...common} checked={value === true} onCheckedChange={(checked) => onChange(checked === true)} />;
        case 'date':
            return <Input {...common} type="date" value={text} onChange={(e) => onChange(e.target.value)} />;
        case 'datetime':
            return <Input {...common} type="datetime-local" value={text} onChange={(e) => onChange(e.target.value)} />;
        case 'string':
            return (
                <Input
                    {...common}
                    maxLength={field.settings.max === undefined ? undefined : Number(field.settings.max)}
                    value={text}
                    onChange={(e) => onChange(e.target.value)}
                />
            );
        default:
            return <Input {...common} value={text} onChange={(e) => onChange(e.target.value)} />;
    }
}

/**
 * The "Edit file details" button of a stored file and the modal window it opens with the "Name" and "Description" of
 * the file. The window edits a draft that starts from the form's state each time it opens: "Done" applies the draft to
 * the form (it is saved with the node, as before), "Cancel", Escape and a click outside drop it. The metadata belongs
 * to the file, so a change shows everywhere the file is used. Errors come from the server (`file_meta.{uuid}.name` /
 * `.description`) and are shown next to the draft's fields (the block under the file shows them too).
 *
 * @example
 * <FileMetaDialog id="field-cover" uuid={file.uuid} editor={fileMeta} />
 */
function FileMetaDialog({ id, uuid, editor }: { id: string; uuid: string; editor: NodeFileMetaEditor }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState<NodeFileMeta>({ name: '', description: '' });
    const invalid = !!editor.errorAt(uuid, 'name') || !!editor.errorAt(uuid, 'description');
    const nameError = editor.errorAt(uuid, 'name');
    const descriptionError = editor.errorAt(uuid, 'description');

    const change = (next: boolean) => {
        if (next) {
            setDraft(editor.values[uuid] ?? { name: '', description: '' });
        }
        setOpen(next);
    };

    return (
        <Dialog open={open} onOpenChange={change}>
            <DialogTrigger asChild>
                <Button type="button" variant="ghost" size="icon-sm" aria-label={t('Edit file details')} aria-invalid={invalid || undefined} data-testid={`file-meta-open-${uuid}`}>
                    <Pencil aria-hidden />
                </Button>
            </DialogTrigger>
            <DialogContent data-testid={`file-meta-${uuid}`}>
                <DialogHeader>
                    <DialogTitle>{t('Edit file details')}</DialogTitle>
                    <DialogDescription>{t('The name and description belong to the file and apply everywhere it is used.')}</DialogDescription>
                </DialogHeader>
                <div className="space-y-3">
                    <div className="space-y-1">
                        <Label htmlFor={`${id}-file-name`}>{t('Name')}</Label>
                        <Input id={`${id}-file-name`} value={draft.name} maxLength={255} aria-invalid={!!nameError || undefined} onChange={(e) => setDraft({ ...draft, name: e.target.value })} />
                        {nameError && (
                            <p role="alert" className="text-sm text-destructive">
                                {nameError}
                            </p>
                        )}
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor={`${id}-file-description`}>{t('Description')}</Label>
                        <Textarea
                            id={`${id}-file-description`}
                            rows={3}
                            value={draft.description}
                            aria-invalid={!!descriptionError || undefined}
                            onChange={(e) => setDraft({ ...draft, description: e.target.value })}
                        />
                        {descriptionError && (
                            <p role="alert" className="text-sm text-destructive">
                                {descriptionError}
                            </p>
                        )}
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                        {t('Cancel')}
                    </Button>
                    <Button
                        type="button"
                        onClick={() => {
                            editor.onChange(uuid, draft);
                            setOpen(false);
                        }}
                    >
                        {t('Done')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
