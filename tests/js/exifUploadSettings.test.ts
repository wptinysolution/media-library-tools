/**
 * The Pro-only "Remove GPS Location on Upload" toggle in EXIF on Upload
 * (src/js/Utils/exifUploadSettings.ts, src/js/Component/Settings/ExifSettings.tsx).
 *
 *     node --test tests/js/
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { GPS_ON_UPLOAD, gpsOnUploadState } from '../../src/js/Utils/exifUploadSettings.ts';

const panel = readFileSync(new URL('../../src/js/Component/Settings/ExifSettings.tsx', import.meta.url), 'utf8');

test('the toggle uses the agreed text and key', () => {
    assert.equal(GPS_ON_UPLOAD.key, 'auto_remove_gps_on_upload');
    assert.equal(GPS_ON_UPLOAD.label, 'Remove GPS Location on Upload');
    assert.equal(GPS_ON_UPLOAD.description, 'Automatically remove GPS location metadata from supported images when they are uploaded.');
});

test('the EXIF panel renders the toggle outside the processing-only rows', () => {
    const row = panel.indexOf('label={GPS_ON_UPLOAD.rowLabel}');
    assert.ok(row > 0, 'The row is in the panel.');
    // Shown (disabled) while processing is off, not hidden with the other rules.
    assert.ok(row > panel.indexOf('</>'), 'Rendered after the {enabled && (...)} block.');
    assert.match(panel, /disabled=\{gps\.disabled\}/);
    assert.match(panel, /isPro=\{gps\.locked\}/);
    assert.match(panel, /checked=\{gps\.checked\}/);
});

test('licensed: reflects the saved value', () => {
    assert.deepEqual(gpsOnUploadState({ auto_process_exif: 1, auto_remove_gps_on_upload: 1 }, true), { checked: true, disabled: false, locked: false });
    assert.deepEqual(gpsOnUploadState({ auto_process_exif: 1, auto_remove_gps_on_upload: 0 }, true), { checked: false, disabled: false, locked: false });
    assert.equal(gpsOnUploadState({ auto_process_exif: 1 }, true).checked, false, 'Missing means off.');
    assert.equal(gpsOnUploadState({ auto_process_exif: '1', auto_remove_gps_on_upload: '1' }, true).checked, true);
    assert.equal(gpsOnUploadState({ auto_process_exif: 1, auto_remove_gps_on_upload: '0' }, true).checked, false, 'A string "0" is off.');
});

test('disabled while Process EXIF on Upload is off, keeping the saved value', () => {
    for (const isPro of [true, false]) {
        assert.equal(gpsOnUploadState({ auto_process_exif: 0, auto_remove_gps_on_upload: 1 }, isPro).disabled, true);
        assert.equal(gpsOnUploadState({ auto_remove_gps_on_upload: 1 }, isPro).disabled, true);
    }
    assert.equal(gpsOnUploadState({ auto_process_exif: 0, auto_remove_gps_on_upload: 1 }, true).checked, true);
});

test('unlicensed: locked and never shown as on', () => {
    assert.deepEqual(gpsOnUploadState({ auto_process_exif: 1, auto_remove_gps_on_upload: 1 }, false), { checked: false, disabled: false, locked: true });
    assert.deepEqual(gpsOnUploadState({ auto_process_exif: 1, auto_remove_gps_on_upload: 0 }, false), { checked: false, disabled: false, locked: true });
});

test('a locked or disabled toggle never changes the setting', () => {
    const handler = panel.slice(panel.indexOf('const toggleGps'), panel.indexOf('return (', panel.indexOf('const toggleGps')));
    assert.ok(handler.indexOf('gps.disabled') < handler.indexOf('toggle(GPS_ON_UPLOAD.key'));
    assert.ok(handler.indexOf('openProModal: true') < handler.indexOf('toggle(GPS_ON_UPLOAD.key'));
});
