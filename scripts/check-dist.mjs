import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

/**
 * Freshness check of the committed build: rebuilds the admin bundle into a temporary directory and compares the
 * file names of `assets/` (content hashes are part of the names) with `dist/build/assets/`. Exits with a non-zero
 * code and lists the differences when they do not match, so CI and the release checklist catch a source change
 * that was committed without `npm run build:package`.
 *
 * @example
 *   npm run check:dist   // "dist/build is up to date" or a list of missing/stale files
 */
const root = path.resolve(import.meta.dirname, '..');
const committed = path.join(root, 'dist', 'build');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'ecms-dist-'));

/** Sorted names of the files in `assets/` of a build directory. */
const names = (dir) => {
    const assets = path.join(dir, 'assets');

    return fs.existsSync(assets) ? fs.readdirSync(assets).sort() : [];
};

try {
    const vite = path.join(root, 'node_modules', 'vite', 'bin', 'vite.js');
    const result = spawnSync(process.execPath, [vite, 'build', '--outDir', tmp, '--emptyOutDir'], {
        cwd: root,
        stdio: 'ignore',
    });

    if (result.status !== 0) {
        console.error('check:dist: the temporary build failed; run `npm run build:package` to see the error.');
        process.exit(1);
    }

    const fresh = names(tmp);
    const current = names(committed);
    const missing = fresh.filter((name) => !current.includes(name));
    const stale = current.filter((name) => !fresh.includes(name));

    if (missing.length === 0 && stale.length === 0) {
        console.log('check:dist: dist/build is up to date.');
    } else {
        console.error('check:dist: dist/build does not match the sources. Run `npm run build:package` and commit dist/.');
        missing.forEach((name) => console.error(`  missing: ${name}`));
        stale.forEach((name) => console.error(`  stale:   ${name}`));
        process.exit(1);
    }
} finally {
    fs.rmSync(tmp, { recursive: true, force: true });
}
