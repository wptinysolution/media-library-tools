import React from 'react';
import Textarea from '@/js/Component/Common/Textarea';
import CheckboxField from '@/js/Component/Common/CheckboxField';
import { useStore } from '@/js/Utils/store';
import type { BulkSubmitDataItem, BulkSubmitData } from '@/js/Utils/store';

const fields = [
    { name: 'post_title', label: 'Title', placeholder: 'Title' },
    { name: 'alt_text', label: 'Alt Text', placeholder: 'Alt text' },
    { name: 'caption', label: 'Caption', placeholder: 'Caption' },
    { name: 'post_description', label: 'Description', placeholder: 'Description' },
];

const groupModes: Array<{ value: BulkSubmitData['post_categories_mode']; label: string; hint: string }> = [
    { value: 'add', label: 'Add to selected groups', hint: 'Keeps the groups each item already has.' },
    { value: 'replace', label: 'Replace all groups', hint: 'Removes every other group from the selected items.' },
    { value: 'remove', label: 'Remove from selected groups', hint: 'Detaches only the groups picked above.' },
];

interface BulkEditFormProps {
    data: BulkSubmitDataItem;
    onChange: React.ChangeEventHandler<HTMLTextAreaElement>;
    selectedGroups: string[];
    onToggleGroup: (value: string, checked: boolean) => void;
    groupMode: BulkSubmitData['post_categories_mode'];
    onGroupModeChange: (mode: BulkSubmitData['post_categories_mode']) => void;
}

export default function BulkEditForm({
    data,
    onChange,
    selectedGroups,
    onToggleGroup,
    groupMode,
    onGroupModeChange,
}: BulkEditFormProps) {
    const { generalData } = useStore();
    const termsList = generalData.termsList || [];
    const activeMode = groupModes.find(mode => mode.value === groupMode);

    return (
        <div className="space-y-4">
            {fields.map(({ name, label, placeholder }) => (
                <div key={name}>
                    <h5 className="text-sm font-semibold text-gray-900 m-0! mb-1.5">{label}</h5>
                    <Textarea
                        rows={2}
                        onChange={onChange}
                        name={name}
                        value={data[name as keyof BulkSubmitDataItem]}
                        placeholder={placeholder}
                    />
                </div>
            ))}

            <div className="pt-4 border-t border-gray-200">
                <h5 className="text-sm font-semibold text-gray-900 m-0! mb-1.5">Groups</h5>

                {termsList.length === 0 ? (
                    <p className="text-sm! text-gray-500 m-0!">
                        No groups exist yet. Create one under <strong>Media &rarr; Groups</strong> first.
                    </p>
                ) : (
                    <>
                        <div className="flex flex-col gap-2 max-h-44 overflow-y-auto border border-gray-200 rounded-md p-3">
                            {termsList.map(term => (
                                <CheckboxField
                                    key={term.value}
                                    value={String(term.value)}
                                    checked={selectedGroups.includes(String(term.value))}
                                    onChange={(e) => onToggleGroup(String(term.value), e.target.checked)}
                                    label={term.label}
                                />
                            ))}
                        </div>

                        {selectedGroups.length > 0 && (
                            <div className="mt-3">
                                <select
                                    className="w-full px-3! py-2! text-sm! text-gray-900! bg-white! border! border-gray-300! rounded-md! shadow-none! focus:outline-none! focus:border-blue-500! focus:ring-2! focus:ring-blue-500/20! focus:shadow-none! hover:border-gray-400!"
                                    value={groupMode}
                                    onChange={(e) => onGroupModeChange(e.target.value as BulkSubmitData['post_categories_mode'])}
                                >
                                    {groupModes.map(mode => (
                                        <option key={mode.value} value={mode.value}>{mode.label}</option>
                                    ))}
                                </select>
                                <p className={`text-xs! m-0! mt-1.5 ${'replace' === groupMode ? 'text-orange-600' : 'text-gray-500'}`}>
                                    {activeMode?.hint}
                                </p>
                            </div>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}
