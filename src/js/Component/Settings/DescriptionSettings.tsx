import React from 'react';
import { useStore } from '@/js/Utils/store';
import CheckboxField from '@/js/Component/Common/CheckboxField';
import Textarea from '@/js/Component/Common/Textarea';
import SettingRow from '@/js/Component/Common/SettingRow';

export default function DescriptionSettings() {
    const { options, setOptions, setGeneralData } = useStore();

    const setDefaultText = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (!tsmltParams.hasExtended && e.target.name === 'desc_text_by_post_title') {
            setGeneralData({ openProModal: true });
            return;
        }
        setOptions({
            [e.target.name]: options[e.target.name] !== e.target.value ? e.target.value : '',
        });
    };

    return (
        <div className="p-6 space-y-6 border-t border-gray-200">
            <SettingRow label="Use Post Title as Description:">
                <CheckboxField
                    name="desc_text_by_post_title"
                    value="desc_text_by_post_title"
                    checked={'desc_text_by_post_title' === options.desc_text_by_post_title}
                    onChange={setDefaultText}
                    label="Default Description Text Base On Post Title"
                    isPro={!tsmltParams.hasExtended}
                />
                <p className="text-sm text-gray-500 mt-0!">
                    Description Text will add automatically when upload Media as attached posts.
                </p>
            </SettingRow>

            <SettingRow label="Default Description Text:" bordered>
                <div className="flex flex-wrap gap-6">
                    <CheckboxField
                        name="default_desc_text"
                        value="image_name_to_desc"
                        checked={'image_name_to_desc' === options.default_desc_text}
                        onChange={setDefaultText}
                        label="Image name use as description"
                    />
                    <CheckboxField
                        name="default_desc_text"
                        value="custom_text_to_desc"
                        checked={'custom_text_to_desc' === options.default_desc_text}
                        onChange={setDefaultText}
                        label="Custom text"
                    />
                </div>

                {'custom_text_to_desc' === options.default_desc_text && (
                    <div className="pt-4">
                        <Textarea
                            placeholder="Enter your custom description..."
                            onChange={(event) => setOptions({ media_default_desc: event.target.value })}
                            value={(options.media_default_desc as string) || ''}
                        />
                    </div>
                )}

                <p className="text-sm text-gray-500 mt-0!">
                    Description text will add automatically when upload Media file
                </p>
            </SettingRow>
        </div>
    );
}
