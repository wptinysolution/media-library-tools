/**
 * Formula protection of the admin CSV exports (src/js/Utils/csvSafety.ts).
 *
 * Runs with Node's built-in test runner on the TypeScript source, without a
 * build step or extra dependencies:
 *
 *     node --test tests/js/
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import Papa from 'papaparse';
import { NUMERIC_COLUMNS, protectCsvCell, protectCsvRow, toSafeCsv } from '../../src/js/Utils/csvSafety.ts';

const TRIGGERS = ['=', '+', '-', '@', '\t', '\r'];

/** Parse CSV text into rows keyed by the header. */
const parse = (csv: string): Record<string, string>[] =>
    Papa.parse<Record<string, string>>(csv, { header: true, skipEmptyLines: true }).data;

/** Undo protection: remove exactly one apostrophe from a protected cell. */
const unprotect = (cell: string): string => (/^''*[=+\-@\t\r]/.test(cell) ? cell.slice(1) : cell);

/** A row as the admin exports build it, with any fields overridden. */
const row = (fields: Record<string, unknown> = {}): Record<string, unknown> => ({
    ID: 12,
    slug: 'beach-photo',
    url: 'https://example.test/wp-content/uploads/2026/11/beach.jpg',
    title: 'Beach',
    caption: 'A caption',
    description: '<p>Description</p>',
    alt_text: 'Sand',
    groups: 'Holiday, Summer',
    post_parent: 7,
    menu_order: -2,
    ...fields,
});

test('every dangerous prefix is protected with one apostrophe', () => {
    for (const trigger of TRIGGERS) {
        const value = `${trigger}SUM(1)`;
        assert.equal(protectCsvCell(value), `'${value}`, JSON.stringify(value));
    }
});

test('ordinary text and other apostrophes are unchanged', () => {
    for (const value of ['Beach', "'Twas brillig", "it's", "''x", 'a=b', '日本 🙂', '', "'"]) {
        assert.equal(protectCsvCell(value), value, JSON.stringify(value));
    }
});

test('apostrophes before a formula get exactly one more, so the original is recoverable', () => {
    assert.equal(protectCsvCell("'=x"), "''=x");
    assert.equal(protectCsvCell("''+x"), "'''+x");
    for (const value of ['=x', "'=x", "''=x", "'Twas", '-2', '\tTab', 'plain']) {
        assert.equal(unprotect(protectCsvCell(value)), value, JSON.stringify(value));
    }
});

test('number columns are never changed, whatever their value', () => {
    const safe = protectCsvRow({ ID: 5, post_parent: 0, menu_order: -2, title: '-2' });
    assert.deepEqual(safe, { ID: 5, post_parent: 0, menu_order: -2, title: "'-2" });
    assert.deepEqual(protectCsvRow({ ID: '-1', post_parent: '-1', menu_order: '-3' }), { ID: '-1', post_parent: '-1', menu_order: '-3' });
    assert.deepEqual([...NUMERIC_COLUMNS], ['ID', 'post_parent', 'menu_order']);
});

test('a full export row: protected text, numbers kept, Unicode, quotes, commas and line breaks preserved', () => {
    const csv = toSafeCsv([
        row({
            title: '=HYPERLINK("http://evil.test","x")',
            caption: 'Line one\nLine two, with "quotes" and ünïcödé 日本',
            description: '@SUM(A1)',
            alt_text: '+1',
            groups: '-Group, Other',
            'custom_meta:credit': '\tTabbed',
            'custom_meta:plain': 'Jane, Doe',
        }),
    ]);
    const [parsed] = parse(csv);

    assert.equal(parsed.title, '\'=HYPERLINK("http://evil.test","x")');
    assert.equal(parsed.caption, 'Line one\nLine two, with "quotes" and ünïcödé 日本');
    assert.equal(parsed.description, "'@SUM(A1)");
    assert.equal(parsed.alt_text, "'+1");
    assert.equal(parsed.groups, "'-Group, Other");
    assert.equal(parsed['custom_meta:credit'], "'\tTabbed");
    assert.equal(parsed['custom_meta:plain'], 'Jane, Doe');
    assert.equal(parsed.ID, '12');
    assert.equal(parsed.post_parent, '7');
    assert.equal(parsed.menu_order, '-2');
    assert.equal(parsed.url, 'https://example.test/wp-content/uploads/2026/11/beach.jpg');
});

test('no text cell in an export starts with a formula character', () => {
    const values = ['=1+1', '+1', '-1', '@x', '\tx', '\rx', "'=x", 'ok'];
    const csv = toSafeCsv(values.map(value => row({ title: value, caption: value, description: value, alt_text: value })));
    for (const parsed of parse(csv)) {
        for (const [column, cell] of Object.entries(parsed)) {
            if (!NUMERIC_COLUMNS.includes(column)) {
                assert.ok(!/^[=+\-@\t\r]/.test(cell), `${column}: ${JSON.stringify(cell)}`);
            }
        }
    }
});

test('protection is applied once per export, and every cell is quoted', () => {
    const csv = toSafeCsv([row({ title: '=x', caption: "'=x" })]);
    const [parsed] = parse(csv);
    assert.equal(parsed.title, "'=x");
    assert.equal(parsed.caption, "''=x");
    assert.ok(csv.split('\r\n')[1].startsWith('"12",'), 'All values quoted, as before.');
});

test('objects become JSON text, which is not protected', () => {
    const [parsed] = parse(toSafeCsv([row({ 'custom_meta:list': { a: 1 } })]));
    assert.equal(parsed['custom_meta:list'], '{"a":1}');
});

test('the selected-row and full exports both build their CSV with toSafeCsv', () => {
    for (const file of ['src/js/Component/ListTable/DownloadCSV.tsx', 'src/js/Component/ExportImport/ExportCSV.tsx']) {
        const source = readFileSync(new URL(`../../${file}`, import.meta.url), 'utf8');
        assert.match(source, /toSafeCsv\(updatedData\)/, file);
        assert.doesNotMatch(source, /Papa\.unparse/, `${file} must not write CSV without protection`);
    }
});
