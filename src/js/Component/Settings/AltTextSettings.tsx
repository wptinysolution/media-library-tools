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
            <SettingRow label="Use Post Title as Alt Text:">
                <CheckboxField
                    name="alt_text_by_post_title"
                    value="alt_text_by_post_title"
                    checked={'alt_text_by_post_title' === options.alt_text_by_post_title}
                    onChange={setDefaultText}
                    label="Default Alt Text Base On Post Title"
                    isPro={!tsmltParams.hasExtended}
                />
                <p className="text-sm text-gray-500">
                    Alt Text will add automatically when upload Media as attached posts.
                </p>
            </SettingRow>

            <SettingRow label="Default Images Alt Text:" bordered>
                <div className="flex flex-wrap gap-6">
                    <CheckboxField
                        name="default_alt_text"
                        value="image_name_to_alt"
                        checked={'image_name_to_alt' === options.default_alt_text}
                        onChange={setDefaultText}
                        label="Image name use as alt text"
                    />
                    <CheckboxField
                        name="default_alt_text"
                        value="custom_text_to_alt"
                        checked={'custom_text_to_alt' === options.default_alt_text}
                        onChange={setDefaultText}
                        label="Custom text"
                    />
                </div>

                {'custom_text_to_alt' === options.default_alt_text && (
                    <div className="pt-4">
                        <Textarea
                            placeholder="Enter your custom alt text..."
                            onChange={(event) => setOptions({ media_default_alt: event.target.value })}
                            value={(options.media_default_alt as string) || ''}
                        />
                    </div>
                )}

                <p className="text-sm text-gray-500">
                    Alt Text Will add automatically when upload Media file
                </p>
            </SettingRow>
        </div>
    );
}
