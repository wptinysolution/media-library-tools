import { useStore } from '@/js/Utils/store';
import CheckboxField from '@/js/Component/Common/CheckboxField';
import SettingRow from '@/js/Component/Common/SettingRow';

/**
 * EXIF on Upload settings panel.
 *
 * Every option is off by default and applies only to new uploads. Values are
 * held in the shared `options` store and saved by the existing SaveButton;
 * the server stores them as 0/1.
 */
export default function ExifSettings() {
    const { options, setOptions } = useStore();

    const enabled = !!options.auto_process_exif;

    const toggle = (key: string, checked: boolean) => setOptions({ [key]: checked ? 1 : 0 });

    return (
        <div className="bg-white rounded-lg border border-gray-200">
            <div className="px-6 py-5 border-b border-gray-200">
                <h3 className="text-xl m-0! font-semibold text-gray-900">EXIF on Upload</h3>
            </div>

            <div className="p-6 space-y-6">
                <SettingRow label="Process EXIF on Upload:">
                    <CheckboxField
                        name="auto_process_exif"
                        value="auto_process_exif"
                        checked={enabled}
                        onChange={(e) => toggle('auto_process_exif', e.target.checked)}
                        label="Read EXIF data from new JPEG, TIFF and WebP uploads"
                    />
                    <p className="text-sm text-gray-500 mt-0!">
                        Stores each upload's camera and GPS location as attachment data, and runs the options
                        below. None of these options change the image file. Requires the PHP EXIF extension.
                        Existing images are not affected.
                    </p>
                </SettingRow>

                {enabled && (
                    <>
                        <SettingRow label="Fill Missing Details from EXIF:" bordered>
                            <CheckboxField
                                name="auto_fill_metadata_from_exif"
                                value="auto_fill_metadata_from_exif"
                                checked={!!options.auto_fill_metadata_from_exif}
                                onChange={(e) => toggle('auto_fill_metadata_from_exif', e.target.checked)}
                                label="Fill empty title, alt text and caption"
                            />
                            <p className="text-sm text-gray-500 mt-0!">
                                Only empty fields are filled: the title from the camera make and model, the alt
                                text from the file name, and the caption from the EXIF image description.
                            </p>
                        </SettingRow>

                        <SettingRow label="Use Date Taken:" bordered>
                            <CheckboxField
                                name="auto_set_date_from_exif"
                                value="auto_set_date_from_exif"
                                checked={!!options.auto_set_date_from_exif}
                                onChange={(e) => toggle('auto_set_date_from_exif', e.target.checked)}
                                label="Set the media upload date to the date the photo was taken"
                            />
                            <p className="text-sm text-gray-500 mt-0!">
                                Changes the date shown in the Media Library. The file stays in its current
                                upload folder.
                            </p>
                        </SettingRow>
                    </>
                )}
            </div>
        </div>
    );
}
