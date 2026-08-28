import { useState, useEffect } from "react";
import { createPortal } from "react-dom";
import { useStore, initialBulkExport } from "@/js/Utils/store";
import ExportCSV from "./ExportCSV";

interface ExportModalCSVProps {
    isModalOpen: boolean;
    setModalOpen: (open: boolean) => void;
}

function ExportModalCSV({ isModalOpen, setModalOpen }: ExportModalCSVProps) {
    const { exportImport, setBulkExport } = useStore();

    const filteredData = exportImport?.mediaFiles || [];
    const defaultKeys = initialBulkExport.selectedKeys;

    const [selectedKeys, setSelectedKeys] = useState(defaultKeys);

    const REQUIRED_KEYS = ['ID', 'slug'];

    function getSelectedKeysWithMeta(): string[] {
        const item = filteredData[0] || {};
        const keys: string[] = [];
        defaultKeys.forEach((key) => {
            // The CSV column is "groups"; it is derived from the item's "categories" field.
            const sourceKey = 'groups' === key ? 'categories' : key;
            if (Object.prototype.hasOwnProperty.call(item, sourceKey)) {
                keys.push(key);
            }
        });
        if (item.custom_meta) {
            const meta = item.custom_meta as Record<string, unknown>;
            for (const metaKey in meta) {
                keys.push(metaKey);
            }
        }
        return keys;
    }

    const handleBulkModalCancel = () => {
        setModalOpen(false);
    };

    const onToggleKey = (key: string, checked: boolean) => {
        const updated = checked
            ? [...selectedKeys, key]
            : selectedKeys.filter(k => k !== key);
        setSelectedKeys(updated);
    };

    useEffect(() => {
        setBulkExport({ selectedKeys });
    }, [selectedKeys]);

    useEffect(() => {
        const handleEsc = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && isModalOpen) {
                handleBulkModalCancel();
            }
        };
        document.addEventListener('keydown', handleEsc);
        return () => document.removeEventListener('keydown', handleEsc);
    }, [isModalOpen]);

    if (!isModalOpen) return null;

    const keys = getSelectedKeysWithMeta();

    return createPortal(
        <div className="fixed inset-0 flex items-center justify-center" style={{ zIndex: 999999 }}>
            <div className="absolute inset-0 bg-black/45" onClick={handleBulkModalCancel} />
            <div className="relative bg-white rounded-lg shadow-xl w-full max-w-[650px] mx-4">
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h3 className="text-lg font-semibold text-gray-900 m-0!">Download CSV</h3>
                    <button type="button" className="p-1 text-gray-400 hover:text-gray-600 cursor-pointer transition-colors" onClick={handleBulkModalCancel}>
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div className="px-6 py-5">
                    <h4 className="text-base font-semibold text-gray-900 mt-0! mb-4">What data would you like to export to CSV?</h4>
                    <div className="max-h-75 overflow-y-auto border border-gray-200 rounded-lg p-3 bg-gray-50">
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

                <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200">
                    <button type="button" className="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors" onClick={handleBulkModalCancel}>
                        Cancel
                    </button>
                    <ExportCSV />
                </div>
            </div>
        </div>,
        document.body
    );
}

export default ExportModalCSV;
