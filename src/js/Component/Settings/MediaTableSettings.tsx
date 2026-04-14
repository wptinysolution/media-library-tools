import { useStore } from '@/js/Utils/store';
import CheckboxField from '@/js/Component/Common/CheckboxField';
import TextInput from '@/js/Component/Common/TextInput';
import SettingRow from '@/js/Component/Common/SettingRow';

export default function MediaTableSettings() {
    const { options, setOptions } = useStore();

    const onChangeOthersFileList = (value: string) => {
        const currentList = options.others_file_support || [];
        const newList = currentList.includes(value)
            ? currentList.filter(item => item !== value)
            : [...currentList, value];
        setOptions({ others_file_support: newList });
    };

    const setDefaultText = (e: React.ChangeEvent<HTMLInputElement>) => {
        setOptions({
            [e.target.name]: options[e.target.name] !== e.target.value ? e.target.value : '',
        });
    };

    return (
        <>
            <div className="p-6 space-y-6 border-t border-gray-200">
                <div className="flex items-start gap-8">
                    <label className="text-base font-medium text-gray-900 whitespace-nowrap pt-1 min-w-50">
                        Others File Support:
                    </label>
                    <div className="flex-1 space-y-2">
                        <label className="inline-flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                checked={(options.others_file_support || []).includes('svg')}
                                onChange={() => onChangeOthersFileList('svg')}
                            />
                            <span className="text-base text-gray-900">SVG</span>
                        </label>
                        <p className="text-sm mt-0! text-gray-500">Svg And Others File Upload.</p>
                    </div>
                </div>

                <SettingRow label="Auto Alt Text on Frontend:" bordered>
                    <CheckboxField
                        name="auto_inject_alt_text"
                        value="auto_inject_alt_text"
                        checked={'auto_inject_alt_text' === options.auto_inject_alt_text}
                        onChange={setDefaultText}
                        label="Enable auto alt text injection"
                        isPro={!tsmltParams.hasExtended}
                    />
                    <p className="text-sm text-gray-500 mt-0!">
                        Automatically add alt text to images missing alt attributes when pages are rendered on the frontend.
                    </p>
                </SettingRow>

                {tsmltParams.hasExtended && 'auto_inject_alt_text' === options.auto_inject_alt_text && (
                    <>
                        <SettingRow label="Use Post Title as Alt Text:" bordered>
                            <CheckboxField
                                name="use_post_title_alt_text"
                                value="use_post_title_alt_text"
                                checked={'use_post_title_alt_text' === options.use_post_title_alt_text}
                                onChange={setDefaultText}
                                label="Use post title for alt text"
                                isPro={false}
                            />
                            <p className="text-sm text-gray-500 mt-0!">
                                If enabled, will use the post/page title as alt text. Falls back to filename if no parent post.
                            </p>
                        </SettingRow>

                        <SettingRow label="Default Alt Text (Fallback):" bordered>
                            <TextInput
                                placeholder="e.g., 'Image' or 'Photo'"
                                onChange={(event) => setOptions({ default_alt_text_if_missing: event.target.value })}
                                value={(options.default_alt_text_if_missing as string) || ''}
                            />
                            <p className="text-sm text-gray-500 mt-0!">
                                Fallback alt text if no post title or filename is available. Leave empty to use filename as fallback.
                            </p>
                        </SettingRow>
                    </>
                )}

                <SettingRow label="Frontend Image Usage Tracking:" bordered>
                    <CheckboxField
                        name="track_frontend_usage"
                        value="track_frontend_usage"
                        checked={'track_frontend_usage' === options.track_frontend_usage}
                        onChange={setDefaultText}
                        label="Enable passive image usage tracking"
                        isPro={false}
                    />
                    <p className="text-sm text-gray-500 mt-0!">
                        Automatically collect image usage data when users visit pages. Complements the backend scan functionality.
                    </p>
                </SettingRow>
            </div>
        </>
    );
}
