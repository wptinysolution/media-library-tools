/**
 * CSV import summary counts (src/js/Utils/importCounts.ts).
 *
 *     node --test tests/js/
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { importCounts } from '../../src/js/Utils/importCounts.ts';

test('imported rows with a message are counted as warnings, not as clean imports', () => {
    assert.deepEqual(
        importCounts([
            { status: 'uploaded' },
            { status: 'uploaded', message: 'The other fields were saved, but the file was not renamed: the file is missing from the server.' },
            { status: 'failed', message: 'WordPress refused the update.' },
            { status: 'not_found' },
            { status: 'skipped' },
        ]),
        { imported: 2, warnings: 1, failed: 3 },
    );
});

test('rows without warnings keep the existing counts', () => {
    assert.deepEqual(importCounts([{ status: 'uploaded' }, { status: 'uploaded' }]), { imported: 2, warnings: 0, failed: 0 });
    assert.deepEqual(importCounts([]), { imported: 0, warnings: 0, failed: 0 });
});
