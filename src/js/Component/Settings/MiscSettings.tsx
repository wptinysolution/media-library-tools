import { useStore } from '@/js/Utils/store';
import CheckboxField from '@/js/Component/Common/CheckboxField';
import SettingRow from '@/js/Component/Common/SettingRow';

export default function MiscSettings() {
    const { options, setOptions } = useStore();

    return (
        <div className="bg-white rounded-lg border border-gray-200">
            <div className="px-6 py-5 border-b border-gray-200">
                <h3 className="text-xl m-0! font-semibold text-gray-900">Misc Settings</h3>
            </div>

            <div className="p-6 space-y-6">
                <SettingRow label="Delete Plugin Data on Uninstall:" bordered>
                    <CheckboxField
                        name="delete_data_on_uninstall"
                        value="delete_data_on_uninstall"
                        checked={!!options.delete_data_on_uninstall}
                        onChange={(e) => setOptions({ delete_data_on_uninstall: e.target.checked ? 1 : 0 })}
                        label="Permanently delete all plugin data when uninstalling"
                        isPro={false}
                    />
                    <div className="bg-amber-50 border border-amber-200 rounded p-3 mt-3">
                        <p className="text-sm text-amber-900 m-0!">
                            <strong>Warning:</strong> When enabled, deleting this plugin will permanently remove all data it created, including:
                        </p>
                        <ul className="text-sm text-amber-900 mt-2! mb-0! ml-5 list-disc">
                            <li>Database tables (rubbish files, duplicate files)</li>
                            <li>Plugin options and settings</li>
                            <li>Image usage tracking data</li>
                            <li>EXIF metadata cached on attachments</li>
                            <li>Scheduled cron jobs and transients</li>
                        </ul>
                        <p className="text-sm text-amber-900 mt-2! m-0!">
                            <strong>This does not delete your media files.</strong> Leave off to keep data in case you reinstall the plugin later.
                        </p>
                    </div>
                </SettingRow>
            </div>
        </div>
    );
}
