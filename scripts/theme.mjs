import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

/**
 * Runs the theme Vite config (`vite.theme.config.js`) for one theme or, for `build`, for all of them.
 *
 * Usage: `node scripts/theme.mjs <dev|build> [name]`. `dev` requires a theme name. `build` without a name
 * builds every theme that has an `assets/` directory, one after another; a theme named explicitly but
 * without `assets/` is an error (non-zero exit code).
 *
 * @example
 *   npm run theme:dev -- default
 *   npm run theme:build            // all themes with assets/
 *   npm run theme:build -- default // one theme
 */
const root = path.resolve(import.meta.dirname, '..');
const themesDir = path.join(root, 'themes');
const [command, name] = process.argv.slice(2);

/** Print an error and stop with a non-zero code. */
function fail(message) {
    console.error(message);
    process.exit(1);
}

/** Whether `themes/<theme>/assets` exists. */
function hasAssets(theme) {
    return fs.existsSync(path.join(themesDir, theme, 'assets'));
}

/** Run Vite for a theme; exits the process on failure. */
function run(theme, args) {
    const vite = path.join(root, 'node_modules', 'vite', 'bin', 'vite.js');
    const result = spawnSync(process.execPath, [vite, ...args, '-c', 'vite.theme.config.js'], {
        cwd: root,
        stdio: 'inherit',
        env: { ...process.env, THEME: theme },
    });

    if (result.status !== 0) {
        fail(`Theme "${theme}": vite ${args[0] ?? 'dev'} failed.`);
    }
}

if (command === 'dev') {
    if (!name) {
        fail('Theme name is required: npm run theme:dev -- <name>');
    }

    if (!hasAssets(name)) {
        fail(`Theme "${name}" has no assets/ directory (themes/${name}/assets).`);
    }

    run(name, []);
} else if (command === 'build') {
    if (name) {
        if (!hasAssets(name)) {
            fail(`Theme "${name}" has no assets/ directory (themes/${name}/assets).`);
        }

        run(name, ['build']);
    } else {
        const themes = fs.existsSync(themesDir)
            ? fs.readdirSync(themesDir, { withFileTypes: true })
                .filter((entry) => entry.isDirectory() && hasAssets(entry.name))
                .map((entry) => entry.name)
            : [];

        if (themes.length === 0) {
            console.log('No themes with assets/ to build.');
        }

        themes.forEach((theme) => run(theme, ['build']));
    }
} else {
    fail('Usage: node scripts/theme.mjs <dev|build> [name]');
}
