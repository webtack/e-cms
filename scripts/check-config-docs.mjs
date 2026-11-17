// Fails when a key of `config/*.php` or an environment variable read there is missing from docs/configuration.md.
// Usage: node scripts/check-config-docs.mjs   (add --list to print the keys that are required)
//
// A key is documented when the guide contains its dotted path in backticks, for example `content.views.ttl`.
// Registries whose entries are extension points rather than settings (field types, owners, upload profiles'
// inner keys, presets, social networks) are documented through their parent key; see REGISTRIES below.
import { readFileSync, readdirSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const guide = readFileSync(join(root, 'docs', 'configuration.md'), 'utf8');

/** Parent paths whose children are entries of a list or registry and need no line each. */
const REGISTRIES = ['content.field_types', 'ecms.locales', 'files.owners', 'files.styles.presets', 'social_networks'];

/**
 * Extracts the dotted paths of every string key (`'key' =>`) of a PHP config file and the names read with `env()`.
 *
 * @param {string} php the file source
 * @param {string} file the config file name without extension, the first path segment
 * @returns {{paths: string[], envs: string[]}}
 */
export function extract(php, file) {
    const paths = [];
    const envs = [];
    const stack = []; // one entry per open `[`: the key it belongs to (or null for a list)
    let pending = null; // the last `'key' =>` that has not met its value yet
    let i = 0;

    const skipString = (quote) => {
        let j = i + 1;
        while (j < php.length && php[j] !== quote) j += php[j] === '\\' ? 2 : 1;
        const text = php.slice(i + 1, j);
        i = j + 1;
        return text;
    };

    while (i < php.length) {
        const c = php[i];
        if (php.startsWith('/*', i)) {
            i = php.indexOf('*/', i) + 2;
        } else if (php.startsWith('//', i)) {
            while (i < php.length && php[i] !== '\n') i++;
        } else if (c === "'" || c === '"') {
            const before = php.slice(Math.max(0, i - 4), i);
            const text = skipString(c);
            const after = php.slice(i).match(/^\s*(=>)?/);
            if (/env\($/.test(before) && /^[A-Z][A-Z0-9_]*$/.test(text)) envs.push(text);
            else if (after[1]) {
                pending = text;
                paths.push([file, ...stack.filter((k) => k !== null), text].join('.'));
            }
        } else if (c === '[') {
            stack.push(pending);
            pending = null;
            i++;
        } else if (c === ']') {
            stack.pop();
            pending = null;
            i++;
        } else {
            if (c === ',') pending = null;
            i++;
        }
    }
    return { paths, envs };
}

const required = new Set();
const envs = new Set();
for (const name of readdirSync(join(root, 'config')).filter((n) => n.endsWith('.php'))) {
    const result = extract(readFileSync(join(root, 'config', name), 'utf8'), name.replace(/\.php$/, ''));
    result.paths.forEach((p) => required.add(p));
    result.envs.forEach((e) => envs.add(e));
}

// The top-level file itself is a "path" only for single-segment names such as `social_networks.facebook`.
const missing = [];
for (const path of required) {
    if (REGISTRIES.some((reg) => path.startsWith(`${reg}.`)) || path.includes('.mimes.')) continue;
    if (!guide.includes(`\`${path}\``)) missing.push(path);
}
for (const name of envs) {
    if (!guide.includes(name)) missing.push(`env ${name}`);
}
for (const reg of REGISTRIES) {
    if (!guide.includes(`\`${reg}\``)) missing.push(reg);
}

if (process.argv.includes('--list')) {
    console.log([...required, ...[...envs].map((e) => `env ${e}`)].join('\n'));
}

if (missing.length) {
    console.error(`docs/configuration.md does not mention:\n  ${missing.join('\n  ')}`);
    process.exit(1);
}
console.log(`All ${required.size} config keys and ${envs.size} environment variables are documented.`);
