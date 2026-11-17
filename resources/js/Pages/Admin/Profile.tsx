import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { PageTitle } from '@/Components/Admin/PageTitle';
import { ProfileAvatar } from '@/Components/Admin/Profile/ProfileAvatar';
import { ProfileDataForm } from '@/Components/Admin/Profile/ProfileDataForm';
import { ProfilePasswordForm } from '@/Components/Admin/Profile/ProfilePasswordForm';
import { ProfileSettings } from '@/Components/Admin/Profile/ProfileSettings';

type Props = {
    profile: { name: string; email: string; role: string | null; avatar: string | null };
};

/**
 * Profile of the signed-in user: the read-only role and the sections "Avatar", "Personal data", "Password" and "Settings".
 * Role and activity can only be changed by an administrator in the users section.
 */
export default function Profile({ profile }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Profile')} />
            <PageTitle>{t('Profile')}</PageTitle>
            <p className="mt-2 text-sm text-muted-foreground" data-testid="profile-role">
                {t('Role')}: {profile.role ?? '—'}
            </p>

            <div className="mt-6 space-y-6">
                <ProfileAvatar name={profile.name} avatar={profile.avatar} />
                <ProfileDataForm profile={profile} />
                <ProfilePasswordForm />
                <ProfileSettings />
            </div>
        </>
    );
}

Profile.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
