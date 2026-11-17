import { ExternalLink } from 'lucide-react';

import { Breadcrumbs } from '@/Components/Admin/Breadcrumbs';
import { LocaleSwitcher } from '@/Components/Admin/LocaleSwitcher';
import { ThemeToggle } from '@/Components/Admin/ThemeToggle';
import { UserMenu } from '@/Components/Admin/UserMenu';
import { Button } from '@/Components/ui/button';
import { SidebarTrigger } from '@/Components/ui/sidebar';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * Top bar: sidebar trigger (hamburger on mobile), breadcrumbs, public site link, language switcher, theme toggle and user menu.
 * Surface rule: the page is `--background` (muted), panels such as this bar use `--card` (white); see docs/admin-visual-style.md.
 */
export function AdminHeader() {
    const { t } = useTranslation();

    return (
        <header className="flex h-14 shrink-0 items-center gap-2 border-b bg-card px-4">
            <SidebarTrigger aria-label={t('Toggle sidebar')} />
            <Breadcrumbs />
            <div className="ml-auto flex items-center gap-1">
                <Button variant="ghost" size="icon" asChild>
                    <a href="/" target="_blank" rel="noopener noreferrer" aria-label={t('Open site')} title={t('Open site')}>
                        <ExternalLink />
                    </a>
                </Button>
                <LocaleSwitcher />
                <ThemeToggle />
                <UserMenu />
            </div>
        </header>
    );
}
