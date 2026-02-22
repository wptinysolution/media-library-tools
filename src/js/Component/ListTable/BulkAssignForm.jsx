import React from "react";

import ProgressBar from "@/js/Component/Common/ProgressBar";

const checkboxOptions = [
    { label: 'File Title Based on Attached Post', value: 'post_title' },
    { label: 'Alt Text Based on Attached Post', value: 'alt_text' },
    { label: 'Caption Based on Attached Post', value: 'caption' },
    { label: 'Description Based on Attached Post', value: 'post_description' },
];

export default function BulkAssignForm({ selectedValues, onToggle, progressBar }) {
    return (
        <div>
            <p className="text-sm text-gray-700">
                Are you certain about performing a bulk assignment based on the associated post title?
            </p>
            <div className="border-t border-gray-200 my-5"></div>
            <div className="flex items-start gap-6">
                <h5 className="text-sm font-semibold text-gray-900 m-0! whitespace-nowrap pt-1">
                    Select the checkbox
                </h5>
                <div className="flex flex-col gap-3">
                    {checkboxOptions.map((option) => (
                        <label key={option.value} className="inline-flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                checked={(selectedValues || []).includes(option.value)}
                                onChange={(e) => onToggle(option.value, e.target.checked)}
                            />
                            <span className="text-sm text-gray-900">{option.label}</span>
                        </label>
                    ))}
                </div>
            </div>
            {progressBar >= 0 && (
                <div className="mt-5">
                    <h5 className="text-sm font-semibold text-gray-900 mb-2">Progress:</h5>
                    <ProgressBar percent={progressBar} />
                </div>
            )}
        </div>
    );
}
