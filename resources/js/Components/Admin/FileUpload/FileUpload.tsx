import { FileText, Loader2, Upload, X } from 'lucide-react';
import { useRef, useState, type DragEvent, type ReactNode } from 'react';

import { Button } from '@/Components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

/** Metadata of an uploaded file, as answered by `POST /admin/files`. */
export type UploadedFile = {
    /** Stable external identifier: 32 chars `0-9a-f`. */
    uuid: string;
    profile: string;
    /** Original file name. */
    name: string;
    mime: string;
    /** Bytes. */
    size: number;
    width: number | null;
    height: number | null;
    /** The value to store in a content field: `{uuid}.{ext}`. */
    value: string;
    /** Original address. */
    url: string;
    /** Address of the `thumb` image style for the preview; `null` for a document. */
    preview: string | null;
};

/** Where the upload rules come from: a profile of `config/files.php`, or a content field (its type's profile narrowed by its settings). */
export type Target = { profile: string; field?: never } | { field: { contentType: string; fieldKey: string }; profile?: never };

type CommonProps = {
    /** Hint for the browser's file picker and a quick refusal (`image/*`, `.pdf`, `image/png,image/jpeg`); the server remains the authority. */
    accept?: string;
    /** Size limit in KB for a quick refusal before the upload; the server remains the authority. */
    maxSize?: number;
    /** Visible label above the zone. */
    label?: string;
    /** A validation error from the surrounding form; an upload error takes precedence. */
    error?: string;
    /** DOM id of the file input; the label points at it. */
    id?: string;
    disabled?: boolean;
    /** Accessible name of the file input when no `<label>` points at it. */
    'aria-label'?: string;
    /** Defaults to `file-upload-{profile or field key}`. */
    'data-testid'?: string;
};

/** One file: the component is controlled by the selected file. */
type SingleProps = CommonProps & {
    multiple?: false;
    /** The file currently selected, or `null`. The component is controlled. */
    value: UploadedFile | null;
    /** Called with the uploaded file's metadata (use `file.uuid`, or `file.value` for content fields), or `null` when the selection is cleared. */
    onChange: (file: UploadedFile | null) => void;
    /** Whether the selected file block has the "Remove file" button; `false` leaves removal to the surrounding form (a list row). Defaults to `true`. */
    removable?: boolean;
    /** Description of the selected file, drawn as text between its name and size (clamped to two lines); empty or missing draws nothing. */
    description?: string;
    /** Extra content drawn inside the selected file block under its name and size (for example fields for the file's name and description). */
    details?: ReactNode;
    /** Extra buttons in the selected file block, in the row of its name, before the "Remove file" button; with `removable={false}` they take its place. */
    actions?: ReactNode;
    onUploadedMany?: never;
};

/** Several files at once: the widget keeps no selection, the form owns the list of files. */
type MultipleProps = CommonProps & {
    multiple: true;
    /** Called once per picked batch, after its queue is done, with every file that was uploaded (in the order picked). Not called when none was. */
    onUploadedMany: (files: UploadedFile[]) => void;
    /** How many more files the form can take; the first ones in the picked order are uploaded and the rest are not (`undefined`: no limit). */
    capacity?: number;
    /** Reason shown next to each file that did not fit `capacity`. */
    capacityMessage?: string;
    /**
     * Takes over the list of refused and failed files: called with it after every batch (and with `[]` when a batch
     * starts), and the widget then does not render the list itself. For a form that hides the widget when it is full,
     * so the list outlives it.
     */
    onFailures?: (failures: FileFailure[]) => void;
    value?: never;
    onChange?: never;
    removable?: never;
    description?: never;
    details?: never;
    actions?: never;
};

type Props = Target & (SingleProps | MultipleProps);

/** A file of a multiple upload that was refused or failed, with the reason. */
export type FileFailure = { name: string; message: string };

