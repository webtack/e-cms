import { Input } from '@/Components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';

import type { UrlValue } from './types';

type Props = {
    /** The element's DOM id; the "Link" input gets it, the "Title" input gets `{id}-title`. */
    id: string;
    value: UrlValue;
    onChange: (value: UrlValue) => void;
    invalid?: boolean;
    /** Accessible name of the field; the two inputs add "link" / "title" to it. */
    'aria-label'?: string;
};

/**
 * Input of one `url` value: two single-line text inputs side by side (stacked on a narrow screen), "Link" and the
 * optional "Title". No dialog and no request to an outside service; the server validates the link.
 *
 * @example
 * <UrlField id="field-site" value={{ url: '/about', title: 'About us' }} onChange={setSite} />
 */
export function UrlField({ id, value, onChange, invalid, 'aria-label': ariaLabel }: Props) {
    const { t } = useTranslation();
    const label = (part: string) => (ariaLabel ? `${ariaLabel}, ${part}` : part);

    return (
        <div className="grid gap-2 sm:grid-cols-2" role="group" aria-label={ariaLabel}>
            <Input
                id={id}
                aria-label={label(t('Link'))}
                aria-invalid={invalid || undefined}
                placeholder={t('Link')}
                value={value.url}
                onChange={(e) => onChange({ ...value, url: e.target.value })}
            />
            <Input
                id={`${id}-title`}
                aria-label={label(t('Title'))}
                aria-invalid={invalid || undefined}
                placeholder={t('Title')}
                value={value.title}
                onChange={(e) => onChange({ ...value, title: e.target.value })}
            />
        </div>
    );
}
