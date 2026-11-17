import { useEffect, useState } from 'react';

/**
 * Whether a CSS media query currently matches, updated when the window changes.
 *
 * Returns `undefined` until the first evaluation (server render and the first client render), so a caller can
 * avoid rendering both variants of a layout at once.
 *
 * @example
 * const isDesktop = useMediaQuery('(min-width: 1024px)'); // true from the `lg` breakpoint, undefined at first
 */
export function useMediaQuery(query: string): boolean | undefined {
    const [matches, setMatches] = useState<boolean | undefined>(undefined);

    useEffect(() => {
        const list = window.matchMedia(query);
        const update = () => setMatches(list.matches);

        update();
        list.addEventListener('change', update);

        return () => list.removeEventListener('change', update);
    }, [query]);

    return matches;
}
