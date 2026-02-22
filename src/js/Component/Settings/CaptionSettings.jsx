import React from 'react';

import { useStateValue } from '@/js/Utils/StateProvider';

import * as Types from "@/js/Utils/actionType";

export default function CaptionSettings() {
    const [stateValue, dispatch] = useStateValue();

    const setDefaultText = (e) => {
        if (!tsmltParams.hasExtended && e.target.name === 'caption_text_by_post_title') {
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
            {/* Use Post Title as Caption */}
            <div className="flex items-start gap-8">
                <label className="text-base font-medium text-gray-900 whitespace-nowrap pt-1 min-w-[200px]">
                    Use Post Title as Caption:
                </label>
                <div className="flex-1 space-y-2">
                    <label className="inline-flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                            onChange={setDefaultText}
                            name="caption_text_by_post_title"
                            value="caption_text_by_post_title"
                            checked={'caption_text_by_post_title' === stateValue.options.caption_text_by_post_title}
                        />
                        <span className="text-base text-gray-900">
                            Default Caption Text Base On Post Title
                            {!tsmltParams.hasExtended && <span className="text-red-600 font-bold"> - PRO</span>}
                        </span>
                    </label>
                    <p className="text-sm text-gray-500">
                        Caption Text will add automatically when upload Media as attached posts.
                    </p>
                </div>
            </div>

            {/* Default Caption Text */}
            <div className="flex items-start gap-8 pt-6 border-t border-gray-200">
                <label className="text-base font-medium text-gray-900 whitespace-nowrap pt-1 min-w-[200px]">
                    Default Caption Text:
                </label>
                <div className="flex-1 space-y-2">
                    <div className="flex flex-wrap gap-6">
                        <label className="inline-flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                onChange={setDefaultText}
                                name="default_caption_text"
                                value="image_name_to_caption"
                                checked={'image_name_to_caption' === stateValue.options.default_caption_text}
                            />
                            <span className="text-base text-gray-900">Image name use as caption</span>
                        </label>

                        <label className="inline-flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                onChange={setDefaultText}
                                name="default_caption_text"
                                value="custom_text_to_caption"
                                checked={'custom_text_to_caption' === stateValue.options.default_caption_text}
                            />
                            <span className="text-base text-gray-900">Custom text</span>
                        </label>
                    </div>

                    {'custom_text_to_caption' === stateValue.options.default_caption_text && (
                        <div className="pt-4">
                            <textarea
                                className="w-full max-w-2xl px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
                                rows="3"
                                placeholder="Enter your custom caption..."
                                onChange={(event) =>
                                    dispatch({
                                        type: Types.UPDATE_OPTIONS,
                                        options: { ...stateValue.options, media_default_caption: event.target.value },
                                    })
                                }
                                value={stateValue.options.media_default_caption}
                            />
                        </div>
                    )}

                    <p className="text-sm text-gray-500">
                        Caption text will add automatically when upload Media file
                    </p>
                </div>
            </div>
        </div>
    );
}
