// Checks every relative link and heading anchor in the package's Markdown documents.
// Usage: node scripts/check-docs.mjs   (exit code 1 when a link is broken)
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

/** Markdown files that are checked: the root documents and everything under docs/. */
function collect() {
    const files = readdirSync(root)
        .filter((name) => name.endsWith('.md'))
        .map((name) => join(root, name));
    const walk = (dir) => {
        for (const name of readdirSync(dir)) {
            const path = join(dir, name);
            if (statSync(path).isDirectory()) walk(path);
            else if (name.endsWith('.md')) files.push(path);
        }
    };
    walk(join(root, 'docs'));
    return files;
}

/** Removes fenced code blocks so that links inside examples are not checked. */
const stripCode = (text) => text.replace(/^(```|~~~)[\s\S]*?^\1/gm, '');

/** GitHub-style heading slug. */
function slug(heading) {
    return heading
        .trim()
        .toLowerCase()
        .replace(/`/g, '')
        .replace(/\[([^\]]*)\]\([^)]*\)/g, '$1')
        .replace(/[^\p{L}\p{N}\s_-]/gu, '')
        .replace(/\s/g, '-');
}

const anchorCache = new Map();
function anchors(file) {
    if (!anchorCache.has(file)) {
        const raw = readFileSync(file, 'utf8');
        const seen = new Map();
        const set = new Set();
        for (const m of raw.matchAll(/<a\s+(?:id|name)="([^"]+)"/g)) set.add(m[1]);
        for (const line of stripCode(raw).split('\n')) {
            const m = /^#{1,6}\s+(.*?)\s*#*$/.exec(line);
            if (!m) continue;
            const base = slug(m[1]);
            const n = seen.get(base) ?? 0;
            seen.set(base, n + 1);
            set.add(n === 0 ? base : `${base}-${n}`);
        }
        anchorCache.set(file, set);
    }
    return anchorCache.get(file);
}

const errors = [];
for (const file of collect()) {
    const text = stripCode(readFileSync(file, 'utf8')).replace(/`[^`\n]*`/g, '');
    for (const m of text.matchAll(/\[[^\]]*\]\(([^)\s]+)(?:\s+"[^"]*")?\)/g)) {
        const target = m[1];
        if (/^[a-z][a-z0-9+.-]*:/i.test(target)) continue; // external
        const [pathPart, hash] = target.split('#');
        const dest = pathPart === '' ? file : resolve(dirname(file), pathPart);
        const rel = relative(root, file);
        if (!existsSync(dest)) {
            errors.push(`${rel}: missing file ${target}`);
            continue;
        }
        if (hash && dest.endsWith('.md') && !anchors(dest).has(decodeURIComponent(hash))) {
            errors.push(`${rel}: missing anchor ${target}`);
        }
    }
}

if (errors.length) {
    console.error(errors.join('\n'));
    console.error(`\n${errors.length} broken link(s).`);
    process.exit(1);
}
console.log('All relative links and anchors are valid.');
