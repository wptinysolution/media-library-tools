import { useState, useEffect } from 'react';
import DownloadCSV from '@/js/Component/ListTable/DownloadCSV';
import { useStore } from '@/js/Utils/store';
import { initialBulkExport } from '@/js/Utils/store';
import Modal from '@/js/Component/Common/Modal';

function BulkModalForCSV() {
    const { mediaData, bulkSubmitData, bulkExport, setBulkExport } = useStore();

    const defaultKeys = initialBulkExport.selectedKeys;
    const [selectedKeys, setSelectedKeys] = useState(defaultKeys);

    const media = mediaData?.posts || [];
    const selectedIds = bulkSubmitData?.ids || [];
    const REQUIRED_KEYS = ['ID', 'slug'];
    const filteredData = media.filter(item => selectedIds.includes(item.ID));

    function getSelectedKeysWithMeta(): string[] {
        const item = filteredData[0];
        const keys: string[] = [];
        defaultKeys.forEach((key) => {
            // The CSV column is "groups"; it is derived from the item's "categories" field.
            const sourceKey = 'groups' === key ? 'categories' : key;
            if (Object.prototype.hasOwnProperty.call(item, sourceKey)) {
                keys.push(key);
            }
        });
        if (item.custom_meta) {
            const meta = item.custom_meta as Record<string, unknown> || {};
            for (const metaKey in meta) {
                keys.push(metaKey);
            }
        }
        return keys;
    }

    const handleBulkModalCancel = () => {
        setBulkExport({ isModalOpen: false });
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

    const keys = bulkExport.isModalOpen && filteredData.length > 0 ? getSelectedKeysWithMeta() : [];

    return (
        <Modal
            isOpen={bulkExport.isModalOpen}
            onClose={handleBulkModalCancel}
            title="Download CSV"
            footer={
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
            }
        >
            <div className="px-6 py-5">
                <h4 className="text-base! font-semibold text-gray-900 mt-0! mb-4">What data would you like to export to CSV?</h4>
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
        </Modal>
    );
}

export default BulkModalForCSV;
