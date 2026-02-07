import React from 'react';
import { useStateValue } from '@/js/Utils/StateProvider';
import Loader from '@/js/Utils/Loader';
import { columnList } from '@/js/Utils/UtilData';
import * as Types from "@/js/Utils/actionType";
import MainHeader from "@/js/Component/MainHeader";
import SaveButton from '@/js/Component/SaveButton';

function CheckboxField({ label, name, value, checked, onChange, text, description, isPro, className, children }) {
    return (
        <div className={`flex items-start gap-8 ${className || ''}`}>
            <label className="text-base font-medium text-gray-900 whitespace-nowrap pt-1 min-w-[200px]">
                {label}
            </label>
            <div className="flex-1 space-y-2">
                <label className="inline-flex items-center gap-2 cursor-pointer">
                    <input
                        type="checkbox"
                        className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                        onChange={onChange}
                        name={name}
                        value={value}
                        checked={checked}
                    />
                    <span className="text-base text-gray-900">
                        {text}
                        {isPro && !tsmltParams.hasExtended && <span className="text-red-600 font-bold"> - PRO</span>}
                    </span>
                </label>
                {description && <p className="text-sm text-gray-500">{description}</p>}
                {children}
            </div>
        </div>
    );
}

function DefaultTextField({ label, optionName, imageNameValue, imageNameLabel, customTextValue, currentValue, textareaValue, textareaPlaceholder, textareaOptionKey, description, onChange, dispatch, stateValue, className }) {
    return (
        <div className={`flex items-start gap-8 ${className || ''}`}>
            <label className="text-base font-medium text-gray-900 whitespace-nowrap pt-1 min-w-[200px]">
                {label}
            </label>
            <div className="flex-1 space-y-2">
                <div className="flex flex-wrap gap-6">
                    <label className="inline-flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                            onChange={onChange}
                            name={optionName}
                            value={imageNameValue}
                            checked={imageNameValue === currentValue}
                        />
                        <span className="text-base text-gray-900">{imageNameLabel}</span>
                    </label>
                    <label className="inline-flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                            onChange={onChange}
                            name={optionName}
                            value={customTextValue}
                            checked={customTextValue === currentValue}
                        />
                        <span className="text-base text-gray-900">Custom text</span>
                    </label>
                </div>
                {customTextValue === currentValue && (
                    <div className="pt-4">
                        <textarea
                            className="w-full max-w-2xl px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
                            rows="3"
                            placeholder={textareaPlaceholder}
                            onChange={(event) =>
                                dispatch({
                                    type: Types.UPDATE_OPTIONS,
                                    options: {
                                        ...stateValue.options,
                                        [textareaOptionKey]: event.target.value,
                                    },
                                })
                            }
                            value={textareaValue}
                        />
                    </div>
                )}
                <p className="text-sm text-gray-500">{description}</p>
            </div>
        </div>
    );
}

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

                                {/* Media Table Column */}
                                <div className="p-6 space-y-6">
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

                                {/* Others File Support */}
                                <div className="p-6 space-y-6 border-t border-gray-200">
                                    <CheckboxField
                                        label="Others File Support:"
                                        checked={(stateValue.options.others_file_support || []).includes('svg')}
                                        onChange={() => onChangeOthersFileList('svg')}
                                        text="SVG"
                                        description="Svg And Others File Upload."
                                    />
                                </div>

                                {/* Alt Text Settings */}
                                <div className="p-6 space-y-6 border-t border-gray-200">
                                    <CheckboxField
                                        label="Use Post Title as Alt Text:"
                                        name="alt_text_by_post_title"
                                        value="alt_text_by_post_title"
                                        checked={'alt_text_by_post_title' === stateValue.options.alt_text_by_post_title}
                                        onChange={setDefaultText}
                                        text="Default Alt Text Base On Post Title"
                                        description="Alt Text will add automatically when upload Media as attached posts."
                                        isPro
                                    />
                                    <DefaultTextField
                                        label="Default Images Alt Text:"
                                        optionName="default_alt_text"
                                        imageNameValue="image_name_to_alt"
                                        imageNameLabel="Image name use as alt text"
                                        customTextValue="custom_text_to_alt"
                                        currentValue={stateValue.options.default_alt_text}
                                        textareaOptionKey="media_default_alt"
                                        textareaValue={stateValue.options.media_default_alt}
                                        textareaPlaceholder="Enter your custom alt text..."
                                        description="Alt Text Will add automatically when upload Media file"
                                        onChange={setDefaultText}
                                        dispatch={dispatch}
                                        stateValue={stateValue}
                                        className="pt-6 border-t border-gray-200"
                                    />
                                </div>

                                {/* Caption Settings */}
                                <div className="p-6 space-y-6 border-t border-gray-200">
                                    <CheckboxField
                                        label="Use Post Title as Caption:"
                                        name="caption_text_by_post_title"
                                        value="caption_text_by_post_title"
                                        checked={'caption_text_by_post_title' === stateValue.options.caption_text_by_post_title}
                                        onChange={setDefaultText}
                                        text="Default Caption Text Base On Post Title"
                                        description="Caption Text will add automatically when upload Media as attached posts."
                                        isPro
                                    />
                                    <DefaultTextField
                                        label="Default Caption Text:"
                                        optionName="default_caption_text"
                                        imageNameValue="image_name_to_caption"
                                        imageNameLabel="Image name use as caption"
                                        customTextValue="custom_text_to_caption"
                                        currentValue={stateValue.options.default_caption_text}
                                        textareaOptionKey="media_default_caption"
                                        textareaValue={stateValue.options.media_default_caption}
                                        textareaPlaceholder="Enter your custom caption..."
                                        description="Caption text will add automatically when upload Media file"
                                        onChange={setDefaultText}
                                        dispatch={dispatch}
                                        stateValue={stateValue}
                                        className="pt-6 border-t border-gray-200"
                                    />
                                </div>

                                {/* Description Settings */}
                                <div className="p-6 space-y-6 border-t border-gray-200">
                                    <CheckboxField
                                        label="Use Post Title as Description:"
                                        name="desc_text_by_post_title"
                                        value="desc_text_by_post_title"
                                        checked={'desc_text_by_post_title' === stateValue.options.desc_text_by_post_title}
                                        onChange={setDefaultText}
                                        text="Default Description Text Base On Post Title"
                                        description="Description Text will add automatically when upload Media as attached posts."
                                        isPro
                                    />
                                    <DefaultTextField
                                        label="Default Description Text:"
                                        optionName="default_desc_text"
                                        imageNameValue="image_name_to_desc"
                                        imageNameLabel="Image name use as description"
                                        customTextValue="custom_text_to_desc"
                                        currentValue={stateValue.options.default_desc_text}
                                        textareaOptionKey="media_default_desc"
                                        textareaValue={stateValue.options.media_default_desc}
                                        textareaPlaceholder="Enter your custom description..."
                                        description="Description text will add automatically when upload Media file"
                                        onChange={setDefaultText}
                                        dispatch={dispatch}
                                        stateValue={stateValue}
                                        className="pt-6 border-t border-gray-200"
                                    />
                                </div>
                            </div>

                            {/* Media Renamer Settings */}
                            <div className="bg-white rounded-lg border border-gray-200">
                                <div className="px-6 py-5 border-b border-gray-200">
                                    <h3 className="text-xl m-0! font-semibold text-gray-900">Media Renamer Settings</h3>
                                </div>

                                <div className="p-6 space-y-6">
                                    {/* Prefix & Suffix */}
                                    <div className="flex items-start gap-8">
                                        <label className="text-base font-medium text-gray-900 whitespace-nowrap pt-1 min-w-[200px]">
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
                                    <CheckboxField
                                        label="Rename based on attached posts:"
                                        name="auto_rename_by_post_title"
                                        value="auto_rename_by_post_title"
                                        checked={'auto_rename_by_post_title' === stateValue.options.auto_rename_by_post_title}
                                        onChange={setDefaultText}
                                        text="Auto Rename by post title"
                                        description="When you edit a post and upload an image, it will be renamed automatically based on the post title."
                                        isPro
                                        className="pt-6 border-t border-gray-200"
                                    />

                                    {/* Others Media Auto Rename */}
                                    <CheckboxField
                                        label="Others Media Auto Rename:"
                                        name="enable_auto_rename"
                                        value="enable_auto_rename"
                                        checked={'enable_auto_rename' === stateValue.options.enable_auto_rename}
                                        onChange={setDefaultText}
                                        text="Custom text"
                                        description="Auto rename will apply automatically when upload Media file. File name will be unique by incremental number. Example: file-name.jpg next one file-name-1.jpg"
                                        isPro
                                        className="pt-6 border-t border-gray-200"
                                    >
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
                                    </CheckboxField>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Save Button */}
                    <SaveButton />
                </div>
            </div>
        </>
    );
}

export default Settings;
