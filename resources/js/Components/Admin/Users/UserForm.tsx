import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { FormActionBar } from '@/Components/Admin/FormActionBar';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { useTranslation } from '@/hooks/useTranslation';
import { FormSection } from '@/Components/Admin/FormSection';

/** A user as the edit form receives it. */
export type UserValues = {
    id: number;
    name: string;
    email: string;
    role_id: number;
    is_active: boolean;
    /** True for the signed-in user: they cannot change their own role or deactivate themselves. */
    is_self: boolean;
};

type Props = {
    mode: 'create' | 'edit';
    roles: { id: number; name: string }[];
    /** The user being edited (required in `edit` mode). */
    user?: UserValues;
};

/**
 * Shared form of the create and edit pages of a user: name, email, password, role and activity. An empty password
 * keeps the current one when editing. The signed-in user's own role and activity are disabled (the server refuses
 * the change anyway). Server validation errors are shown next to their fields.
 *
 * @example
 * <UserForm mode="create" roles={roles} />
 * <UserForm mode="edit" roles={roles} user={user} />
 */
export function UserForm({ mode, roles, user }: Props) {
    const { t } = useTranslation();
    const editing = mode === 'edit';
    const { data, setData, post, put, processing, errors } = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        password: '',
        role_id: user?.role_id?.toString() ?? '',
        is_active: user?.is_active ?? true,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (editing) {
            // The page stays open after a successful save (`back()`), so the typed password must not linger in the field.
            put(`/admin/users/${user?.id}`, { onSuccess: () => setData('password', '') });
        } else {
            post('/admin/users');
        }
    };

    const error = (field: keyof typeof errors) =>
        errors[field] && (
            <p role="alert" className="text-sm text-destructive">
                {errors[field]}
            </p>
        );

    return (
        <form onSubmit={submit} noValidate>
            <FormSection className="mb-6 max-w-xl space-y-4">
                <div className="space-y-2">
                    <Label htmlFor="name">{t('Name')}</Label>
                    <Input id="name" value={data.name} aria-invalid={!!errors.name} onChange={(e) => setData('name', e.target.value)} />
                    {error('name')}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="email">{t('Email')}</Label>
                    <Input id="email" type="email" value={data.email} aria-invalid={!!errors.email} onChange={(e) => setData('email', e.target.value)} />
                    {error('email')}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="password">{t('Password')}</Label>
                    <Input
                        id="password"
                        type="password"
                        autoComplete="new-password"
                        value={data.password}
                        aria-invalid={!!errors.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <p className="text-sm text-muted-foreground">
                        {editing ? t('Leave empty to keep the current password.') : t('At least 8 characters.')}
                    </p>
                    {error('password')}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="role_id">{t('Role')}</Label>
                    <select
                        id="role_id"
                        value={data.role_id}
                        disabled={user?.is_self}
                        aria-invalid={!!errors.role_id}
                        onChange={(e) => setData('role_id', e.target.value)}
                        className="h-9 w-full rounded-md border bg-card px-2 text-sm disabled:opacity-60"
                    >
                        <option value="">{t('Select a role')}</option>
                        {roles.map((role) => (
                            <option key={role.id} value={role.id}>
                                {role.name}
                            </option>
                        ))}
                    </select>
                    {user?.is_self && <p className="text-sm text-muted-foreground">{t('You cannot change your own role.')}</p>}
                    {error('role_id')}
                </div>

                <div className="space-y-2">
                    <div className="flex items-center justify-between gap-4">
                        <Label htmlFor="is_active">{t('Active')}</Label>
                        <Switch id="is_active" checked={data.is_active} disabled={user?.is_self} onCheckedChange={(checked) => setData('is_active', checked)} />
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {user?.is_self ? t('You cannot deactivate yourself.') : t('A deactivated user cannot log in; their records keep the author.')}
                    </p>
                    {error('is_active')}
                </div>
            </FormSection>
            <FormActionBar cancelHref="/admin/users" processing={processing} />
        </form>
    );
}
