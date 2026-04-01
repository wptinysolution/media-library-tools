import React from 'react';
import { useStore } from '@/js/Utils/store';
import CheckboxField from '@/js/Component/Common/CheckboxField';
import TextInput from '@/js/Component/Common/TextInput';
import SettingRow from '@/js/Component/Common/SettingRow';
import ProLabel from "@/js/Component/ProLabel";

export default function RenamerSettings() {
    const { options, setOptions, setGeneralData } = useStore();

    const setDefaultText = (e: React.ChangeEvent<HTMLInputElement>) => {
        const proFields = ['enable_auto_rename', 'auto_rename_by_post_title'];
        if (!tsmltParams.hasExtended && proFields.includes(e.target.name)) {
            setGeneralData({ openProModal: true });
            return;
        }
        setOptions({
            [e.target.name]: options[e.target.name] !== e.target.value ? e.target.value : '',
        });
    };

    return (
        <div className="bg-white rounded-lg border border-gray-200">
            <div className="px-6 py-5 border-b border-gray-200">
                <h3 className="text-xl m-0! font-semibold text-gray-900">Media Renamer Settings</h3>
            </div>

            <div className="p-6 space-y-6">
                <div className="flex items-start gap-8">
                    <label className="text-base font-medium text-gray-900 whitespace-nowrap pt-2 min-w-50">
                        File Rename Prefix And Suffix:
                    </label>
                    <div className="flex-1 space-y-6">
                        <div className="space-y-2">
                            <div className="flex items-center gap-2">
                                <label className="text-base font-medium text-gray-900">Rename prefix</label>
                                {!tsmltParams.hasExtended && <ProLabel /> }
                            </div>
                            <TextInput
                                placeholder="Prefix"
                                onChange={(event) => setOptions({ media_rename_prefix: event.target.value })}
                                value={(options.media_rename_prefix as string) || ''}
                            />
                            <p className="text-sm text-gray-500">
                                A file rename prefix is a set of characters, words, or numbers added at the beginning of a filename when renaming it.
                            </p>
                        </div>

                        <div className="space-y-2 pt-4 border-t border-gray-200">
                            <div className="flex items-center gap-2">
                                <label className="text-base font-medium text-gray-900">Rename suffix</label>
                                {!tsmltParams.hasExtended && <ProLabel /> }
                            </div>
                            <TextInput
                                placeholder="Suffix"
                                onChange={(event) => setOptions({ media_rename_suffix: event.target.value })}
                                value={(options.media_rename_suffix as string) || ''}
                            />
                            <p className="text-sm text-gray-500">
                                A file rename suffix is a set of characters, words, or numbers added at the end of a filename when renaming it.
                            </p>
                        </div>
                    </div>
                </div>

                <SettingRow label="Rename based on attached posts:" bordered>
                    <CheckboxField
                        name="auto_rename_by_post_title"
                        value="auto_rename_by_post_title"
                        checked={'auto_rename_by_post_title' === options.auto_rename_by_post_title}
                        onChange={setDefaultText}
                        label="Auto Rename by post title"
                        isPro={!tsmltParams.hasExtended}
                    />
                    <p className="text-sm text-gray-500">
                        When you edit a post and upload an image, it will be renamed automatically based on the post title.
                    </p>
                </SettingRow>

                <SettingRow label="Others Media Auto Rename:" bordered>
                    <CheckboxField
                        name="enable_auto_rename"
                        value="enable_auto_rename"
                        checked={'enable_auto_rename' === options.enable_auto_rename}
                        onChange={setDefaultText}
                        label="Custom text"
                        isPro={!tsmltParams.hasExtended}
                    />
                    <p className="text-sm text-gray-500">
                        Auto rename will apply automatically when upload Media file.
                    </p>
                    {tsmltParams.hasExtended && 'enable_auto_rename' === options.enable_auto_rename && (
                        <div className="pt-4 space-y-2">
                            <TextInput
                                placeholder="file name"
                                onChange={(event) => setOptions({ media_auto_rename_text: event.target.value })}
                                value={options.media_auto_rename_text || ''}
                            />
                            <p className="text-sm! text-red-600">
                                Required Field. Write file name without extension.
                            </p>
                        </div>
                    )}
                </SettingRow>
            </div>
        </div>
    );
}
