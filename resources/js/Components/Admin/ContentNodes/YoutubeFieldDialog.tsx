import { Loader2 } from 'lucide-react';
import { useState } from 'react';

import { FileUpload, type UploadedFile } from '@/Components/Admin/FileUpload/FileUpload';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';

import type { NodeField, NodeFiles, YoutubeValue } from './types';

type Props = {
    field: NodeField;
    /** Key of the content type that owns the field; the screenshot upload goes by it. */
    typeKey: string;
    /** Base DOM id: the link input has `{id}-url`, the others `{id}-title`, `{id}-description` and `{id}-screen`. */
    id: string;
    /** The video being edited; an empty one when a video is being added. */
    initial: YoutubeValue;
    /** Known files by stored value, to show the stored screenshot. */
    files?: NodeFiles;
    /** Called with a screenshot just received or uploaded, so the form can show it. */
    onFile?: (file: UploadedFile) => void;
    /** Called with the draft when the user confirms with "Done"; the dialog closes itself afterwards through `onClose`. */
    onDone: (value: YoutubeValue) => void;
    /** Called when the dialog should go away: after "Done", "Cancel" or any other way of closing it. */
    onClose: () => void;
};

/** The answer of `POST /admin/youtube/lookup`. */
type Lookup = { url: string; title: string; description: string; screen: { value: string; uuid: string; name: string; mime: string; size: number; url: string; preview: string | null } };

/** `XSRF-TOKEN` cookie value, which Laravel accepts back as the `X-XSRF-TOKEN` header. */
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Asks the server for the title, description and screenshot of a video. Resolves with the data, or rejects with an
 * `Error` whose message is the server's reason (empty when there is none, e.g. a network failure).
 */
async function lookupVideo(url: string): Promise<Lookup> {
    let response: Response;

    try {
        response = await fetch('/admin/youtube/lookup', {
            method: 'POST',
            body: JSON.stringify({ url }),
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrfToken() },
            credentials: 'same-origin',
        });
    } catch {
        throw new Error('');
    }

    if (response.ok) {
        return (await response.json()) as Lookup;
    }

    const payload = (await response.json().catch(() => null)) as { message?: string; errors?: Record<string, string[]> } | null;

    throw new Error(payload?.errors?.url?.[0] ?? payload?.message ?? '');
}

/**
 * Modal window that adds or edits one `youtube` video: the link with a "Get data" button, the editable title and
 * description, and the screenshot as a `FileUpload` of the field (replace or remove it).
 *
 * The window edits a local draft; the form gets it only through `onDone` ("Done"), while "Cancel" and closing the
 * window drop it. "Get data" asks the server (`POST /admin/youtube/lookup`) and, on success, overwrites the link,
 * title, description and screenshot of the draft; on an error it shows the reason next to the link and changes
 * nothing. Changing the link alone never touches the other values. The button is disabled while the link is empty,
 * and so is "Done". The draft starts from `initial` each time the window is mounted, so mount it only while it is open.
 *
 * @example
 * {open && <YoutubeFieldDialog field={field} typeKey="news" id="field-video" initial={value} onDone={onChange} onClose={() => setOpen(false)} />}
 */
export function YoutubeFieldDialog({ field, typeKey, id, initial, files, onFile, onDone, onClose }: Props) {
    const { t } = useTranslation();
    const [draft, setDraft] = useState<YoutubeValue>(initial);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const info = draft.screen === '' ? null : (files?.[draft.screen] ?? { uuid: draft.screen.slice(0, 32), name: draft.screen, mime: '', size: 0, url: '', preview: null });
    const selected: UploadedFile | null = info === null ? null : { ...info, preview: info.preview ?? null, profile: 'image', width: null, height: null, value: draft.screen };

    const getData = async () => {
        setLoading(true);
        setError(null);

        try {
            const data = await lookupVideo(draft.url.trim());
            const screen: UploadedFile = { ...data.screen, profile: 'image', width: null, height: null };

            onFile?.(screen);
            setDraft({ url: data.url, title: data.title, description: data.description, screen: screen.value });
        } catch (e) {
            setError((e as Error).message || t('Could not get the video data. Try again later.'));
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl" data-testid={`youtube-dialog-${field.key}`}>
                <DialogHeader>
                    <DialogTitle>{initial.url === '' ? t('Add video') : t('Edit video')}</DialogTitle>
                    <DialogDescription className="sr-only">{t('Link, title, description and screenshot of the video.')}</DialogDescription>
                </DialogHeader>
                <div className="space-y-3">
                    <div className="space-y-1">
                        <Label htmlFor={`${id}-url`}>{t('Link')}</Label>
                        <div className="flex items-start gap-2">
                            <Input
                                id={`${id}-url`}
                                aria-invalid={!!error || undefined}
                                inputMode="url"
                                placeholder="https://www.youtube.com/watch?v=…"
                                value={draft.url}
                                onChange={(e) => setDraft({ ...draft, url: e.target.value })}
                            />
                            <Button type="button" variant="outline" disabled={draft.url.trim() === '' || loading} onClick={getData} data-testid={`youtube-lookup-${field.key}`}>
                                {loading && <Loader2 className="animate-spin" aria-hidden />}
                                {t('Get data')}
                            </Button>
                        </div>
                        {error && (
                            <p role="alert" className="text-sm text-destructive" data-testid={`youtube-error-${field.key}`}>
                                {error}
                            </p>
                        )}
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor={`${id}-title`}>{t('Title')}</Label>
                        <Input id={`${id}-title`} maxLength={255} value={draft.title} onChange={(e) => setDraft({ ...draft, title: e.target.value })} />
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor={`${id}-description`}>{t('Description')}</Label>
                        <Textarea id={`${id}-description`} rows={4} maxLength={10000} value={draft.description} onChange={(e) => setDraft({ ...draft, description: e.target.value })} />
                    </div>
                    <FileUpload
                        field={{ contentType: typeKey, fieldKey: field.key }}
                        id={`${id}-screen`}
                        aria-label={t('Screenshot')}
                        accept={field.settings.mimes?.join(',')}
                        maxSize={field.settings.max_size}
                        value={selected}
                        data-testid={`file-upload-${field.key}`}
                        onChange={(file) => {
                            if (file) {
                                onFile?.(file);
                            }
                            setDraft({ ...draft, screen: file ? file.value : '' });
                        }}
                    />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" size="sm" onClick={onClose}>
                        {t('Cancel')}
                    </Button>
                    <Button type="button" size="sm" disabled={draft.url.trim() === ''} onClick={() => onDone({ ...draft, url: draft.url.trim() })}>
                        {t('Done')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
