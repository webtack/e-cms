import { CopyableText } from '@/Components/Admin/CopyableText';
import { UserAvatar } from '@/Components/Admin/UserAvatar';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { useTranslation } from '@/hooks/useTranslation';

import type { NodeMeta } from './types';
import { nodeStatus, type NodeStatus } from './status';

/** The service attributes the form edits; they travel with `values` in the same request. */
export type NodeServiceData = {
    slug: string;
    is_active: boolean;
    published_at: string;
    unpublished_at: string;
};

type Props = {
    data: NodeServiceData;
    setData: <K extends keyof NodeServiceData>(key: K, value: NodeServiceData[K]) => void;
    /** Server validation errors by field key. */
    errors: Record<string, string | undefined>;
    /** Read-only attributes of the node (`id` on the edit form only, `updated_at`, `author`). */
    meta: Pick<NodeMeta, 'id' | 'updated_at' | 'author'>;
    /** `true` on the edit form: "Updated" is shown and the slug hint changes. */
    editing: boolean;
    /** Fixed slug prefix drawn before the input (`block-` for blocks); `data.slug` holds only what follows it. */
    slugPrefix?: string;
};

/** Translation key of each state's badge. */
const STATUS_LABEL: Record<NodeStatus, string> = {
    published: 'Published',
    scheduled: 'Scheduled',
    expired: 'Expired',
    disabled: 'Disabled',
};

/** Badge tone of each state: published is success, scheduled info, expired warning, disabled neutral. */
const STATUS_VARIANT: Record<NodeStatus, 'success' | 'info' | 'warning' | 'outline'> = {
    published: 'success',
    scheduled: 'info',
    expired: 'warning',
    disabled: 'outline',
};

/** The badge of a node state; `data-status` lets tests and styles tell the states apart. */
export function NodeStatusBadge({ status }: { status: NodeStatus }) {
    const { t } = useTranslation();

    return (
        <Badge variant={STATUS_VARIANT[status]} data-status={status}>
            {t(STATUS_LABEL[status])}
        </Badge>
    );
}

/**
 * The content of the «Details» section of the node form (the heading is the accordion trigger drawn by
 * `ContentNodeForm`): the visibility switch, the state badge (computed from the
 * form state, so it reacts before saving), the publication date, the slug, and the read-only «ID» (edit only,
 * click to copy), «Updated» (edit only) and «Author». Each row is a label and a control, so the column grows by adding rows. Field errors show
 * inside the block. The block holds no state of its own and must be rendered once (in the side column or in the
 * sheet), because its inputs have fixed ids (`slug`, `published_at`, `unpublished_at`, `is_active`).
 *
 * With a `slugPrefix` the slug input shows the prefix as a separate unchangeable addon; a prefix typed or pasted at the
 * start of the input is dropped so that it is never doubled.
 *
 * @example
 * <NodeServicePanel data={data} setData={setData} errors={errors} meta={node} editing />
 *
 * @example
 * // Typing or pasting `block-hero` leaves `hero` in the input, next to the `block-` addon.
 * <NodeServicePanel data={data} setData={setData} errors={errors} meta={node} editing={false} slugPrefix="block-" />
 */
export function NodeServicePanel({ data, setData, errors, meta, editing, slugPrefix = '' }: Props) {
    const { t } = useTranslation();
    const message = (key: string) =>
        errors[key] && (
            <p role="alert" className="text-sm text-destructive">
                {errors[key]}
            </p>
        );

    return (
        <div data-testid="node-service-panel" className="space-y-4">
            <div className="flex items-center justify-between gap-2">
                <Label htmlFor="is_active" className={'cursor-pointer'}>{t('Show on site')}</Label>
                <Switch className={'cursor-pointer'} id="is_active" checked={data.is_active} aria-invalid={!!errors.is_active} onCheckedChange={(checked) => setData('is_active', checked)} />
            </div>
            {message('is_active')}

            <div className="flex items-center justify-between gap-2">
                <span className="text-sm">{t('Status')}</span>
                <NodeStatusBadge status={nodeStatus(data.is_active, data.published_at, data.unpublished_at)} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="published_at">{t('Publication date')}</Label>
                <Input
                    id="published_at"
                    type="datetime-local"
                    value={data.published_at}
                    aria-invalid={!!errors.published_at}
                    onChange={(e) => setData('published_at', e.target.value)}
                />
                {message('published_at')}
            </div>

            <div className="space-y-2">
                <Label htmlFor="unpublished_at">{t('Show until')}</Label>
                <Input
                    id="unpublished_at"
                    type="datetime-local"
                    value={data.unpublished_at}
                    aria-invalid={!!errors.unpublished_at}
                    onChange={(e) => setData('unpublished_at', e.target.value)}
                />
                {message('unpublished_at')}
            </div>

            <div className="space-y-2">
                <Label htmlFor="slug">{t('Slug')}</Label>
                <div className="flex">
                    {slugPrefix && (
                        <span
                            data-testid="slug-prefix"
                            className="inline-flex items-center rounded-l-md border border-r-0 bg-muted px-3 text-sm text-muted-foreground"
                        >
                            {slugPrefix}
                        </span>
                    )}
                    <Input
                        id="slug"
                        value={data.slug}
                        aria-invalid={!!errors.slug}
                        className={slugPrefix ? 'rounded-l-none' : undefined}
                        onChange={(e) => setData('slug', slugPrefix && e.target.value.startsWith(slugPrefix) ? e.target.value.slice(slugPrefix.length) : e.target.value)}
                    />
                </div>
                <p className="text-sm text-muted-foreground">
                    {editing
                        ? t('The public identifier of the node on the site. Changing it does not change the node’s address in the admin.')
                        : slugPrefix
                          ? t('Leave empty to generate it automatically. The slug always starts with the prefix; lowercase Latin letters, digits and single hyphens.')
                          : t('Leave empty to generate it from the title. Lowercase Latin letters, digits and single hyphens.')}
                </p>
                {message('slug')}
            </div>

            {meta.id !== undefined && (
                <div className="flex items-center justify-between gap-2 text-sm">
                    <span>{t('ID')}</span>
                    <span data-testid="node-id" className="text-muted-foreground">
                        <CopyableText value={String(meta.id)}>{meta.id}</CopyableText>
                    </span>
                </div>
            )}

            {editing && (
                <div className="flex items-center justify-between gap-2 text-sm">
                    <span>{t('Updated')}</span>
                    <span className="text-muted-foreground" data-testid="node-updated-at">
                        {meta.updated_at ? meta.updated_at.replace('T', ' ') : '—'}
                    </span>
                </div>
            )}

            <div className="flex items-center justify-between gap-2 text-sm">
                <span>{t('Author')}</span>
                <span className="inline-flex items-center gap-2 text-muted-foreground">
                    {meta.author && <UserAvatar name={meta.author.name} src={meta.author.avatar} />}
                    <span data-testid="node-author">{meta.author?.name ?? '—'}</span>
                </span>
            </div>
        </div>
    );
}
