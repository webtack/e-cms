import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';
import fs from 'node:fs';

/**
 * Vite config of a single site theme, separate from the admin build (`vite.config.js`).
 *
 * The theme is chosen with the `THEME` environment variable. Entries follow a convention: only
 * `themes/<name>/assets/{vendor,app}.{css,scss,js}` that exist become entries; every other file in
 * `assets/` is a partial that is imported, never built on its own.
 *
 * Output goes to `public/themes/<name>/` (with its own `manifest.json`), the dev server writes
 * `public/themes/<name>.hot` and listens on `THEME_DEV_PORT` (5174 by default), so it never clashes with
 * the admin build (port 5173). The same file is used by the package and, published with `ecms-theme-tooling`,
 * by a host. With `ECMS_THEME_DIST=1` (the package's `build:themes`) the public directory is `dist/`, so the
 * output is `dist/themes/<name>/` and the static files go to `dist/app/<name>/`.
 *
 * @example
 *   THEME=default npx vite build -c vite.theme.config.js
 *   THEME=default npx vite -c vite.theme.config.js
 *   ECMS_THEME_DIST=1 THEME=default npx vite build -c vite.theme.config.js   // package: dist/themes/default
 */
const publicDir = process.env.ECMS_THEME_DIST ? 'dist' : 'public';
const theme = process.env.THEME;

if (!theme) {
    throw new Error('THEME is not set. Use `npm run theme:build -- <name>` or `npm run theme:dev -- <name>`.');
}

const assets = path.resolve(import.meta.dirname, 'themes', theme, 'assets');

/** Existing entries of the theme, as paths relative to the project root. */
const input = ['vendor', 'app']
    .flatMap((name) => ['css', 'scss', 'js'].map((ext) => `${name}.${ext}`))
    .filter((file) => fs.existsSync(path.join(assets, file)))
    .map((file) => `themes/${theme}/assets/${file}`);

if (input.length === 0) {
    throw new Error(`Theme "${theme}" has no entries: expected themes/${theme}/assets/{vendor,app}.{css,scss,js}.`);
}

/**
 * Copies the theme's static files (`assets/fonts/`, `assets/images/`) into `public/app/<theme>/`, so
 * templates and theme CSS can reference them as plain URLs (`/app/<theme>/images/<file>`).
 *
 * Runs at `buildStart` (both `vite build` and the dev server). The target is outside Vite's output
 * folder, so the build's clearing never erases it. Does nothing when the theme has neither folder,
 * writes only inside `public/app/<theme>/`, and skips files whose size and mtime are unchanged.
 *
 * @returns {import('vite').Plugin}
 * @example
 *   // themes/dcculture/assets/images/logo.png -> public/app/dcculture/images/logo.png
 *   plugins: [copyThemeStatic()]
 */
function copyThemeStatic() {
    const target = path.resolve(import.meta.dirname, publicDir, 'app', theme);

    /** Copies `from` into `to` recursively, skipping files that are already up to date. */
    const copyDir = (from, to) => {
        fs.mkdirSync(to, { recursive: true });

        for (const entry of fs.readdirSync(from, { withFileTypes: true })) {
            const source = path.join(from, entry.name);
            const destination = path.join(to, entry.name);

            if (entry.isDirectory()) {
                copyDir(source, destination);
                continue;
            }

            const src = fs.statSync(source);
            const dst = fs.existsSync(destination) ? fs.statSync(destination) : null;

            if (dst && dst.size === src.size && dst.mtimeMs >= src.mtimeMs) {
                continue;
            }

            fs.copyFileSync(source, destination);
        }
    };

    return {
        name: 'copy-theme-static',
        buildStart() {
            for (const dir of ['fonts', 'images']) {
                const from = path.join(assets, dir);

                if (fs.existsSync(from)) {
                    copyDir(from, path.join(target, dir));
                }
            }
        },
    };
}

/**
 * Dev-server proxy for the theme's static files.
 *
 * A stylesheet loaded from the dev server (`http://[::1]:5174/...`) resolves root-absolute URLs such as
 * `url(/app/<theme>/images/bg.png)` against the dev server origin, but those files are served only by
 * the application. Forwards `/app` to `APP_URL` (from `.env`), so the same URLs load in dev as in build.
 * Applies to the dev server only; `vite build` ignores it. Entries and `/@vite/client` are not under
 * `/app`, so the dev server still answers them itself.
 *
 * @param {string} mode Vite mode, used to pick the `.env` files.
 * @returns {Record<string, import('vite').ProxyOptions>}
 * @example
 *   // GET http://localhost:5174/app/dcculture/fonts/inter.woff2 -> http://localhost:8080/app/dcculture/fonts/inter.woff2
 *   server: { proxy: appStaticProxy(mode) }
 */
function appStaticProxy(mode) {
    const appUrl = loadEnv(mode, import.meta.dirname, '').APP_URL;

    if (!appUrl) {
        throw new Error('APP_URL is not set in .env. The theme dev server forwards /app/... static files to it.');
    }

    return { '/app': { target: appUrl, changeOrigin: true } };
}

export default defineConfig(({ command, mode }) => ({
    plugins: [
        copyThemeStatic(),
        laravel({
            input,
            publicDirectory: publicDir,
            buildDirectory: `themes/${theme}`,
            hotFile: `${publicDir}/themes/${theme}.hot`,
        }),
        tailwindcss(),
    ],
    server: {
        port: Number(process.env.THEME_DEV_PORT) || 5174,
        strictPort: true,
        proxy: command === 'serve' ? appStaticProxy(mode) : undefined,
    },
}));
