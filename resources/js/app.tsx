import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import type { ComponentType } from 'react';

/**
 * Inertia client entry point.
 *
 * Pages are resolved lazily from `resources/js/Pages/**\/*.tsx`, so the
 * component name passed to `Inertia::render('Admin/Dashboard')` maps to
 * `Pages/Admin/Dashboard.tsx`. A page may define a static `layout` property
 * to opt in to a persistent layout.
 */
createInertiaApp({
    resolve: async (name) => {
        const pages = import.meta.glob<{ default: ComponentType }>('./Pages/**/*.tsx');
        const page = pages[`./Pages/${name}.tsx`];

        if (!page) {
            throw new Error(`Inertia page not found: ${name}`);
        }

        return (await page()).default;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});

// `<html lang>` is rendered by Blade only on a full page load; keep it in sync
// with the interface language after client-side visits (e.g. `POST /locale`).
// `success` carries the freshly loaded page, `navigate` covers history
// back/forward restoring a page.
const syncHtmlLang = (page: { props: { locale: { current: string } } }) => {
    document.documentElement.lang = page.props.locale.current;
};

router.on('success', (event) => syncHtmlLang(event.detail.page));
router.on('navigate', (event) => syncHtmlLang(event.detail.page));
