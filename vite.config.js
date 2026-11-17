import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import path from 'node:path';

/**
 * Vite config of the admin frontend of the package.
 *
 * The build is written to the committed directory `dist/build/` (manifest, `fonts-manifest.json`, `assets/*`);
 * the host receives it at `public/vendor/ecms/build` through the publish tag `ecms-assets`. The dev server writes
 * its hot file to `ECMS_HOT_FILE` (the host's `public/vendor/ecms/hot`), so a host gets hot reload of the admin.
 *
 * @example
 *   npm run build:package
 *   ECMS_HOT_FILE=/path/to/host/public/vendor/ecms/hot npm run dev
 */
export default defineConfig(({ command }) => ({
    // URLs inside the build (the font CSS in `fonts-manifest.json`, preload helpers) point to where the host serves it.
    base: command === 'build' ? '/vendor/ecms/build/' : undefined,
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            publicDirectory: 'dist',
            buildDirectory: 'build',
            hotFile: process.env.ECMS_HOT_FILE ?? 'dist/hot',
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(import.meta.dirname, 'resources/js'),
        },
    },
}));
