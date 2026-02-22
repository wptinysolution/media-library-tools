import React from 'react';

import { useStateValue } from '@/js/Utils/StateProvider';

import * as Types from "@/js/Utils/actionType";

export default function RenamerSettings() {
    const [stateValue, dispatch] = useStateValue();

    const setDefaultText = (e) => {
        const proFields = ['enable_auto_rename', 'auto_rename_by_post_title'];
        if (!tsmltParams.hasExtended && proFields.includes(e.target.name)) {
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
        <div className="bg-white rounded-lg border border-gray-200">
            <div className="px-6 py-5 border-b border-gray-200">
                <h3 className="text-xl m-0! font-semibold text-gray-900">Media Renamer Settings</h3>
            </div>

            <div className="p-6 space-y-6">
                {/* Prefix */}
                <div className="flex items-start gap-8">
                    <label className="text-base font-medium text-gray-900 whitespace-nowrap pt-2 min-w-[200px]">
                        File Rename Prefix And Suffix:
                    </label>
                    <div className="flex-1 space-y-6">
                        <div className="space-y-2">
                            <div className="flex items-center gap-2">
                                <label className="text-base font-medium text-gray-900">Rename prefix</label>
                                {!tsmltParams.hasExtended && <span className="text-red-600 font-bold">- PRO</span>}
                            </div>
                            <input
                                type="text"
                                className="w-full max-w-md px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Prefix"
                                onChange={(event) =>
                                    dispatch({
                                        type: Types.UPDATE_OPTIONS,
                                        options: { ...stateValue.options, media_rename_prefix: event.target.value },
                                    })
                                }
                                value={stateValue.options.media_rename_prefix}
                            />
                            <p className="text-sm text-gray-500">
                                A file rename prefix is a set of characters, words, or numbers added at the beginning of a filename when renaming it. This helps in organizing files, improving SEO, or maintaining a consistent naming convention.
                            </p>
                        </div>

                        <div className="space-y-2 pt-4 border-t border-gray-200">
                            <div className="flex items-center gap-2">
                                <label className="text-base font-medium text-gray-900">Rename suffix</label>
                                {!tsmltParams.hasExtended && <span className="text-red-600 font-bold">- PRO</span>}
                            </div>
                            <input
                                type="text"
                                className="w-full max-w-md px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Suffix"
                                onChange={(event) =>
                                    dispatch({
                                        type: Types.UPDATE_OPTIONS,
                                        options: { ...stateValue.options, media_rename_suffix: event.target.value },
                                    })
                                }
                                value={stateValue.options.media_rename_suffix}
                            />
                            <p className="text-sm text-gray-500">
                                A file rename suffix is a set of characters, words, or numbers added at the end of a filename when renaming it. This helps differentiate files, improve SEO, or maintain a structured naming convention.
                            </p>
                        </div>
                    </div>
                </div>

                {/* Rename based on attached posts */}
                <div className="flex items-start gap-8 pt-6 border-t border-gray-200">
                    <label className="text-base font-medium text-gray-900 whitespace-nowrap pt-1 min-w-[200px]">
                        Rename based on attached posts:
                    </label>
                    <div className="flex-1 space-y-2">
                        <label className="inline-flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                onChange={setDefaultText}
                                name="auto_rename_by_post_title"
                                value="auto_rename_by_post_title"
                                checked={'auto_rename_by_post_title' === stateValue.options.auto_rename_by_post_title}
                            />
                            <span className="text-base text-gray-900">
                                Auto Rename by post title
                                {!tsmltParams.hasExtended && <span className="text-red-600 font-bold"> - PRO</span>}
                            </span>
                        </label>
                        <p className="text-sm text-gray-500">
                            When you edit a post and upload an image, it will be renamed automatically based on the post title.
                        </p>
                    </div>
                </div>

                {/* Others Media Auto Rename */}
                <div className="flex items-start gap-8 pt-6 border-t border-gray-200">
                    <label className="text-base font-medium text-gray-900 whitespace-nowrap pt-1 min-w-[200px]">
                        Others Media Auto Rename:
                    </label>
                    <div className="flex-1 space-y-2">
                        <label className="inline-flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                onChange={setDefaultText}
                                name="enable_auto_rename"
                                value="enable_auto_rename"
                                checked={'enable_auto_rename' === stateValue.options.enable_auto_rename}
                            />
                            <span className="text-base text-gray-900">
                                Custom text
                                {!tsmltParams.hasExtended && <span className="text-red-600 font-bold"> - PRO</span>}
                            </span>
                        </label>
                        <p className="text-sm text-gray-500">
                            Auto rename will apply automatically when upload Media file. File name will be unique by incremental number. Example: file-name.jpg next one file-name-1.jpg
                        </p>
                        {tsmltParams.hasExtended && 'enable_auto_rename' === stateValue.options.enable_auto_rename && (
                            <div className="pt-4 space-y-2">
                                <input
                                    type="text"
                                    className="w-full max-w-md px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="file name"
                                    onChange={(event) =>
                                        dispatch({
                                            type: Types.UPDATE_OPTIONS,
                                            options: { ...stateValue.options, media_auto_rename_text: event.target.value },
                                        })
                                    }
                                    value={stateValue.options.media_auto_rename_text}
                                />
                                <p className="text-sm text-red-600">
                                    Required Field. Write file name without extension. Remember !! Empty Value will not apply. <br /> Example: File Name
                                </p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
