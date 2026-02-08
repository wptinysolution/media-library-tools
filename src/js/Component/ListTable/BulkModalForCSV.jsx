import React, { useState, useEffect } from "react";

import DownloadCSV from "@/js/Component/ListTable/DownloadCSV";

import { useStateValue } from "@/js/Utils/StateProvider";

import * as Types from "@/js/Utils/actionType";
import { initialState } from "@/js/Utils/reducer";

function BulkModalForCSV() {

    const [stateValue, dispatch] = useStateValue();

    const bulkExportData = stateValue.bulkExport;
    const defaultKeys = initialState.bulkExport.selectedKeys;

    const [selectedKeys, setSelectedKeys] = useState(defaultKeys);

    const media = stateValue.mediaData?.posts || [];
    const selectedIds = stateValue.bulkSubmitData?.ids || [];
    const REQUIRED_KEYS = ['ID', 'slug'];
    const filteredData = media.filter(item => selectedIds.includes(item.ID));

    function getSelectedKeysWithMeta() {
        const item = filteredData[0];
        const keys = [];
        defaultKeys.forEach((key) => {
            if (item.hasOwnProperty(key)) {
                keys.push(key);
            }
        });
        if (item.custom_meta) {
            const meta = item.custom_meta || {};
            for (const metaKey in meta) {
                keys.push(metaKey);
            }
        }
        return keys;
    }

    const handleBulkModalCancel = () => {
        dispatch({
            type: Types.EXPORT_CSV,
            bulkExport: {
                ...bulkExportData,
                isModalOpen: false,
            },
        });
    };

    const onToggleKey = (key, checked) => {
        const updated = checked
            ? [...selectedKeys, key]
            : selectedKeys.filter(k => k !== key);
        setSelectedKeys(updated);
    };

    useEffect(() => {
        dispatch({
            type: Types.EXPORT_CSV,
            bulkExport: {
                ...bulkExportData,
                selectedKeys: selectedKeys
            },
        });
    }, [selectedKeys]);

    useEffect(() => {
        const handleEsc = (e) => {
            if (e.key === 'Escape' && bulkExportData.isModalOpen) {
                handleBulkModalCancel();
            }
        };
        document.addEventListener('keydown', handleEsc);
        return () => document.removeEventListener('keydown', handleEsc);
    }, [bulkExportData.isModalOpen]);

    if (!bulkExportData.isModalOpen) return null;

    const keys = getSelectedKeysWithMeta();

    return (
        <div className="fixed inset-0 z-[1000] flex items-center justify-center">
            {/* Backdrop */}
            <div
                className="absolute inset-0 bg-black/45"
                onClick={handleBulkModalCancel}
            />

            {/* Modal */}
            <div className="relative bg-white rounded-lg shadow-xl w-full max-w-[650px] mx-4">
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h3 className="text-lg font-semibold text-gray-900 m-0!">Download CSV</h3>
                    <button
                        type="button"
                        className="p-1 text-gray-400 hover:text-gray-600 cursor-pointer transition-colors"
                        onClick={handleBulkModalCancel}
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Body */}
                <div className="px-6 py-5">
                    <h4 className="text-base font-semibold text-gray-900 mb-4">Select which data will be exported to CSV</h4>
                    <div className="max-h-[300px] overflow-y-auto border border-gray-200 rounded-lg p-3 bg-gray-50">
                        {keys.map((key) => {
                            const isRequired = REQUIRED_KEYS.includes(key);
                            return (
                                <div key={key} className="mb-2 last:mb-0">
                                    <label className={`inline-flex items-center gap-2 ${isRequired ? 'opacity-60' : 'cursor-pointer'}`}>
                                        <input
                                            type="checkbox"
                                            className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer disabled:cursor-not-allowed"
                                            checked={selectedKeys.includes(key)}
                                            disabled={isRequired}
                                            onChange={(e) => onToggleKey(key, e.target.checked)}
                                        />
                                        <span className="text-sm text-gray-900">{key}</span>
                                    </label>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Footer */}
                <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200">
                    <button
                        type="button"
                        className="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors"
                        onClick={handleBulkModalCancel}
                    >
                        Cancel
                    </button>
                    <DownloadCSV />
                </div>
            </div>
        </div>
    )
}

export default BulkModalForCSV;