/** `XSRF-TOKEN` cookie value, which Laravel accepts back as the `X-XSRF-TOKEN` header. */
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function formatSize(bytes: number): string {
    if (bytes >= 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/** Whether a file matches an `accept` list (`type/*`, `type/subtype`, `.ext`); an empty list matches everything. */
function matchesAccept(file: File, accept?: string): boolean {
    const rules = (accept ?? '').split(',').map((rule) => rule.trim().toLowerCase()).filter(Boolean);

    return (
        rules.length === 0 ||
        rules.some((rule) =>
            rule.startsWith('.') ? file.name.toLowerCase().endsWith(rule) : rule.endsWith('/*') ? file.type.startsWith(rule.slice(0, -1)) : file.type === rule,
        )
    );
}

/** Why a file is refused before any request (`accept` / `maxSize`), or `null` when it may be sent. */
function quickRefusal(file: File, accept: string | undefined, maxSize: number | undefined, t: (key: string, replace?: Record<string, string | number>) => string): string | null {
    if (!matchesAccept(file, accept)) {
        return t('This file type is not allowed.');
    }

    if (maxSize !== undefined && file.size > maxSize * 1024) {
        return t('The file is too large. The maximum size is :max.', { max: formatSize(maxSize * 1024) });
    }

    return null;
}

/**
 * Sends one file to `POST /admin/files` and resolves with the stored file. Rejects with an `Error` whose message is
 * the server's reason, or empty when there is none (a network failure, an unreadable answer), so the caller can use its
 * own wording.
 */
export async function uploadFile(file: File, target: Target): Promise<UploadedFile> {
    const body = new FormData();
    body.append('file', file);
    if (target.profile !== undefined) {
        body.append('profile', target.profile);
    } else if (target.field !== undefined) {
        body.append('content_type', target.field.contentType);
        body.append('field_key', target.field.fieldKey);
    }

    let response: Response;

    try {
        response = await fetch('/admin/files', {
            method: 'POST',
            body,
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrfToken() },
            credentials: 'same-origin',
        });
    } catch {
        throw new Error('');
    }

    if (response.ok) {
        return (await response.json()) as UploadedFile;
    }

    const payload = (await response.json().catch(() => null)) as { errors?: Record<string, string[]> } | null;

    throw new Error(payload?.errors?.file?.[0] ?? payload?.errors?.profile?.[0] ?? payload?.errors?.field_key?.[0] ?? '');
}

/**
 * File upload widget: pick or drop a file, it is sent at once to `POST /admin/files` under `profile`
 * (or for a content `field`, whose settings narrow the profile) and the form receives the stored file
 * through `onChange` (it keeps only `file.uuid`, or `file.value` for a content field).
 *
 * With `multiple` the zone takes several files (picked or dropped) and sends them one by one in separate requests; it shows
 * "Uploaded N of M" while the queue runs, accepts no new files until it ends, and lists the files that failed with
 * the reason. The widget then keeps no selection (the form draws its own list). With `capacity` only that many files of a
 * batch are uploaded; the rest are listed with `capacityMessage`.
 *
 * Shows the upload state, the server's validation error (type or size is checked on the server; `accept`
 * and `maxSize` only refuse an obvious mismatch early), a preview for images and a button to clear the selection. It knows nothing about
 * Inertia forms, so it works with `useForm` and with plain state alike.
 *
 * @example
 * const [avatar, setAvatar] = useState<UploadedFile | null>(null);
 * <FileUpload profile="image" accept="image/*" label={t('Avatar')} value={avatar} onChange={setAvatar} />
 *
 * @example
 * // For a content field: the rules come from the field's settings.
 * <FileUpload field={{ contentType: 'article', fieldKey: 'cover' }} accept="image/png" maxSize={1024} value={cover} onChange={...} />
 *
 * @example
 * // With an Inertia form: only the uuid travels with the form.
 * <FileUpload profile="document" value={file} onChange={(f) => { setFile(f); setData('document', f?.uuid ?? null); }} />
 *
 * @example
 * // Inside a list row: no own remove button (the row has one), and extra fields under the file's name.
 * <FileUpload profile="image" value={file} removable={false} details={<Input aria-label="Description" />} onChange={setFile} />
 *
 * @example
 * // The file's description is drawn as text between its name and size.
 * <FileUpload profile="image" value={file} description="Front cover" onChange={setFile} />
 *
 * @example
 * // An extra button in the block's button row (here it takes the place of the hidden "Remove file" button).
 * <FileUpload profile="image" value={file} removable={false} actions={<Button type="button" size="icon-sm" variant="ghost" aria-label="Edit" />} onChange={setFile} />
 *
 * @example
 * // Several files at once: one request per file, one after another. A refused or failed file is reported by name and
 * // does not stop the others; `onUploadedMany` gets the files that went through, in the order picked.
 * <FileUpload multiple field={{ contentType: 'article', fieldKey: 'gallery' }} accept="image/png" onUploadedMany={(files) => addToList(files)} />
 *
 * @example
 * // A list with room for two more files: of a batch of four only the first two are uploaded.
 * <FileUpload multiple field={...} capacity={2} capacityMessage={t('Limit: :max', { max: 5 })} onUploadedMany={addToList} />
 */
export function FileUpload(props: Props) {
    const { profile, field, accept, maxSize, label, error, id, disabled, 'aria-label': ariaLabel, 'data-testid': testId } = props;
    const multiple = props.multiple === true;
    const value = props.multiple ? null : props.value;
    const { t } = useTranslation();
    const inputRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const [failures, setFailures] = useState<FileFailure[]>([]);
    const [progress, setProgress] = useState({ done: 0, total: 0 });
    const [dragging, setDragging] = useState(false);
    const target = profile ?? field?.fieldKey ?? 'file';
    const inputId = id ?? `file-upload-input-${target}`;
    const shownError = uploadError ?? error;
    const root = testId ?? `file-upload-${target}`;
    const where = (profile !== undefined ? { profile } : { field }) as Target;

    async function upload(file: File) {
        setUploadError(null);

        const refusal = quickRefusal(file, accept, maxSize, t);

        if (refusal !== null) {
            setUploadError(refusal);
            resetInput();

            return;
        }

        setUploading(true);

        try {
            const uploaded = await uploadFile(file, where);

            if (!props.multiple) {
                props.onChange(uploaded);
            }
        } catch (failure) {
            setUploadError((failure as Error).message || t('The file could not be uploaded.'));
        } finally {
            setUploading(false);
            resetInput();
        }
    }

    /** Sends the picked files one after another; a refused or failed file is recorded and the queue goes on. */
    async function uploadMany(files: File[]) {
        if (!props.multiple || files.length === 0) {
            return;
        }

        const uploaded: UploadedFile[] = [];
        const failed: FileFailure[] = [];

        // Only the first files that fit are sent, so no file lands in the storage without a place in the form.
        const room = props.capacity === undefined ? files.length : Math.max(0, props.capacity);
        const queue = files.slice(0, room);
        const skipped = files.slice(room);

        setUploadError(null);
        setFailures([]);
        props.onFailures?.([]);
        setProgress({ done: 0, total: queue.length });
        setUploading(true);

        try {
            for (const [index, file] of queue.entries()) {
                const refusal = quickRefusal(file, accept, maxSize, t);

                if (refusal !== null) {
                    failed.push({ name: file.name, message: refusal });
                } else {
                    try {
                        uploaded.push(await uploadFile(file, where));
                    } catch (failure) {
                        failed.push({ name: file.name, message: (failure as Error).message || t('The file could not be uploaded.') });
                    }
                }

                setProgress({ done: index + 1, total: queue.length });
            }
        } finally {
            skipped.forEach((file) => failed.push({ name: file.name, message: props.capacityMessage ?? '' }));
            setUploading(false);
            setFailures(failed);
            props.onFailures?.(failed);
            resetInput();

            if (uploaded.length > 0) {
                props.onUploadedMany(uploaded);
            }
        }
    }

    /** Lets the same file be picked again after an error or a clear. */
    function resetInput() {
        if (inputRef.current) {
            inputRef.current.value = '';
        }
    }

    function pick(list: FileList | null | undefined) {
        const files = Array.from(list ?? []);

        if (multiple) {
            void uploadMany(files);
        } else if (files[0]) {
            void upload(files[0]);
        }
    }

    function handleDrop(event: DragEvent<HTMLDivElement>) {
        event.preventDefault();
        setDragging(false);

        if (!disabled && !uploading) {
            pick(event.dataTransfer.files);
        }
    }

    function clear() {
        setUploadError(null);

        if (!props.multiple) {
            props.onChange(null);
        }
    }

    return (
        <div className="flex flex-col gap-2" data-testid={root}>
            {label && (
                <label htmlFor={inputId} className="text-sm font-medium">
                    {label}
                </label>
            )}

            <input
                ref={inputRef}
                id={inputId}
                type="file"
                multiple={multiple}
                accept={accept}
                aria-label={ariaLabel}
                disabled={disabled || uploading}
                className="sr-only"
                aria-invalid={shownError ? true : undefined}
                onChange={(event) => pick(event.target.files)}
            />

            {value ? (
                <div className="flex flex-col gap-3 rounded-md border p-3" data-testid={`${root}-selected`}>
                    <div className="flex items-center gap-3">
                        {value.mime.startsWith('image/') ? (
                            <a href={value.url} target="_blank" rel="noopener noreferrer" data-testid={`${root}-preview-link`}>
                                <img src={value.preview ?? value.url} alt={value.name} className="size-16 cursor-pointer rounded object-cover" data-testid={`${root}-preview`} />
                            </a>
                        ) : (
                            <FileText className="text-muted-foreground size-8" aria-hidden />
                        )}
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium">{value.name}</p>
                            {props.description && (
                                <p className="text-muted-foreground line-clamp-2 text-xs break-words" data-testid={`${root}-description`}>
                                    {props.description}
                                </p>
                            )}
                            <p className="text-muted-foreground text-xs">{formatSize(value.size)}</p>
                        </div>
                        {props.actions}
                        {props.removable !== false && (
                            <Button type="button" variant="ghost" size="icon-sm" disabled={disabled} aria-label={t('Remove file')} onClick={clear}>
                                <X aria-hidden />
                            </Button>
                        )}
                    </div>
                    {props.details}
                </div>
            ) : (
                <div
                    className={cn(
                        'flex flex-col items-center gap-2 rounded-md border border-dashed p-6 text-center text-sm transition-colors',
                        dragging && 'border-primary bg-muted',
                        shownError && 'border-destructive',
                    )}
                    onDragOver={(event) => {
                        event.preventDefault();
                        setDragging(true);
                    }}
                    onDragLeave={() => setDragging(false)}
                    onDrop={handleDrop}
                    data-testid={`${root}-zone`}
                >
                    {uploading ? (
                        <p className="text-muted-foreground flex items-center gap-2" role="status" data-testid={`${root}-progress`}>
                            <Loader2 className="size-4 animate-spin" aria-hidden />
                            {multiple ? t('Uploaded :done of :total', { done: progress.done, total: progress.total }) : t('Uploading…')}
                        </p>
                    ) : (
                        <>
                            <Upload className="text-muted-foreground size-6" aria-hidden />
                            <Button type="button" variant="outline" size="sm" disabled={disabled} onClick={() => inputRef.current?.click()}>
                                {multiple ? t('Choose files') : t('Choose a file')}
                            </Button>
                            <p className="text-muted-foreground text-xs">{multiple ? t('or drag them here') : t('or drag it here')}</p>
                        </>
                    )}
                </div>
            )}

            {shownError && (
                <p className="text-destructive text-sm" role="alert" data-testid={`${root}-error`}>
                    {shownError}
                </p>
            )}

            {failures.length > 0 && !(props.multiple && props.onFailures) && (
                <ul className="text-destructive space-y-1 text-sm" role="alert" data-testid={`${root}-failures`}>
                    {failures.map((failure, index) => (
                        <li key={`${failure.name}-${index}`}>{t(':name: :message', { name: failure.name, message: failure.message })}</li>
                    ))}
                </ul>
            )}
        </div>
    );
}
