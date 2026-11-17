import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { FieldGroup } from '@/Components/Admin/FieldGroup';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { useTranslation } from '@/hooks/useTranslation';

/** The answer of `GET /admin/media/{uuid}` (`MediaController::show`). */
type Details = {
    uuid: string;
    name: string;
    /** Free-text description of the file; `null` when absent. */
    description: string | null;
    /** Names and descriptions in additional languages, with the language names; read-only here. */
    translations: { lang: string; language: string; name: string | null; description: string | null }[];
    mime: string;
    /** Short type label (`docx`) shown in the header. */
    type: string;
    size: number;
    width: number | null;
    height: number | null;
    url: string;
    uploader: string | null;
    created_at: string | null;
    /**
     * Places of use in the order of the relations. An entry with `restricted: true` is a place the viewer may not
     * open: only the translated `label` ("Used elsewhere") is meaningful, the other fields are empty.
     */
    usages: {
        restricted?: boolean;
        owner_type: string;
        owner_id: number | null;
        label: string;
        url: string | null;
        role: string;
        lang: string;
    }[];
};

type Props = {
    uuid: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Side panel with the details of one file and the list of places it is used in: the owner (a link to its edit page
 * when it has one), the role (the field key) and the language. The details are fetched when the panel opens.
 *
 * @example
 * <MediaDetails uuid={row.uuid} open={open} onOpenChange={setOpen} />
 */
export function MediaDetails({ uuid, open, onOpenChange }: Props) {
    const { t } = useTranslation();
    const [details, setDetails] = useState<Details | null>(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        let current = true;
        setDetails(null);
        setFailed(false);

        fetch(`/admin/media/${uuid}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then((response) => (response.ok ? (response.json() as Promise<Details>) : Promise.reject(new Error(String(response.status)))))
            .then((payload) => current && setDetails(payload))
            .catch(() => current && setFailed(true));

        return () => {
            current = false;
        };
    }, [open, uuid]);

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent data-testid="media-details" className="overflow-y-auto">
                <SheetHeader>
                    <SheetTitle>{details?.name ?? t('File details')}</SheetTitle>
                    <SheetDescription>{details ? details.type : t('Loading…')}</SheetDescription>
                </SheetHeader>

                <div className="space-y-4 px-4 pb-4 text-sm">
                    {failed && (
                        <p role="alert" className="text-destructive">
                            {t('The details could not be loaded.')}
                        </p>
                    )}

                    {details && (
                        <>
                            {details.mime.startsWith('image/') && <img src={details.url} alt={details.name} className="max-h-48 rounded border object-contain" />}

                            {details.description && (
                                <p className="whitespace-pre-line" data-testid="media-description">
                                    {details.description}
                                </p>
                            )}

                            {details.translations.length > 0 && (
                                <ul className="space-y-1" data-testid="media-translations">
                                    {details.translations.map((translated) => (
                                        <li key={translated.lang} data-testid={`media-translation-${translated.lang}`}>
                                            <span className="text-muted-foreground">
                                                {translated.language} ({translated.lang}):{' '}
                                            </span>
                                            {translated.name && (
                                                <strong data-testid={`media-translated-name-${translated.lang}`}>{translated.name} </strong>
                                            )}
                                            {translated.description && (
                                                <span className="whitespace-pre-line" data-testid={`media-description-${translated.lang}`}>
                                                    {translated.description}
                                                </span>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}

                            <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
                                <dt className="text-muted-foreground">{t('Size')}</dt>
                                <dd>{Math.max(1, Math.round(details.size / 1024))} KB</dd>
                                {details.width !== null && details.height !== null && (
                                    <>
                                        <dt className="text-muted-foreground">{t('Dimensions')}</dt>
                                        <dd>
                                            {details.width} × {details.height}
                                        </dd>
                                    </>
                                )}
                                <dt className="text-muted-foreground">{t('Uploaded by')}</dt>
                                <dd>{details.uploader ?? '—'}</dd>
                                <dt className="text-muted-foreground">{t('Uploaded')}</dt>
                                <dd>{details.created_at}</dd>
                            </dl>

                            <FieldGroup legend={t('Used in')}>
                                {details.usages.length === 0 ? (
                                    <p className="text-muted-foreground" data-testid="media-unused">
                                        {t('This file is not used anywhere.')}
                                    </p>
                                ) : (
                                    <ul className="space-y-2" data-testid="media-usages">
                                        {details.usages.map((usage, index) => (
                                            <li key={index} className="rounded border p-2" data-testid={usage.restricted ? 'media-usage-restricted' : undefined}>
                                                {usage.restricted ? (
                                                    <span className="text-muted-foreground">{usage.label}</span>
                                                ) : usage.url ? (
                                                    <Link href={usage.url} className="font-medium underline-offset-4 hover:underline">
                                                        {usage.label}
                                                    </Link>
                                                ) : (
                                                    <span className="font-medium">{usage.label}</span>
                                                )}
                                                {!usage.restricted && (
                                                    <p className="text-muted-foreground">
                                                        {t('Field')}: {usage.role} · {t('Language')}: {usage.lang}
                                                    </p>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </FieldGroup>
                        </>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}
