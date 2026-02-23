import React from 'react';

import { useStore } from '@/js/Utils/store';

import CheckboxField from "@/js/Component/Common/CheckboxField";
import Textarea from "@/js/Component/Common/Textarea";
import SettingRow from "@/js/Component/Common/SettingRow";

export default function CaptionSettings() {
    const { options, setOptions, setGeneralData } = useStore();

    const setDefaultText = (e) => {
        if (!tsmltParams.hasExtended && e.target.name === 'caption_text_by_post_title') {
            setGeneralData({ openProModal: true });
            return;
        }
        setOptions({
            [e.target.name]: options[e.target.name] !== e.target.value ? e.target.value : '',
        });
    };

    return (
        <div className="p-6 space-y-6 border-t border-gray-200">
            {/* Use Post Title as Caption */}
            <SettingRow label="Use Post Title as Caption:">
                <CheckboxField
                    name="caption_text_by_post_title"
                    value="caption_text_by_post_title"
                    checked={'caption_text_by_post_title' === options.caption_text_by_post_title}
                    onChange={setDefaultText}
                    label="Default Caption Text Base On Post Title"
                    isPro={!tsmltParams.hasExtended}
                />
                <p className="text-sm text-gray-500">
                    Caption Text will add automatically when upload Media as attached posts.
                </p>
            </SettingRow>

            {/* Default Caption Text */}
            <SettingRow label="Default Caption Text:" bordered>
                <div className="flex flex-wrap gap-6">
                    <CheckboxField
                        name="default_caption_text"
                        value="image_name_to_caption"
                        checked={'image_name_to_caption' === options.default_caption_text}
                        onChange={setDefaultText}
                        label="Image name use as caption"
                    />

                    <CheckboxField
                        name="default_caption_text"
                        value="custom_text_to_caption"
                        checked={'custom_text_to_caption' === options.default_caption_text}
                        onChange={setDefaultText}
                        label="Custom text"
                    />
                </div>

                {'custom_text_to_caption' === options.default_caption_text && (
                    <div className="pt-4">
                        <Textarea
                            placeholder="Enter your custom caption..."
                            onChange={(event) => setOptions({ media_default_caption: event.target.value })}
                            value={options.media_default_caption}
                        />
                    </div>
                )}

                <p className="text-sm text-gray-500">
                    Caption text will add automatically when upload Media file
                </p>
            </SettingRow>
        </div>
    );
}
