import React from 'react';
import { useStateValue } from '@/js/Utils/StateProvider';
import Loader from '@/js/Utils/Loader';
import { columnList } from '@/js/Utils/UtilData';
import * as Types from "@/js/Utils/actionType";
import MainHeader from "@/js/Component/MainHeader";

function Settings() {
    const [stateValue, dispatch] = useStateValue();

    const plainOptions = columnList.map((currentValue) => currentValue.key);
    const isCheckedDiff = Object.keys(plainOptions).length === Object.keys(stateValue.options.media_table_column).length;

    const onChangeColumnList = (key) => {
        const currentColumn = stateValue.options.media_table_column;
        const newColumn = currentColumn.includes(key)
            ? currentColumn.filter(item => item !== key)
            : [...currentColumn, key];

        dispatch({
            type: Types.UPDATE_OPTIONS,
            options: {
                ...stateValue.options,
                media_table_column: newColumn,
            }
        });
    };

    const onCheckAllColumn = (e) => {
        dispatch({
            type: Types.UPDATE_OPTIONS,
            options: {
                ...stateValue.options,
                media_table_column: e.target.checked ? plainOptions : [],
            }
        });
    };

    const setDefaultText = (e) => {
        if (!tsmltParams.hasExtended) {
            const fields = [
                'enable_auto_rename',
                'alt_text_by_post_title',
                'auto_rename_by_post_title',
                'caption_text_by_post_title',
                'desc_text_by_post_title',
            ];
            if (fields.indexOf(e.target.name) !== -1) {
                dispatch({
                    type: Types.GENERAL_DATA,
                    generalData: {
                        ...stateValue.generalData,
                        openProModal: true,
                    },
                });
                return;
            }
        }
        dispatch({
            type: Types.UPDATE_OPTIONS,
            options: {
                ...stateValue.options,
                [e.target.name]: stateValue.options[e.target.name] !== e.target.value ? e.target.value : '',
            }
        });
    };

    const onChangeOthersFileList = (value) => {
        const currentList = stateValue.options.others_file_support || [];
        const newList = currentList.includes(value)
            ? currentList.filter(item => item !== value)
            : [...currentList, value];

        dispatch({
            type: Types.UPDATE_OPTIONS,
            options: {
                ...stateValue.options,
                others_file_support: newList,
            }
        });
    };

    return (
        <>
            <MainHeader />
            <div className="overflow-y-auto">
                <div className="relative">
                    {stateValue.options.isLoading ? (
                        <Loader />
                    ) : (
                        <div className="p-6 bg-white/35 rounded-lg shadow-[0_0_20px_rgba(0,0,0,0.01)]">
                            {/* Media Table Settings */}
                            <h3 className="text-2xl font-semibold m-0">Media Table Settings</h3>
                            <hr className="my-4 border-gray-200" />

                            <div className="mb-6">
                                <label className="block text-sm font-medium mb-2">Media Table Column</label>
                                <div className="space-y-3">
                                    <label className="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            indeterminate={!isCheckedDiff}
                                            onChange={onCheckAllColumn}
                                            checked={isCheckedDiff}
                                        />
                                        <span className="text-sm">Check all</span>
                                    </label>
                                    <hr className="my-2.5 border-gray-200" />
                                    <div className="grid grid-cols-2 gap-3">
                                        {columnList.map((column) => (
                                            <label key={column.key} className="flex items-center gap-2 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                    checked={stateValue.options.media_table_column.includes(column.key)}
                                                    onChange={() => onChangeColumnList(column.key)}
                                                />
                                                <span className="text-sm">{column.title}</span>
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            </div>

                            <hr className="my-4 border-gray-200" />

                            {/* Others File Support */}
                            <div className="mb-6">
                                <label className="block text-sm font-medium mb-2">Others File Support</label>
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        checked={(stateValue.options.others_file_support || []).includes('svg')}
                                        onChange={() => onChangeOthersFileList('svg')}
                                    />
                                    <span className="text-sm">SVG</span>
                                </label>
                                <p className="text-sm text-gray-500 mt-2">Svg And Others File Upload.</p>
                            </div>

                            <hr className="my-4 border-gray-200" />

                            {/* Use Post Title as Alt Text */}
                            <div className="mb-6">
                                <label className="block text-sm font-medium mb-2">Use Post Title as Alt Text</label>
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        onChange={setDefaultText}
                                        name="alt_text_by_post_title"
                                        value="alt_text_by_post_title"
                                        checked={'alt_text_by_post_title' === stateValue.options.alt_text_by_post_title}
                                    />
                                    <span className="text-sm">
                                        Default Alt Text Base On Post Title
                                        {!tsmltParams.hasExtended && <span className="text-red-600 font-bold"> - PRO</span>}
                                    </span>
                                </label>
                                <p className="text-sm text-gray-500 mt-2">
                                    Alt Text will add automatically when upload Media as attached posts.
                                </p>
                            </div>

                            <hr className="my-4 border-gray-200" />

                            {/* Default Images Alt Text */}
                            <div className="mb-6">
                                <label className="block text-sm font-medium mb-2">Default Images Alt Text</label>
                                <div className="space-y-2">
                                    <label className="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            onChange={setDefaultText}
                                            name="default_alt_text"
                                            value="image_name_to_alt"
                                            checked={'image_name_to_alt' === stateValue.options.default_alt_text}
                                        />
                                        <span className="text-sm">Image name use as alt text</span>
                                    </label>
                                    <label className="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            onChange={setDefaultText}
                                            name="default_alt_text"
                                            value="custom_text_to_alt"
                                            checked={'custom_text_to_alt' === stateValue.options.default_alt_text}
                                        />
                                        <span className="text-sm">Custom text</span>
                                    </label>
                                    {'custom_text_to_alt' === stateValue.options.default_alt_text && (
                                        <>
                                            <hr className="my-2.5 border-gray-200" />
                                            <textarea
                                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                rows="4"
                                                onChange={(event) =>
                                                    dispatch({
                                                        type: Types.UPDATE_OPTIONS,
                                                        options: {
                                                            ...stateValue.options,
                                                            media_default_alt: event.target.value,
                                                        },
                                                    })
                                                }
                                                value={stateValue.options.media_default_alt}
                                            />
                                        </>
                                    )}
                                </div>
                                <p className="text-sm text-gray-500 mt-2">
                                    Alt Text Will add automatically when upload Media file
                                </p>
                            </div>

                            <hr className="my-4 border-gray-200" />

                            {/* Use Post Title as Caption */}
                            <div className="mb-6">
                                <label className="block text-sm font-medium mb-2">Use Post Title as Caption</label>
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        onChange={setDefaultText}
                                        name="caption_text_by_post_title"
                                        value="caption_text_by_post_title"
                                        checked={'caption_text_by_post_title' === stateValue.options.caption_text_by_post_title}
                                    />
                                    <span className="text-sm">
                                        Default Caption Text Base On Post Title
                                        {!tsmltParams.hasExtended && <span className="text-red-600 font-bold"> - PRO</span>}
                                    </span>
                                </label>
                                <p className="text-sm text-gray-500 mt-2">
                                    Caption Text will add automatically when upload Media as attached posts.
                                </p>
                            </div>

                            <hr className="my-4 border-gray-200" />

                            {/* Default Caption Text */}
                            <div className="mb-6">
                                <label className="block text-sm font-medium mb-2">Default Caption Text</label>
                                <div className="space-y-2">
                                    <label className="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            onChange={setDefaultText}
                                            name="default_caption_text"
                                            value="image_name_to_caption"
                                            checked={'image_name_to_caption' === stateValue.options.default_caption_text}
                                        />
                                        <span className="text-sm">Image name use as caption</span>
                                    </label>
                                    <label className="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            onChange={setDefaultText}
                                            name="default_caption_text"
                                            value="custom_text_to_caption"
                                            checked={'custom_text_to_caption' === stateValue.options.default_caption_text}
                                        />
                                        <span className="text-sm">Custom text</span>
                                    </label>
                                    {'custom_text_to_caption' === stateValue.options.default_caption_text && (
                                        <>
                                            <hr className="my-2.5 border-gray-200" />
                                            <textarea
                                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                rows="4"
                                                onChange={(event) =>
                                                    dispatch({
                                                        type: Types.UPDATE_OPTIONS,
                                                        options: {
                                                            ...stateValue.options,
                                                            media_default_caption: event.target.value,
                                                        },
                                                    })
                                                }
                                                value={stateValue.options.media_default_caption}
                                            />
                                        </>
                                    )}
                                </div>
                                <p className="text-sm text-gray-500 mt-2">
                                    Caption text will add automatically when upload Media file
                                </p>
                            </div>

                            <hr className="my-4 border-gray-200" />

                            {/* Use Post Title as Description */}
                            <div className="mb-6">
                                <label className="block text-sm font-medium mb-2">Use Post Title as Description</label>
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        onChange={setDefaultText}
                                        name="desc_text_by_post_title"
                                        value="desc_text_by_post_title"
                                        checked={'desc_text_by_post_title' === stateValue.options.desc_text_by_post_title}
                                    />
                                    <span className="text-sm">
                                        Default Description Text Base On Post Title
                                        {!tsmltParams.hasExtended && <span className="text-red-600 font-bold"> - PRO</span>}
                                    </span>
                                </label>
                                <p className="text-sm text-gray-500 mt-2">
                                    Description Text will add automatically when upload Media as attached posts.
                                </p>
                            </div>

                            <hr className="my-4 border-gray-200" />

                            {/* Default Description Text */}
                            <div className="mb-6">
                                <label className="block text-sm font-medium mb-2">Default Description Text</label>
                                <div className="space-y-2">
                                    <label className="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            onChange={setDefaultText}
                                            name="default_desc_text"
                                            value="image_name_to_desc"
                                            checked={'image_name_to_desc' === stateValue.options.default_desc_text}
                                        />
                                        <span className="text-sm">Image name use as description</span>
                                    </label>
                                    <label className="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            onChange={setDefaultText}
                                            name="default_desc_text"
                                            value="custom_text_to_desc"
                                            checked={'custom_text_to_desc' === stateValue.options.default_desc_text}
                                        />
                                        <span className="text-sm">Custom text</span>
                                    </label>
                                    {'custom_text_to_desc' === stateValue.options.default_desc_text && (
                                        <>
                                            <hr className="my-2.5 border-gray-200" />
                                            <textarea
                                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                rows="4"
                                                onChange={(event) =>
                                                    dispatch({
                                                        type: Types.UPDATE_OPTIONS,
                                                        options: {
                                                            ...stateValue.options,
                                                            media_default_desc: event.target.value,
                                                        },
                                                    })
                                                }
                                                value={stateValue.options.media_default_desc}
                                            />
                                        </>
                                    )}
                                </div>
                                <p className="text-sm text-gray-500 mt-2">
                                    Description text will add automatically when upload Media file
                                </p>
                            </div>

                            <hr className="my-4 border-gray-200" />

                            {/* Media Renamer Settings */}
                            <h3 className="text-2xl font-semibold m-0">Media Renamer Settings</h3>
                            <hr className="my-4 border-gray-200" />

                            {/* File Rename Prefix And Suffix */}
                            <div className="mb-6">
                                <label className="block text-sm font-medium mb-2">File Rename Prefix And Suffix</label>

                                <div className="mb-4">
                                    <h5 className="text-sm font-medium mt-0 mb-2">
                                        Rename prefix
                                        {!tsmltParams.hasExtended && <span className="text-red-600 font-bold"> - PRO</span>}
                                    </h5>
                                    <input
                                        type="text"
                                        className="inline-flex items-center w-[300px] px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="Prefix"
                                        onChange={(event) =>
                                            dispatch({
                                                type: Types.UPDATE_OPTIONS,
                                                options: {
                                                    ...stateValue.options,
                                                    media_rename_prefix: event.target.value,
                                                },
                                            })
                                        }
                                        value={stateValue.options.media_rename_prefix}
                                    />
                                    <p className="text-sm text-gray-500 mt-2">
                                        A file rename prefix is a set of characters, words, or numbers added at the beginning of
                                        a filename when renaming it. This helps in organizing files, improving SEO, or
                                        maintaining a consistent naming convention.
                                    </p>
                                </div>

                                <hr className="my-4 border-gray-200" />

                                <div>
                                    <h5 className="text-sm font-medium mb-2">
                                        Rename suffix
                                        {!tsmltParams.hasExtended && <span className="text-red-600 font-bold"> - PRO</span>}
                                    </h5>
                                    <input
                                        type="text"
                                        className="inline-flex items-center w-[300px] px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="Suffix"
                                        onChange={(event) =>
                                            dispatch({
                                                type: Types.UPDATE_OPTIONS,
                                                options: {
                                                    ...stateValue.options,
                                                    media_rename_suffix: event.target.value,
                                                },
                                            })
                                        }
                                        value={stateValue.options.media_rename_suffix}
                                    />
                                    <p className="text-sm text-gray-500 mt-2">
                                        A file rename suffix is a set of characters, words, or numbers added at the end of a
                                        filename when renaming it. This helps differentiate files, improve SEO, or maintain a
                                        structured naming convention.
                                    </p>
                                </div>
                            </div>

                            <hr className="my-4 border-gray-200" />

                            {/* Rename based on attached posts */}
                            <div className="mb-6">
                                <label className="block text-sm font-medium mb-2">Rename based on attached posts</label>
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        onChange={setDefaultText}
                                        name="auto_rename_by_post_title"
                                        value="auto_rename_by_post_title"
                                        checked={'auto_rename_by_post_title' === stateValue.options.auto_rename_by_post_title}
                                    />
                                    <span className="text-sm">
                                        Auto Rename by post title
                                        {!tsmltParams.hasExtended && <span className="text-red-600 font-bold"> - PRO</span>}
                                    </span>
                                </label>
                                <p className="text-sm text-gray-500 mt-2">
                                    When you edit a post and upload an image, it will be renamed automatically based on the post
                                    title.
                                </p>
                            </div>

                            <hr className="my-4 border-gray-200" />

                            {/* Others Media Auto Rename */}
                            <div className="mb-6">
                                <label className="block text-sm font-medium mb-2">Others Media Auto Rename</label>
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        onChange={setDefaultText}
                                        name="enable_auto_rename"
                                        value="enable_auto_rename"
                                        checked={'enable_auto_rename' === stateValue.options.enable_auto_rename}
                                    />
                                    <span className="text-sm">
                                        Custom text
                                        {!tsmltParams.hasExtended && <span className="text-red-600 font-bold"> - PRO</span>}
                                    </span>
                                </label>
                                <p className="text-sm text-gray-500 mt-2">
                                    Auto rename will apply automatically when upload Media file. File name will be unique by
                                    incremental number. Example: file-name.jpg next one file-name-1.jpg
                                </p>
                                {tsmltParams.hasExtended && 'enable_auto_rename' === stateValue.options.enable_auto_rename && (
                                    <>
                                        <hr className="my-2.5 border-gray-200" />
                                        <input
                                            type="text"
                                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="file name"
                                            onChange={(event) =>
                                                dispatch({
                                                    type: Types.UPDATE_OPTIONS,
                                                    options: {
                                                        ...stateValue.options,
                                                        media_auto_rename_text: event.target.value,
                                                    },
                                                })
                                            }
                                            value={stateValue.options.media_auto_rename_text}
                                        />
                                        <p className="text-sm text-gray-500 mt-2">
                                            <span className="text-red-600">
                                                Required Field. Write file name without extension. Remember !! Empty Value will not
                                                apply. <br /> Example: File Name
                                            </span>
                                        </p>
                                    </>
                                )}
                            </div>
                        </div>
                    )}

                    <button
                        className="fixed bottom-[100px] right-[100px] px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                        onClick={() =>
                            dispatch({
                                ...stateValue,
                                type: Types.UPDATE_OPTIONS,
                                saveType: Types.UPDATE_OPTIONS,
                            })
                        }
                    >
                        Save Settings
                    </button>
                </div>
            </div>
        </>
    );
}

export default Settings;