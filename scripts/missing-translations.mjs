// Lists English keys used with t()/$t()/tc()/__() that lang/bn.json lacks.
// Usage: node scripts/missing-translations.mjs [--json]
// Tests\Unit\TranslationCoverageTest runs the same check in PHP.
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

const root = new URL('..', import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1');
const bn = JSON.parse(readFileSync(join(root, 'lang/bn.json'), 'utf8'));

const walk = (dir, exts) =>
    readdirSync(dir).flatMap((name) => {
        const path = join(dir, name);
        if (statSync(path).isDirectory()) return /ui$|actions$|routes$|wayfinder$/.test(path) ? [] : walk(path, exts);
        return exts.some((e) => name.endsWith(e)) ? [path] : [];
    });

const files = [
    ...walk(join(root, 'resources/js'), ['.vue', '.ts']),
    ...walk(join(root, 'app'), ['.php']),
    ...walk(join(root, 'bootstrap'), ['app.php']),
];
const call = /(?:\$tc?|\btc?|__)\(\s*(['"])((?:\\.|(?!\1).)*)\1(?:\s*,\s*(['"])((?:\\.|(?!\3).)*)\3)?/g;
// Keys held in data and translated where rendered (menus, steps, legends).
const property = /\b(?:title|label|text|description):\s*(['"])((?:\\.|(?!\1).)+)\1/g;
// Keys built at runtime (statuses, ranks, enum values) — see lang/bn.json.
const dynamic = JSON.parse(readFileSync(join(root, 'lang/dynamic-keys.json'), 'utf8'));
const missing = new Map();

for (const key of dynamic) {
    if (!(key in bn)) missing.set(key, 'lang/dynamic-keys.json');
}

for (const file of files) {
    const text = readFileSync(file, 'utf8');
    const memberJs = file.includes('resources') && !/[\\/]admin[\\/]/.test(file);
    const matches = [...text.matchAll(call), ...(memberJs ? text.matchAll(property) : [])];
    for (const m of matches) {
        const keys = [m[2]];
        if (m[0].includes('tc(') && m[4] !== undefined) keys.push(m[4]);
        for (const raw of keys) {
            const key = raw.replace(/\\(['"\\])/g, '$1');
            if (!(key in bn)) missing.set(key, file.replace(root, ''));
        }
    }
}

if (process.argv.includes('--json')) {
    console.log(JSON.stringify(Object.fromEntries([...missing.keys()].map((k) => [k, ''])), null, 4));
} else {
    for (const [key, file] of missing) console.log(`${file}: ${key}`);
    console.log(`${missing.size} missing`);
}
