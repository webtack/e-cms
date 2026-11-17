import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

import type { UploadedFile } from '@/Components/Admin/FileUpload/FileUpload';
import { Button } from '@/Components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';

import { YoutubeFieldDialog } from './YoutubeFieldDialog';
import type { NodeField, NodeFiles, YoutubeValue } from './types';
import { emptyYoutube } from './types';

type Props = {
    field: NodeField;
    /** Key of the content type that owns the field; the screenshot upload goes by it. */
    typeKey: string;
    /** The element's DOM id; the dialog's inputs derive their ids from it. */
    id: string;
    value: YoutubeValue;
    onChange: (value: YoutubeValue) => void;
    /** Known files by stored value, to show the stored screenshot. */
    files?: NodeFiles;
    /** Called with a screenshot just received or uploaded, so the form can show it. */
    onFile?: (file: UploadedFile) => void;
    invalid?: boolean;
    /** `false` hides the card's own "Delete" button (a row of a multiple field has its own remove button). Defaults to `true`. */
    removable?: boolean;
    /** Accessible name of the card; also tells the rows of a multiple field apart. */
    'aria-label'?: string;
};

/**
 * Input of one `youtube` value: a card with the screenshot, the title as a link to the video (a new tab) and the
 * description, with "Edit" and "Delete" buttons. While there is no video, the "Add video" button stands in its place.
 * Adding and editing happen in `YoutubeFieldDialog`; the value changes only when the user confirms the dialog.
 * The title falls back to the address when it is empty.
 *
 * @example
 * <YoutubeField field={field} typeKey="news" id="field-video" value={{ url: '', title: '', description: '', screen: '' }} onChange={setVideo} />
 */
export function YoutubeField({ field, typeKey, id, value, onChange, files, onFile, invalid, removable = true, 'aria-label': ariaLabel }: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const screen = value.screen === '' ? undefined : files?.[value.screen];

    return (
        <div data-testid={`youtube-${field.key}`}>
            {value.url === '' ? (
                <Button type="button" variant="outline" size="sm" onClick={() => setOpen(true)} data-testid={`youtube-add-${field.key}`}>
                    <Plus aria-hidden />
                    {t('Add video')}
                </Button>
            ) : (
                <div role="group" aria-label={ariaLabel} aria-invalid={invalid || undefined} className={`flex items-start gap-3 rounded-md border p-3 ${invalid ? 'border-destructive' : ''}`} data-testid={`youtube-card-${field.key}`}>
                    {screen?.url ? (
                        <img src={screen.url} alt={value.title || t('Screenshot')} className="h-20 w-36 shrink-0 rounded-sm object-cover" data-testid={`youtube-screen-${field.key}`} />
                    ) : (
                        <div className="flex h-20 w-36 shrink-0 items-center justify-center rounded-sm bg-muted text-xs text-muted-foreground">{t('No screenshot')}</div>
                    )}
                    <div className="min-w-0 flex-1 space-y-1">
                        <a href={value.url} target="_blank" rel="noopener noreferrer" className="block truncate font-medium hover:underline" data-testid={`youtube-link-${field.key}`}>
                            {value.title || value.url}
                        </a>
                        {value.description && <p className="line-clamp-3 text-sm text-muted-foreground">{value.description}</p>}
                    </div>
                    <div className="flex shrink-0 gap-1">
                        <Button type="button" variant="ghost" size="icon-sm" aria-label={t('Edit')} onClick={() => setOpen(true)} data-testid={`youtube-edit-${field.key}`}>
                            <Pencil aria-hidden />
                        </Button>
                        {removable && (
                            <Button type="button" variant="ghost" size="icon-sm" aria-label={t('Delete')} onClick={() => onChange(emptyYoutube())} data-testid={`youtube-delete-${field.key}`}>
                                <Trash2 aria-hidden />
                            </Button>
                        )}
                    </div>
                </div>
            )}
            {open && (
                <YoutubeFieldDialog
                    field={field}
                    typeKey={typeKey}
                    id={id}
                    initial={value}
                    files={files}
                    onFile={onFile}
                    onDone={(next) => {
                        onChange(next);
                        setOpen(false);
                    }}
                    onClose={() => setOpen(false)}
                />
            )}
        </div>
    );
}
