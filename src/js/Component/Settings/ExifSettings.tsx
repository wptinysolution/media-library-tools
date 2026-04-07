import React from 'react';
import { useStore } from '@/js/Utils/store';
import CheckboxField from '@/js/Component/Common/CheckboxField';
import SettingRow from '@/js/Component/Common/SettingRow';

export default function ExifSettings() {
    const { options, setOptions } = useStore();

    const handlePreferField = (e: React.ChangeEvent<HTMLInputElement>) => {
        setOptions({ exif_prefer_field: e.target.value });
    };

    const handleAutoRead = (e: React.ChangeEvent<HTMLInputElement>) => {
        setOptions({
            exif_auto_read_on_upload: options.exif_auto_read_on_upload === 'exif_auto_read_on_upload' ? '' : 'exif_auto_read_on_upload',
        });
    };

    const handleDisplayFormat = (e: React.ChangeEvent<HTMLSelectElement>) => {
        setOptions({ exif_date_display_format: e.target.value });
    };

    const preferField = (options.exif_prefer_field as string) || 'DateTimeOriginal';
    const displayFormat = (options.exif_date_display_format as string) || 'Y-m-d H:i';

    return (
        <div className="p-6 space-y-6 border-t border-gray-200">
            <SettingRow label="Preferred Date Field:">
                <div className="flex flex-wrap gap-6">
                    <CheckboxField
                        name="exif_prefer_field"
                        value="DateTimeOriginal"
                        checked={preferField === 'DateTimeOriginal'}
                        onChange={handlePreferField}
                        label="DateTimeOriginal (when photo was taken — applies to JPEG/TIFF)"
                    />
                    <CheckboxField
                        name="exif_prefer_field"
                        value="DateTimeDigitized"
                        checked={preferField === 'DateTimeDigitized'}
                        onChange={handlePreferField}
                        label="DateTimeDigitized (when photo was scanned/digitized — applies to JPEG/TIFF)"
                    />
                </div>
                <p className="text-sm text-gray-500 mt-0!">
                    Which EXIF field to prefer for JPEG/TIFF images. For all other file types (video, audio, PDF, etc.) the file system modification date is used automatically.
                </p>
            </SettingRow>

            <SettingRow label="Auto-Read on Upload:" bordered>
                <CheckboxField
                    name="exif_auto_read_on_upload"
                    value="exif_auto_read_on_upload"
                    checked={'exif_auto_read_on_upload' === options.exif_auto_read_on_upload}
                    onChange={handleAutoRead}
                    label="Automatically read and store date metadata when any file is uploaded"
                />
                <p className="text-sm text-gray-500 mt-0!">
                    Works for all file types. Data is stored in post meta only — no dates are synced automatically on upload.
                </p>
            </SettingRow>

            <SettingRow label="Date Display Format:" bordered>
                <select
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                    value={displayFormat}
                    onChange={handleDisplayFormat}
                >
                    <option value="Y-m-d H:i">2025-07-15 10:30 (Y-m-d H:i)</option>
                    <option value="d/m/Y H:i">15/07/2025 10:30 (d/m/Y H:i)</option>
                    <option value="m/d/Y H:i">07/15/2025 10:30 (m/d/Y H:i)</option>
                    <option value="F j, Y">July 15, 2025 (F j, Y)</option>
                    <option value="Y-m-d">2025-07-15 (Y-m-d)</option>
                </select>
                <p className="text-sm text-gray-500 mt-0!">
                    How EXIF dates are displayed in the attachment edit screen and EXIF Dates panel.
                </p>
            </SettingRow>
        </div>
    );
}
