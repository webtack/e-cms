import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { BrandLogo } from '@/Components/Admin/BrandLogo';
import { LocaleSwitcher } from '@/Components/Admin/LocaleSwitcher';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { useTranslation } from '@/hooks/useTranslation';
import authBackground from '../../../images/auth-bg.png';

/**
 * Login page: email + password form submitted to `POST /login`.
 * Validation errors (including throttling) arrive in `errors.email`.
 * Looks: the full-page background `resources/images/auth-bg.png` (bundled by Vite), and the form in a `bg-card` block with the logo and the site
 * name (shared prop `site.name`) at its top; the language switcher sits in the top-right corner on a card-coloured chip.
 */
export default function Login() {
    const { props } = usePage();
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <>
            <Head title={t('Log in')} />
            <div
                className="flex min-h-svh items-center justify-center bg-cover bg-center p-6"
                style={{ backgroundImage: `url('${authBackground}')` }}
            >
                <div className="absolute right-4 top-4 rounded-md bg-card/90 shadow-sm">
                    <LocaleSwitcher />
                </div>
                <form onSubmit={submit} className="w-full max-w-sm space-y-4 rounded-xl border bg-card p-6 shadow-sm" data-testid="login-card">
                    <div className="space-y-2">
                        <BrandLogo />
                        <p className="truncate text-sm font-semibold" data-testid="login-site-name">
                            {props.site.name}
                        </p>
                    </div>
                    <h1 className="text-2xl font-semibold">{t('Log in')}</h1>
                    <div className="space-y-2">
                        <Label htmlFor="email">{t('Email')}</Label>
                        <Input
                            id="email"
                            type="email"
                            autoComplete="username"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                        {errors.email && (
                            <p role="alert" className="text-sm text-destructive">
                                {errors.email}
                            </p>
                        )}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="password">{t('Password')}</Label>
                        <Input
                            id="password"
                            type="password"
                            autoComplete="current-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                        {errors.password && (
                            <p role="alert" className="text-sm text-destructive">
                                {errors.password}
                            </p>
                        )}
                    </div>
                    <Button type="submit" className="w-full" disabled={processing}>
                        {t('Log in')}
                    </Button>
                </form>
            </div>
        </>
    );
}
