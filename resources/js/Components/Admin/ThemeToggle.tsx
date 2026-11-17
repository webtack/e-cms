import { Moon, Sun } from 'lucide-react';

import { useTheme } from '@/Components/Admin/ThemeProvider';
import { Button } from '@/Components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * Header button that switches between the light and dark theme.
 *
 * @example
 * <ThemeToggle />
 */
export function ThemeToggle() {
    const { resolvedTheme, setTheme } = useTheme();
    const { t } = useTranslation();
    const isDark = resolvedTheme === 'dark';

    return (
        <Button
            variant="ghost"
            size="icon"
            aria-label={isDark ? t('Switch to light theme') : t('Switch to dark theme')}
            onClick={() => setTheme(isDark ? 'light' : 'dark')}
        >
            {isDark ? <Sun /> : <Moon />}
        </Button>
    );
}
