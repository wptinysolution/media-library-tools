import React from 'react';

import { useStateValue } from '@/js/Utils/StateProvider';

import * as Types from "@/js/Utils/actionType";

import CheckboxField from "@/js/Component/Common/CheckboxField";
import Textarea from "@/js/Component/Common/Textarea";
import SettingRow from "@/js/Component/Common/SettingRow";

export default function DescriptionSettings() {
    const [stateValue, dispatch] = useStateValue();

    const setDefaultText = (e) => {
        if (!tsmltParams.hasExtended && e.target.name === 'desc_text_by_post_title') {
            dispatch({
                type: Types.GENERAL_DATA,
                generalData: { ...stateValue.generalData, openProModal: true },
            });
            return;
        }
        dispatch({
            type: Types.UPDATE_OPTIONS,
            options: {
                ...stateValue.options,
                [e.target.name]: stateValue.options[e.target.name] !== e.target.value ? e.target.value : '',
            },
        });
    };

    return (
        <div className="p-6 space-y-6 border-t border-gray-200">
            {/* Use Post Title as Description */}
            <SettingRow label="Use Post Title as Description:">
                <CheckboxField
                    name="desc_text_by_post_title"
                    value="desc_text_by_post_title"
                    checked={'desc_text_by_post_title' === stateValue.options.desc_text_by_post_title}
                    onChange={setDefaultText}
                    label="Default Description Text Base On Post Title"
                    isPro={!tsmltParams.hasExtended}
                />
                <p className="text-sm text-gray-500">
                    Description Text will add automatically when upload Media as attached posts.
                </p>
            </SettingRow>

            {/* Default Description Text */}
            <SettingRow label="Default Description Text:" bordered>
                <div className="flex flex-wrap gap-6">
                    <CheckboxField
                        name="default_desc_text"
                        value="image_name_to_desc"
                        checked={'image_name_to_desc' === stateValue.options.default_desc_text}
                        onChange={setDefaultText}
                        label="Image name use as description"
                    />

                    <CheckboxField
                        name="default_desc_text"
                        value="custom_text_to_desc"
                        checked={'custom_text_to_desc' === stateValue.options.default_desc_text}
                        onChange={setDefaultText}
                        label="Custom text"
                    />
                </div>

                {'custom_text_to_desc' === stateValue.options.default_desc_text && (
                    <div className="pt-4">
                        <Textarea
                            placeholder="Enter your custom description..."
                            onChange={(event) =>
                                dispatch({
                                    type: Types.UPDATE_OPTIONS,
                                    options: { ...stateValue.options, media_default_desc: event.target.value },
                                })
                            }
                            value={stateValue.options.media_default_desc}
                        />
                    </div>
                )}

                <p className="text-sm text-gray-500">
                    Description text will add automatically when upload Media file
                </p>
            </SettingRow>
        </div>
    );
}
