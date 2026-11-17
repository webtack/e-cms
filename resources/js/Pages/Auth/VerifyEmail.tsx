import { Head, router } from '@inertiajs/react';

import { Button } from '@/Components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * Shown when an authenticated user has not verified their email yet.
 * Sending verification emails is out of scope; the user can only log out.
 */
export default function VerifyEmail() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Email not verified')} />
            <div className="flex min-h-svh items-center justify-center p-6">
                <div className="w-full max-w-sm space-y-4">
                    <h1 className="text-2xl font-semibold">{t('Email not verified')}</h1>
                    <p>{t('Your email address has not been verified, so the admin area is unavailable.')}</p>
                    <Button type="button" onClick={() => router.post('/logout')}>
                        {t('Log out')}
                    </Button>
                </div>
            </div>
        </>
    );
}
