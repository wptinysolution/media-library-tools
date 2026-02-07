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
            <div className="min-h-screen bg-gray-50 overflow-y-auto pb-32">
                <div className="max-w-7xl mx-auto px-6 py-8">
                    {stateValue.options.isLoading ? (
                        <Loader fullScreen />
                    ) : (
                        <div className="space-y-8">
                            {/* Media Table Settings */}
                            <div className="bg-white rounded-lg border border-gray-200">
                                <div className="px-6 py-5 border-b border-gray-200">
                                    <h3 className="text-xl m-0! font-semibold text-gray-900">Media Table Settings</h3>
                                </div>

                                <div className="p-6 space-y-6">
                                    {/* Media Table Column */}
                                    <div className="flex items-start gap-8">
                                        <label className="text-base font-medium text-gray-900 whitespace-nowrap pt-1 min-w-[200px]">
                                            Media Table Column:
                                        </label>
                                        <div className="flex-1 space-y-4">
                                            <label className="inline-flex items-center gap-2 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                                    onChange={onCheckAllColumn}
                                                    checked={isCheckedDiff}
                                                />
                                                <span className="text-base text-gray-900">Check all</span>
                                            </label>

                                            <div className="flex flex-wrap gap-x-6 gap-y-3">
                                                {columnList.map((column) => (
                                                    <label key={column.key} className="inline-flex items-center gap-2 cursor-pointer">
                                                        <input
                                                            type="checkbox"
                                                            className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                                            checked={stateValue.options.media_table_column.includes(column.key)}
                                                            onChange={() => onChangeColumnList(column.key)}
                                                        />
                                                        <span className="text-base text-gray-900">{column.title}</span>
                                                    </label>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div className="p-6 space-y-6 border-t border-gray-200 ">
                                    <div className="flex items-start gap-8 ">
                                        <label className="text-base font-medium text-gray-900 whitespace-nowrap pt-1 min-w-[200px]">
                                            Others File Support:
                                        </label>
                                        <div className="flex-1 space-y-2">
                                            <label className="inline-flex items-center gap-2 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                                    checked={(stateValue.options.others_file_support || []).includes('svg')}
                                                    onChange={() => onChangeOthersFileList('svg')}
                                                />
                                                <span className="text-base text-gray-900">SVG</span>
                                            </label>
                                            <p className="text-sm text-gray-500">Svg And Others File Upload.</p>
                                        </div>
                                    </div>
                                </div>
                                <div className="p-6 space-y-6 border-t border-gray-200">
                                    {/* Use Post Title as Alt Text */}
                                    <div className="flex items-start gap-8">
                                        <label className="text-base font-medium text-gray-900 whitespace-nowrap pt-1 min-w-[200px]">
                                            Use Post Title as Alt Text:
                                        </label>
                                        <div className="flex-1 space-y-2">
                                            <label className="inline-flex items-center gap-2 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                                    onChange={setDefaultText}
                                                    name="alt_text_by_post_title"
                                                    value="alt_text_by_post_title"
                                                    checked={'alt_text_by_post_title' === stateValue.options.alt_text_by_post_title}
                                                />
                                                <span className="text-base text-gray-900">
                                                    Default Alt Text Base On Post Title
                                                    {!tsmltParams.hasExtended && <span className="text-red-600 font-bold"> - PRO</span>}
                                                </span>
                                            </label>
                                            <p className="text-sm text-gray-500">
                                                Alt Text will add automatically when upload Media as attached posts.
                                            </p>
                                        </div>
                                    </div>

                                    {/* Default Images Alt Text */}
                                    <div className="flex items-start gap-8 pt-6 border-t border-gray-200">
                                        <label className="text-base font-medium text-gray-900 whitespace-nowrap pt-1 min-w-[200px]">
                                            Default Images Alt Text:
                                        </label>
                                        <div className="flex-1 space-y-2">
                                            <div className="flex flex-wrap gap-6">
                                                <label className="inline-flex items-center gap-2 cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                                        onChange={setDefaultText}
                                                        name="default_alt_text"
                                                        value="image_name_to_alt"
                                                        checked={'image_name_to_alt' === stateValue.options.default_alt_text}
                                                    />
                                                    <span className="text-base text-gray-900">Image name use as alt text</span>
                                                </label>

                                                <label className="inline-flex items-center gap-2 cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                                        onChange={setDefaultText}
                                                        name="default_alt_text"
                                                        value="custom_text_to_alt"
                                                        checked={'custom_text_to_alt' === stateValue.options.default_alt_text}
                                                    />
                                                    <span className="text-base text-gray-900">Custom text</span>
                                                </label>
                                            </div>

                                            {'custom_text_to_alt' === stateValue.options.default_alt_text && (
                                                <div className="pt-4">
                                                    <textarea
                                                        className="w-full max-w-2xl px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
                                                        rows="3"
                                                        placeholder="Enter your custom alt text..."
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
                                                </div>
                                            )}

                                            <p className="text-sm text-gray-500">
                                                Alt Text Will add automatically when upload Media file
                                            </p>
                                        </div>
                                    </div>
                                </div>
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
                                                                options: {
                                                                    ...stateValue.options,
                                                                    media_default_caption: event.target.value,
                                                                },
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
                                <div className="p-6 space-y-6 border-t border-gray-200">
                                    {/* Use Post Title as Description */}
                                    <div className="flex items-start gap-8">
                                        <label className="text-base font-medium text-gray-900 whitespace-nowrap pt-1 min-w-[200px]">
                                            Use Post Title as Description:
                                        </label>
                                        <div className="flex-1 space-y-2">
                                            <label className="inline-flex items-center gap-2 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                                    onChange={setDefaultText}
                                                    name="desc_text_by_post_title"
                                                    value="desc_text_by_post_title"
                                                    checked={'desc_text_by_post_title' === stateValue.options.desc_text_by_post_title}
                                                />
                                                <span className="text-base text-gray-900">
                                                    Default Description Text Base On Post Title
                                                    {!tsmltParams.hasExtended && <span className="text-red-600 font-bold"> - PRO</span>}
                                                </span>
                                            </label>
                                            <p className="text-sm text-gray-500">
                                                Description Text will add automatically when upload Media as attached posts.
                                            </p>
                                        </div>
                                    </div>

                                    {/* Default Description Text */}
                                    <div className="flex items-start gap-8 pt-6 border-t border-gray-200">
                                        <label className="text-base font-medium text-gray-900 whitespace-nowrap pt-1 min-w-[200px]">
                                            Default Description Text:
                                        </label>
                                        <div className="flex-1 space-y-2">
                                            <div className="flex flex-wrap gap-6">
                                                <label className="inline-flex items-center gap-2 cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                                        onChange={setDefaultText}
                                                        name="default_desc_text"
                                                        value="image_name_to_desc"
                                                        checked={'image_name_to_desc' === stateValue.options.default_desc_text}
                                                    />
                                                    <span className="text-base text-gray-900">Image name use as description</span>
                                                </label>

                                                <label className="inline-flex items-center gap-2 cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                                        onChange={setDefaultText}
                                                        name="default_desc_text"
                                                        value="custom_text_to_desc"
                                                        checked={'custom_text_to_desc' === stateValue.options.default_desc_text}
                                                    />
                                                    <span className="text-base text-gray-900">Custom text</span>
                                                </label>
                                            </div>

                                            {'custom_text_to_desc' === stateValue.options.default_desc_text && (
                                                <div className="pt-4">
                                                    <textarea
                                                        className="w-full max-w-2xl px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
                                                        rows="3"
                                                        placeholder="Enter your custom description..."
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
                                                </div>
                                            )}

                                            <p className="text-sm text-gray-500">
                                                Description text will add automatically when upload Media file
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Media Renamer Settings */}
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
                                                    <label className="text-base font-medium text-gray-900">
                                                        Rename prefix
                                                    </label>
                                                    {!tsmltParams.hasExtended && <span className="text-red-600 font-bold">- PRO</span>}
                                                </div>
                                                <input
                                                    type="text"
                                                    className="w-full max-w-md px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
                                                <p className="text-sm text-gray-500">
                                                    A file rename prefix is a set of characters, words, or numbers added at the beginning of a filename when renaming it. This helps in organizing files, improving SEO, or maintaining a consistent naming convention.
                                                </p>
                                            </div>

                                            <div className="space-y-2 pt-4 border-t border-gray-200">
                                                <div className="flex items-center gap-2">
                                                    <label className="text-base font-medium text-gray-900">
                                                        Rename suffix
                                                    </label>
                                                    {!tsmltParams.hasExtended && <span className="text-red-600 font-bold">- PRO</span>}
                                                </div>
                                                <input
                                                    type="text"
                                                    className="w-full max-w-md px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
                                                                options: {
                                                                    ...stateValue.options,
                                                                    media_auto_rename_text: event.target.value,
                                                                },
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
                        </div>
                    )}

                    {/* Save Button */}
                    <button
                        className="fixed bottom-8 right-8 px-8 py-3 bg-blue-600 text-white text-sm font-medium rounded-lg shadow-lg hover:bg-blue-700 hover:shadow-xl focus:ring-4 focus:ring-blue-500 focus:ring-offset-2 transition-all"
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