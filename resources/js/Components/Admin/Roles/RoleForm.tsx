import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { FormActionBar } from '@/Components/Admin/FormActionBar';
import { PermissionMatrix, type RolePermissions, type TypeGroup } from '@/Components/Admin/Roles/PermissionMatrix';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { useTranslation } from '@/hooks/useTranslation';
import { FormSection } from '@/Components/Admin/FormSection';

/** A role as the form receives it. */
export type RoleValues = {
    id?: number;
    name: string;
    /** The system role is shown read-only. */
    is_system: boolean;
    permissions: RolePermissions;
};

type Props = {
    mode: 'create' | 'edit';
    role: RoleValues;
    groups: TypeGroup[];
};

/**
 * Shared form of the create and edit pages of a role: the name and the permission matrix. The system role is
 * shown read-only (inputs disabled, no Save), because the server refuses to change it.
 *
 * @example
 * <RoleForm mode="edit" role={role} groups={groups} />
 */
export function RoleForm({ mode, role, groups }: Props) {
    const { t } = useTranslation();
    const { data, setData, post, put, processing, errors } = useForm({ name: role.name, permissions: role.permissions });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (role.is_system) {
            return;
        }

        if (mode === 'edit') {
            put(`/admin/roles/${role.id}`);
        } else {
            post('/admin/roles');
        }
    };

    return (
        <form onSubmit={submit} noValidate>
            <div className="mb-6 max-w-2xl space-y-6">
                {role.is_system && (
                    <p role="status" className="rounded-md border bg-card p-3 text-sm">
                        {t('The system role cannot be changed.')}
                    </p>
                )}

                <FormSection className="space-y-2">
                    <Label htmlFor="name">{t('Name')}</Label>
                    <Input id="name" value={data.name} readOnly={role.is_system} aria-invalid={!!errors.name} onChange={(e) => setData('name', e.target.value)} />
                    {errors.name && (
                        <p role="alert" className="text-sm text-destructive">
                            {errors.name}
                        </p>
                    )}
                </FormSection>

                <PermissionMatrix value={data.permissions} groups={groups} readOnly={role.is_system} onChange={(permissions) => setData('permissions', permissions)} />
            </div>
            {role.is_system ? (
                <FormActionBar cancelHref="/admin/roles" processing hideSave />
            ) : (
                <FormActionBar cancelHref="/admin/roles" processing={processing} />
            )}
        </form>
    );
}
