import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

export type Theme = 'light' | 'dark' | 'system';

type ThemeContextValue = {
    /** The user's choice; `system` follows `prefers-color-scheme`. */
    theme: Theme;
    /** The theme actually applied to the page. */
    resolvedTheme: 'light' | 'dark';
    setTheme: (theme: Theme) => void;
};

const STORAGE_KEY = 'theme';
const ThemeContext = createContext<ThemeContextValue | null>(null);

function readStoredTheme(): Theme {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored === 'light' || stored === 'dark' || stored === 'system') {
            return stored;
        }
    } catch {
        // localStorage can be unavailable (privacy mode); fall back to system.
    }

    return 'system';
}

function systemPrefersDark(): boolean {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

/**
 * Provides the current theme and toggles the `dark` class on `<html>`.
 * The choice is stored in `localStorage`; with no stored choice the system
 * theme is used. The inline script in `app.blade.php` applies the same
 * logic before first paint to avoid a flash.
 *
 * @example
 * <ThemeProvider><App /></ThemeProvider>
 */
export function ThemeProvider({ children }: { children: ReactNode }) {
    const [theme, setThemeState] = useState<Theme>(readStoredTheme);
    const [systemDark, setSystemDark] = useState<boolean>(systemPrefersDark);

    useEffect(() => {
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = (event: MediaQueryListEvent) => setSystemDark(event.matches);
        media.addEventListener('change', onChange);

        return () => media.removeEventListener('change', onChange);
    }, []);

    const resolvedTheme = theme === 'system' ? (systemDark ? 'dark' : 'light') : theme;

    useEffect(() => {
        document.documentElement.classList.toggle('dark', resolvedTheme === 'dark');
    }, [resolvedTheme]);

    const setTheme = useCallback((next: Theme) => {
        setThemeState(next);
        try {
            localStorage.setItem(STORAGE_KEY, next);
        } catch {
            // Ignore storage failures; the choice still applies for this session.
        }
    }, []);

    const value = useMemo(() => ({ theme, resolvedTheme, setTheme }), [theme, resolvedTheme, setTheme]);

    return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

/**
 * Access the current theme and its setter. Must be used inside `ThemeProvider`.
 *
 * @example
 * const { resolvedTheme, setTheme } = useTheme();
 * setTheme(resolvedTheme === 'dark' ? 'light' : 'dark');
 */
export function useTheme(): ThemeContextValue {
    const context = useContext(ThemeContext);

    if (!context) {
        throw new Error('useTheme must be used within ThemeProvider');
    }

    return context;
}
