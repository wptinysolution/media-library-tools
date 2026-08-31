import { useStore } from '@/js/Utils/store';
import CheckboxField from '@/js/Component/Common/CheckboxField';
import SettingRow from '@/js/Component/Common/SettingRow';

/**
 * Preset options mirrored from CompressionSettings::PRESETS on the server.
 * The server re-validates the chosen value, so this list is presentational.
 */
const compressionModes = [
    { value: 'high_quality', label: 'High Quality', description: 'Smallest visual change. Modest file size savings.' },
    { value: 'balanced', label: 'Balanced', description: 'Recommended. Good savings with no visible quality loss on most images.' },
    { value: 'maximum', label: 'Maximum Compression', description: 'Largest savings. Quality loss may be visible on detailed images.' },
];

/**
 * Compress Images settings panel.
 *
 * Values are held in the shared `options` store and saved by the existing
 * SaveButton, exactly like the other settings sections. Pro-only rows open the
 * upgrade modal rather than changing state; the backend enforces the same
 * restrictions regardless of what is submitted.
 */
export default function CompressionSettings() {
    const { options, setOptions, setGeneralData } = useStore();

    const isPro = tsmltParams.hasExtended;

    /** Toggle a Pro-only setting, or prompt to upgrade when not entitled. */
    const setProOption = (key: string, checked: boolean) => {
        if (!isPro) {
            setGeneralData({ openProModal: true });
            return;
        }
        setOptions({ [key]: checked ? 1 : 0 });
    };

    const quality = parseInt(String(options.compression_quality ?? 0), 10) || 82;

    return (
        <div className="bg-white rounded-lg border border-gray-200">
            <div className="px-6 py-5 border-b border-gray-200">
                <h3 className="text-xl m-0! font-semibold text-gray-900">Compress Images</h3>
            </div>

            <div className="p-6 space-y-6">
                <SettingRow label="Default Compression Level:">
                    <div className="space-y-2">
                        {compressionModes.map((item) => (
                            <label key={item.value} className="flex items-start gap-2 cursor-pointer">
                                <input
                                    type="radio"
                                    name="compression_mode"
                                    className="mt-1 text-blue-600 focus:ring-blue-500"
                                    checked={(options.compression_mode || 'balanced') === item.value}
                                    onChange={() => setOptions({ compression_mode: item.value })}
                                />
                                <span>
                                    <span className="text-sm font-medium text-gray-900">{item.label}</span>
                                    <span className="block text-xs text-gray-500">{item.description}</span>
                                </span>
                            </label>
                        ))}
                    </div>
                    <p className="text-sm text-gray-500 mt-0!">
                        Used as the default when you open the Compress Images window. You can still change
                        the level for any individual run.
                    </p>
                </SettingRow>

                <SettingRow label="Custom Compression Quality:" bordered>
                    <CheckboxField
                        name="compression_use_custom_quality"
                        value="compression_use_custom_quality"
                        checked={isPro && !!options.compression_use_custom_quality}
                        onChange={(e) => setProOption('compression_use_custom_quality', e.target.checked)}
                        label="Use an exact quality value instead of a preset"
                        isPro={!isPro}
                    />

                    {isPro && !!options.compression_use_custom_quality && (
                        <div className="flex items-center gap-3 mt-2">
                            <input
                                type="range"
                                min={1}
                                max={100}
                                value={quality}
                                onChange={(e) => setOptions({ compression_quality: parseInt(e.target.value, 10) })}
                                className="flex-1 max-w-xs"
                            />
                            <span className="text-sm font-semibold text-gray-900 w-10">{quality}</span>
                        </div>
                    )}

                    <p className="text-sm text-gray-500 mt-0!">
                        Lower values produce smaller files with more visible quality loss. Around 80 is a
                        good balance for photographs.
                    </p>
                </SettingRow>

                <SettingRow label="Backup Original Images:" bordered>
                    <CheckboxField
                        name="compression_backup_originals"
                        value="compression_backup_originals"
                        checked={isPro && !!options.compression_backup_originals}
                        onChange={(e) => setProOption('compression_backup_originals', e.target.checked)}
                        label="Keep a copy of each original before compressing"
                        isPro={!isPro}
                    />
                    <p className="text-sm text-gray-500 mt-0!">
                        Originals are stored inside your uploads folder so any compressed image can be
                        restored later. Backups use extra disk space and are never deleted automatically.
                    </p>
                </SettingRow>

                <SettingRow label="Compress Generated Image Sizes:" bordered>
                    <CheckboxField
                        name="compression_generated_sizes"
                        value="compression_generated_sizes"
                        checked={isPro && !!options.compression_generated_sizes}
                        onChange={(e) => setProOption('compression_generated_sizes', e.target.checked)}
                        label="Also compress thumbnails and other generated sizes"
                        isPro={!isPro}
                    />
                    <p className="text-sm text-gray-500 mt-0!">
                        WordPress creates several resized copies of every image. Compressing them too
                        saves considerably more space, but each image takes longer to process.
                    </p>
                </SettingRow>

                <SettingRow label="Automatic Compression on Upload:" bordered>
                    <CheckboxField
                        name="compression_auto_on_upload"
                        value="compression_auto_on_upload"
                        checked={isPro && !!options.compression_auto_on_upload}
                        onChange={(e) => setProOption('compression_auto_on_upload', e.target.checked)}
                        label="Compress new images automatically when they are uploaded"
                        isPro={!isPro}
                    />
                    <p className="text-sm text-gray-500 mt-0!">
                        Runs after WordPress finishes generating image sizes, using the settings above.
                        Existing images are not affected &mdash; use the Media Table to compress those.
                    </p>
                </SettingRow>
            </div>
        </div>
    );
}
