import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { useTranslation } from '@/hooks/useTranslation';
import type { AccessLevel } from '@/types';
import { FormSection } from '@/Components/Admin/FormSection';

/** The permissions of a role as the form edits them. */
export type RolePermissions = {
    /** A switch per system permission. */
    system: Record<string, boolean>;
    /** A level per content type key. */
    nodes: Record<string, AccessLevel>;
    media: AccessLevel;
};

/** The types of one kind, in the order the matrix lists them. */
export type TypeGroup = {
    kind: 'content' | 'page' | 'block';
    types: { key: string; name: string }[];
};

type Props = {
    value: RolePermissions;
    groups: TypeGroup[];
    /** True for the system role: everything is shown but nothing can be changed. */
    readOnly?: boolean;
    onChange: (value: RolePermissions) => void;
};

/** System permissions in the order of the form: translation key of the label per permission key. */
const SYSTEM_LABELS: [string, string][] = [
    ['users', 'Manage users'],
    ['roles', 'Manage roles'],
    ['menus', 'Manage menus'],
    ['taxonomy', 'Manage taxonomy'],
    ['types', 'Manage content types'],
    ['settings', 'Site settings'],
];

const GROUP_TITLES: Record<TypeGroup['kind'], string> = {
    content: 'Content types',
    page: 'Page types',
    block: 'Block types',
};

const LEVELS: [AccessLevel, string][] = [
    ['none', 'No access'],
    ['own', 'Own records'],
    ['any', 'Any records'],
];

/** A native select of the three levels; the label is the row's name. */
function LevelSelect({ id, label, value, disabled, onChange }: { id: string; label: string; value: AccessLevel; disabled: boolean; onChange: (level: AccessLevel) => void }) {
    const { t } = useTranslation();

    return (
        <div className="flex items-center justify-between gap-4 py-2">
            <Label htmlFor={id} className="min-w-0 truncate font-normal">
                {label}
            </Label>
            <select
                id={id}
                value={value}
                disabled={disabled}
                onChange={(e) => onChange(e.target.value as AccessLevel)}
                className="h-9 rounded-md border bg-card px-2 text-sm disabled:opacity-60"
            >
                {LEVELS.map(([level, name]) => (
                    <option key={level} value={level}>
                        {t(name)}
                    </option>
                ))}
            </select>
        </div>
    );
}

/**
 * The permission matrix of a role: switches for the system permissions, then a level (`none` / `own` / `any`) per
 * content type grouped by kind and one for the media library. `own` limits a user to the records they authored (or,
 * for media, uploaded). The component only edits the value; the server validates and stores it.
 *
 * @example
 * <PermissionMatrix value={data.permissions} groups={groups} readOnly={role.is_system} onChange={(v) => setData('permissions', v)} />
 */
export function PermissionMatrix({ value, groups, readOnly = false, onChange }: Props) {
    const { t } = useTranslation();

    return (
        <div className="space-y-8">
            <FormSection as="section" aria-labelledby="perm-system">
                <h2 id="perm-system" className="mb-2 text-lg font-semibold">
                    {t('Sections')}
                </h2>
                {SYSTEM_LABELS.map(([key, label]) => (
                    <div key={key} className="flex items-center justify-between gap-4 py-2">
                        <Label htmlFor={`perm-system-${key}`} className="font-normal">
                            {t(label)}
                        </Label>
                        <Switch
                            id={`perm-system-${key}`}
                            checked={value.system[key] ?? false}
                            disabled={readOnly}
                            onCheckedChange={(checked) => onChange({ ...value, system: { ...value.system, [key]: checked } })}
                        />
                    </div>
                ))}
            </FormSection>

            {groups.map((group) => (
                <FormSection as="section" key={group.kind} aria-labelledby={`perm-${group.kind}`}>
                    <h2 id={`perm-${group.kind}`} className="mb-2 text-lg font-semibold">
                        {t(GROUP_TITLES[group.kind])}
                    </h2>
                    {group.types.length === 0 ? (
                        <p className="text-sm text-muted-foreground">{t('No types yet.')}</p>
                    ) : (
                        group.types.map((type) => (
                            <LevelSelect
                                key={type.key}
                                id={`perm-node-${type.key}`}
                                label={type.name}
                                value={value.nodes[type.key] ?? 'none'}
                                disabled={readOnly}
                                onChange={(level) => onChange({ ...value, nodes: { ...value.nodes, [type.key]: level } })}
                            />
                        ))
                    )}
                </FormSection>
            ))}

            <FormSection as="section" aria-labelledby="perm-media">
                <h2 id="perm-media" className="mb-2 text-lg font-semibold">
                    {t('Media library')}
                </h2>
                <LevelSelect id="perm-media-level" label={t('Files')} value={value.media} disabled={readOnly} onChange={(level) => onChange({ ...value, media: level })} />
            </FormSection>
        </div>
    );
}
