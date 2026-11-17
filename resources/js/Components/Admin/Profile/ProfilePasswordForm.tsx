import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { FormSection } from '@/Components/Admin/FormSection';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * "Password" section of the profile page: the current password, the new one and its confirmation
 * (`PUT /admin/profile/password`). The fields are cleared after a successful change; a wrong current password
 * is shown next to its field, a short or unconfirmed new one next to the new password field.
 *
 * @example
 * <ProfilePasswordForm />
 */
export function ProfilePasswordForm() {
    const { t } = useTranslation();
    const { data, setData, put, processing, errors, reset } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put('/admin/profile/password', {
            preserveScroll: true,
            onSuccess: () => reset(),
            // A rejected attempt must not keep the secrets in the fields.
            onError: () => reset('password', 'password_confirmation'),
        });
    };

    const error = (field: keyof typeof errors) =>
        errors[field] && (
            <p role="alert" className="text-sm text-destructive">
                {errors[field]}
            </p>
        );

    return (
        <form onSubmit={submit} noValidate data-testid="profile-password-form">
            <FormSection as="section" title={t('Password')} titleId="profile-password-heading" className="max-w-xl space-y-4">
                <div className="space-y-2">
                    <Label htmlFor="current_password">{t('Current password')}</Label>
                    <Input
                        id="current_password"
                        type="password"
                        autoComplete="current-password"
                        value={data.current_password}
                        aria-invalid={!!errors.current_password}
                        onChange={(e) => setData('current_password', e.target.value)}
                    />
                    {error('current_password')}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="new_password">{t('New password')}</Label>
                    <Input
                        id="new_password"
                        type="password"
                        autoComplete="new-password"
                        value={data.password}
                        aria-invalid={!!errors.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <p className="text-sm text-muted-foreground">{t('At least 8 characters.')}</p>
                    {error('password')}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="password_confirmation">{t('Confirm new password')}</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        autoComplete="new-password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                    />
                </div>

                <Button type="submit" disabled={processing}>
                    {t('Change password')}
                </Button>
            </FormSection>
        </form>
    );
}
