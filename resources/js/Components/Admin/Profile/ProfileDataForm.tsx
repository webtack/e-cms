import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { FormSection } from '@/Components/Admin/FormSection';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    /** The signed-in user's current name and email. */
    profile: { name: string; email: string };
};

/**
 * "Personal data" section of the profile page: edits the user's own name and email (`PUT /admin/profile`).
 * Server validation errors are shown next to their fields; the page stays open after a save.
 *
 * @example
 * <ProfileDataForm profile={{ name: 'Anna', email: 'anna@example.com' }} />
 */
export function ProfileDataForm({ profile }: Props) {
    const { t } = useTranslation();
    const { data, setData, put, processing, errors } = useForm({ name: profile.name, email: profile.email });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put('/admin/profile', { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} noValidate data-testid="profile-data-form">
            <FormSection as="section" title={t('Personal data')} titleId="profile-data-heading" className="max-w-xl space-y-4">
                <div className="space-y-2">
                    <Label htmlFor="profile-name">{t('Name')}</Label>
                    <Input id="profile-name" value={data.name} aria-invalid={!!errors.name} onChange={(e) => setData('name', e.target.value)} />
                    {errors.name && (
                        <p role="alert" className="text-sm text-destructive">
                            {errors.name}
                        </p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="profile-email">{t('Email')}</Label>
                    <Input
                        id="profile-email"
                        type="email"
                        value={data.email}
                        aria-invalid={!!errors.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    {errors.email && (
                        <p role="alert" className="text-sm text-destructive">
                            {errors.email}
                        </p>
                    )}
                </div>

                <Button type="submit" disabled={processing}>
                    {t('Save')}
                </Button>
            </FormSection>
        </form>
    );
}
