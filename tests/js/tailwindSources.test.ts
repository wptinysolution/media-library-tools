/**
 * Tailwind scans only the React app (src/css/index.css), so PHP templates,
 * docs and vendor code cannot add global !important utilities, and words
 * that match WordPress admin classes are never generated.
 *
 *     node --test tests/js/
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

const css = readFileSync(new URL('../../src/css/index.css', import.meta.url), 'utf8');
const EXCLUDED = ['inline', 'table', 'static', 'visible'];

const sources = (dir: string): string[] =>
    readdirSync(dir).flatMap((name) => {
        const path = join(dir, name);
        return statSync(path).isDirectory() ? sources(path) : /\.(tsx?|jsx?)$/.test(name) ? [path] : [];
    });

test('Tailwind scans only src/js', () => {
    assert.match(css, /@import "tailwindcss" source\(none\);/, 'No automatic detection of the whole plugin.');
    assert.match(css, /@source "\.\.\/js";/);
    assert.doesNotMatch(css, /@source "\.\.\/\.\.\/(app|docs|vendor|vendor_prefixed)/);
    assert.ok(css.indexOf('source(none)') < css.indexOf('@import "tw-animate-css"'));
});

test('admin class names used only as words are never generated', () => {
    const match = css.match(/@source not inline\("([^"]*)"\);/);
    assert.ok(match, 'The exclusion list exists.');
    assert.deepEqual(match[1].split(/\s+/).sort(), [...EXCLUDED].sort());
});

test('no React component relies on an excluded utility', () => {
    const root = new URL('../../src/js', import.meta.url).pathname;
    for (const file of sources(root)) {
        const source = readFileSync(file, 'utf8');
        // Every string literal: an excluded name standing alone among class
        // names (or alone) would be a class the build no longer generates.
        for (const literal of source.matchAll(/"([^"\n]*)"|'([^'\n]*)'|`([^`]*)`/g)) {
            const tokens = (literal[1] ?? literal[2] ?? literal[3] ?? '').split(/\s+/).filter(Boolean);
            const classy = tokens.length === 1 || tokens.some((token) => token.includes('-') || token.includes(':'));
            for (const name of EXCLUDED) {
                if (!classy || !tokens.includes(name)) continue;
                // A typed prop value (SaveButton's position) is not a class.
                const before = source.slice(Math.max(0, (literal.index ?? 0) - 40), literal.index ?? 0);
                if (/position\??:\s*['"]fixed['"]\s*\|\s*$|===\s*$|!==\s*$/.test(before)) continue;
                assert.fail(`${file.replace(root, 'src/js')}: "${name}" used as a class, but index.css excludes it`);
            }
        }
    }
});
