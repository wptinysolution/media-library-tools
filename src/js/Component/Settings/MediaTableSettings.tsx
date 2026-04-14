import { useStore } from '@/js/Utils/store';
import CheckboxField from '@/js/Component/Common/CheckboxField';
import TextInput from '@/js/Component/Common/TextInput';
import SettingRow from '@/js/Component/Common/SettingRow';

export default function MediaTableSettings() {
    const { options, setOptions, setGeneralData } = useStore();

    const onChangeOthersFileList = (value: string) => {
        const currentList = options.others_file_support || [];
        const newList = currentList.includes(value)
            ? currentList.filter(item => item !== value)
            : [...currentList, value];
        setOptions({ others_file_support: newList });
    };

    const setDefaultText = (e: React.ChangeEvent<HTMLInputElement>) => {
        const proFields = ['auto_inject_alt_text', 'use_post_title_alt_text'];
        if (!tsmltParams.hasExtended && proFields.includes(e.target.name)) {
            setGeneralData({ openProModal: true });
            return;
        }
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

                {tsmltParams.hasExtended && (
                    <SettingRow label="Auto Alt Text on Frontend:" bordered>
                        <CheckboxField
                            name="auto_inject_alt_text"
                            value="auto_inject_alt_text"
                            checked={'auto_inject_alt_text' === options.auto_inject_alt_text}
                            onChange={setDefaultText}
                            label="Enable auto alt text injection"
                            isPro={false}
                        />
                        <p className="text-sm text-gray-500 mt-0!">
                            <strong>What it does:</strong> Automatically fills in missing alt text on your website's frontend (visitor-facing pages) when images don't already have alt attributes. This only affects how images are displayed to visitors, not the image metadata in your media library.
                        </p>
                        <p className="text-sm text-gray-500 mt-2!">
                            <strong>Why use it:</strong> Helps with SEO and accessibility by ensuring every image has alt text, even if you forgot to add it when uploading.
                        </p>
                    </SettingRow>
                )}

                {tsmltParams.hasExtended && 'auto_inject_alt_text' === options.auto_inject_alt_text && (
                    <div className="space-y-6">
                        <SettingRow label="Alt Text Source (Priority Order):" bordered>
                            <p className="text-sm text-gray-700 mb-4!">
                                When auto-injecting alt text, the plugin tries these in order. The first one that produces a result wins:
                            </p>

                            <CheckboxField
                                name="use_post_title_alt_text"
                                value="use_post_title_alt_text"
                                checked={'use_post_title_alt_text' === options.use_post_title_alt_text}
                                onChange={setDefaultText}
                                label="1. Use Post/Page Title"
                                isPro={false}
                            />
                            <p className="text-sm text-gray-500 mt-0! ml-6">
                                If enabled, uses the title of the parent post or page. For example, if the post is titled "How to Bake Cookies", images in that post get alt text "How to Bake Cookies".
                            </p>
                        </SettingRow>

                        <SettingRow label="2. Custom Default Text:" bordered>
                            <TextInput
                                placeholder="e.g., 'Image' or 'Photo'"
                                onChange={(event) => setOptions({ default_alt_text_if_missing: event.target.value })}
                                value={(options.default_alt_text_if_missing as string) || ''}
                            />
                            <p className="text-sm text-gray-500 mt-0!">
                                If post title is unavailable or disabled, this custom text will be used. Leave empty to skip this step and fall back to the filename.
                            </p>
                        </SettingRow>

                        <div className="bg-blue-50 border border-blue-200 rounded p-4 mt-4">
                            <p className="text-sm text-blue-900 m-0!">
                                <strong>How it works:</strong> When a visitor views your website, if an image has no alt text, the plugin adds one using the rules above. This does not modify your media library — it only affects what visitors see on the frontend.
                            </p>
                        </div>
                    </div>
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
                        <strong>What it does:</strong> Automatically records image usage data whenever visitors view your pages. Works alongside the "Used Where" scanner to show you which images are being displayed on your frontend.
                    </p>
                    <p className="text-sm text-gray-500 mt-2!">
                        <strong>Why use it:</strong> Helps you identify images that look like they're used but aren't actually being displayed, or images that might have broken references. The backend scan shows uploaded images; this shows which ones are actually being viewed by visitors.
                    </p>
                    <p className="text-sm text-gray-500 mt-2!">
                        <strong>Performance:</strong> Minimal impact - only tracks data for images that are loaded when pages are viewed.
                    </p>
                </SettingRow>
            </div>
        </>
    );
}
