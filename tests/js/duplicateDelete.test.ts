/**
 * Move to Trash on the Duplicates page (Free): the confirmation dialog's
 * fixed text and how the server's answer is read
 * (src/js/Utils/duplicateDelete.ts).
 *
 *     node --test tests/js/
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { TRASH_DIALOG, interpretTrashResponse } from '../../src/js/Utils/duplicateDelete.ts';

test('the dialog uses the agreed text', () => {
    assert.deepEqual(TRASH_DIALOG, {
        title: 'Move Image to Trash?',
        warning: 'If this image is used anywhere on your website, moving it to Trash may cause it to disappear from posts, pages, products, or other locations. This could also create broken image URLs.',
        promoTitle: 'Want to avoid broken images?',
        promoText: 'Upgrade to Media Library Tools Pro and use Duplicate Merge. It replaces supported references to the duplicate image with the image you want to keep before moving the duplicate to Trash, helping reduce the risk of broken images.',
        promoAction: 'Explore Pro – Merge Duplicates',
        cancel: 'Cancel',
        confirm: 'Move to Trash Anyway',
        footer: 'The image will be moved to Trash, not permanently deleted.',
    });
});

test('a moved image needs updated true', () => {
    assert.deepEqual(interpretTrashResponse({ updated: true, code: '', message: 'Moved.' }), { kind: 'trashed', message: 'Moved.' });
});

test('refusals keep their code and message', () => {
    for (const code of ['forbidden', 'trash_disabled', 'locked', 'already_trashed', 'site_media', 'not_found', 'trash_failed']) {
        assert.deepEqual(interpretTrashResponse({ updated: false, code, message: `m-${code}` }), { kind: 'refused', code, message: `m-${code}` });
    }
});

test('anything else is a refusal, never a success', () => {
    for (const data of ['0', 0, null, undefined, '', { message: 'Unauthorized.' }, { updated: 'yes' }]) {
        assert.equal(interpretTrashResponse(data).kind, 'refused', JSON.stringify(data));
    }
    assert.equal(interpretTrashResponse({ message: 'Unauthorized.' }).message, 'Unauthorized.');
});

test('the page shows the dialog before every move, for Free and Pro alike', () => {
    const page = readFileSync(new URL('../../src/js/Component/Duplicate/DuplicatePage.tsx', import.meta.url), 'utf8');
    const open = page.slice(page.indexOf('const openDeleteModal'), page.indexOf('const closeDeleteModal'));
    assert.match(open, /setDeleteItem\(item\)/, 'The button only opens the dialog.');
    assert.doesNotMatch(open, /hasExtended|openProModal|trashDuplicate/, 'No Pro gate and no request before confirming.');
    const handle = page.slice(page.indexOf('const handleDelete'), page.indexOf('const openMergeModal'));
    assert.match(handle, /trashDuplicate\(\{ attachment_id: deleteItem\.attachment_id \}\)/, 'Only the attachment ID is sent.');
    assert.match(handle, /deletingRef\.current\) return/, 'Repeated clicks are ignored.');
    assert.match(handle, /if \(outcome\.kind === 'trashed'\)/, 'Results reload only after a successful move.');
    const button = page.slice(page.indexOf('onClick={() => openDeleteModal(item)}') - 400, page.indexOf('onClick={() => openDeleteModal(item)}') + 300);
    assert.doesNotMatch(button, /ProLabel/, 'No Pro badge on Move to Trash.');
});
