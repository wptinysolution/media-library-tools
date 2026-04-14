import React from 'react';
import { useStore } from '@/js/Utils/store';
import CheckboxField from '@/js/Component/Common/CheckboxField';
import Textarea from '@/js/Component/Common/Textarea';
import SettingRow from '@/js/Component/Common/SettingRow';

export default function AltTextSettings() {
    const { options, setOptions, setGeneralData } = useStore();

    const setDefaultText = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (!tsmltParams.hasExtended && e.target.name === 'alt_text_by_post_title') {
            setGeneralData({ openProModal: true });
            return;
        }
        setOptions({
            [e.target.name]: options[e.target.name] !== e.target.value ? e.target.value : '',
        });
    };

    return (
        <div className="p-6 space-y-6 border-t border-gray-200">
            <div className="bg-amber-50 border border-amber-200 rounded p-4 mb-4">
                <p className="text-sm text-amber-900 m-0!">
                    <strong>⚙️ Upload Settings:</strong> These settings control what happens when you upload new images to your media library. See the "Auto Alt Text on Frontend" section above for options that affect how alt text appears to website visitors.
                </p>
            </div>

            <SettingRow label="Auto Alt Text When Uploading:">
                <CheckboxField
                    name="alt_text_by_post_title"
                    value="alt_text_by_post_title"
                    checked={'alt_text_by_post_title' === options.alt_text_by_post_title}
                    onChange={setDefaultText}
                    label="Use post title as default alt text"
                    isPro={!tsmltParams.hasExtended}
                />
                <p className="text-sm text-gray-500 mt-0!">
                    When uploading a new image to a post or page, automatically fill the alt text field with the post/page title. This only happens during upload, not on the frontend.
                </p>
            </SettingRow>

            <SettingRow label="Fallback Alt Text for Uploads:" bordered>
                <div className="flex flex-wrap gap-6">
                    <CheckboxField
                        name="default_alt_text"
                        value="image_name_to_alt"
                        checked={'image_name_to_alt' === options.default_alt_text}
                        onChange={setDefaultText}
                        label="Use image filename"
                    />
                    <CheckboxField
                        name="default_alt_text"
                        value="custom_text_to_alt"
                        checked={'custom_text_to_alt' === options.default_alt_text}
                        onChange={setDefaultText}
                        label="Use custom text"
                    />
                </div>

                {'custom_text_to_alt' === options.default_alt_text && (
                    <div className="pt-4">
                        <Textarea
                            placeholder="Enter your custom alt text..."
                            onChange={(event) => setOptions({ media_default_alt: event.target.value as string })}
                            value={(options.media_default_alt) || ''}
                        />
                    </div>
                )}

                <p className="text-sm text-gray-500 mt-0!">
                    If a post title is not available, use the image filename or custom text as a fallback when uploading. This only affects new uploads.
                </p>
            </SettingRow>
        </div>
    );
}
