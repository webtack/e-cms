import { Badge } from '@/Components/ui/badge';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    value: boolean;
    /** Shows a false value as a warning badge instead of quiet muted text. */
    tone?: 'warning';
};

/**
 * A yes/no value for tables and detail views: `Yes` is a success badge, `No` is quiet muted text,
 * or a warning badge when `tone="warning"`. The words are translated; the text is always present,
 * so the colour is never the only carrier of meaning.
 *
 * @example
 * <BooleanBadge value={user.is_active} tone="warning" />
 */
export function BooleanBadge({ value, tone }: Props) {
    const { t } = useTranslation();

    if (value) {
        return <Badge variant="success">{t('Yes')}</Badge>;
    }

    if (tone === 'warning') {
        return <Badge variant="warning">{t('No')}</Badge>;
    }

    return <span className="text-muted-foreground">{t('No')}</span>;
}
