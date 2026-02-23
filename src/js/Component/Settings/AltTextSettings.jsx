import React from 'react';

import { useStateValue } from '@/js/Utils/StateProvider';

import * as Types from "@/js/Utils/actionType";

import CheckboxField from "@/js/Component/Common/CheckboxField";
import Textarea from "@/js/Component/Common/Textarea";
import SettingRow from "@/js/Component/Common/SettingRow";

export default function AltTextSettings() {
    const [stateValue, dispatch] = useStateValue();

    const setDefaultText = (e) => {
        if (!tsmltParams.hasExtended && e.target.name === 'alt_text_by_post_title') {
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
            {/* Use Post Title as Alt Text */}
            <SettingRow label="Use Post Title as Alt Text:">
                <CheckboxField
                    name="alt_text_by_post_title"
                    value="alt_text_by_post_title"
                    checked={'alt_text_by_post_title' === stateValue.options.alt_text_by_post_title}
                    onChange={setDefaultText}
                    label="Default Alt Text Base On Post Title"
                    isPro={!tsmltParams.hasExtended}
                />
                <p className="text-sm text-gray-500">
                    Alt Text will add automatically when upload Media as attached posts.
                </p>
            </SettingRow>

            {/* Default Images Alt Text */}
            <SettingRow label="Default Images Alt Text:" bordered>
                <div className="flex flex-wrap gap-6">
                    <CheckboxField
                        name="default_alt_text"
                        value="image_name_to_alt"
                        checked={'image_name_to_alt' === stateValue.options.default_alt_text}
                        onChange={setDefaultText}
                        label="Image name use as alt text"
                    />

                    <CheckboxField
                        name="default_alt_text"
                        value="custom_text_to_alt"
                        checked={'custom_text_to_alt' === stateValue.options.default_alt_text}
                        onChange={setDefaultText}
                        label="Custom text"
                    />
                </div>

                {'custom_text_to_alt' === stateValue.options.default_alt_text && (
                    <div className="pt-4">
                        <Textarea
                            placeholder="Enter your custom alt text..."
                            onChange={(event) =>
                                dispatch({
                                    type: Types.UPDATE_OPTIONS,
                                    options: { ...stateValue.options, media_default_alt: event.target.value },
                                })
                            }
                            value={stateValue.options.media_default_alt}
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
