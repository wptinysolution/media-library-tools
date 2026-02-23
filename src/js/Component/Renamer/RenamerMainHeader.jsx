import React, { useRef, useEffect } from "react";

import { defaultBulkSubmitData } from "@/js/Utils/UtilData";

import { useStore } from "@/js/Utils/store";

import * as Types from "@/js/Utils/actionType";

import BulkRanameModal from "./BulkRanameModal";

import { useSearchDebounce } from "@/js/Utils/Hooks";

import { notifications } from "@/js/Utils/Data";

import SearchInput from "@/js/Component/Common/SearchInput";

function RenamerMainHeader() {

    const {
        mediaData, setMediaData,
        options, setOptions,
        generalData, setGeneralData,
        rename, setRename,
        bulkSubmitData, setBulkSubmitData,
        setSaveType,
    } = useStore();

    const [search, setSearch] = useSearchDebounce();

    const inputRef = useRef(null);

    const handleChangeBulkType = (value) => {
        const data = 'bulkRename' === value ? bulkSubmitData.data : defaultBulkSubmitData.data;
        setBulkSubmitData({ type: value, data });
    };

    const upDateQuery = async () => {
        if (mediaData.postQuery.searchKeyWords === search) {
            return;
        }
        setMediaData({
            postQuery: { ...mediaData.postQuery, searchKeyWords: search }
        });
        console.log('search', search);
    };

    useEffect(() => {
        upDateQuery();
    }, [search]);

    const handleBulkSubmit = () => {
        if (['bulkRenameBySKU', 'bulkRenameByPostTitle'].includes(bulkSubmitData.type) && !tsmltParams.hasExtended) {
            setGeneralData({ openProModal: true });
            return;
        }

        if (!bulkSubmitData.ids.length) {
            notifications(false, 'No checkboxes are checked. Please select at least one item.');
            return;
        }

        switch (bulkSubmitData.type) {
            case 'bulkRename':
            case 'bulkRenameBySKU':
            case 'bulkRenameByPostTitle':
                setBulkSubmitData({ isModalOpen: true });
                setSaveType(null);
                break;
            default:
                notifications(false, 'No Actions are selected. Please select one.');
        }
    };

    const options_list = [
        { value: 'bulkRename', label: 'Bulk Rename' },
        { value: 'bulkRenameByPostTitle', label: 'Rename Based on Attached Post Title' },
    ];
    if (tsmltParams?.hasWoo) {
        options_list.push({ value: 'bulkRenameBySKU', label: 'Rename Based on Product SKU' });
    }

    return (
        <header className="bg-white border-b border-gray-200 px-6 py-4">
            <div className="border border-gray-200 px-4 py-3 mb-4 text-[13px] text-red-600 font-medium text-center rounded">
                Renamer Note: We suggest you before renaming at first you should practice in your staging site. Before making any changes to the "File Name," it is highly recommended to take a backup. Renaming the file will also modify file URL. If you have hardcoded the file URL anywhere, please ensure to update it with the new URL after renaming. Item Per page maximum allowed 1000 for ignoring server capacity issue.
            </div>

            <div className="flex flex-wrap items-center gap-3">
                {/* Bulk Apply Select */}
                <select
                    className="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent min-w-[285px] bg-white"
                    onChange={(e) => handleChangeBulkType(e.target.value)}
                    defaultValue=""
                >
                    <option value="" disabled>Bulk Apply</option>
                    {options_list.map(option => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>

                {/* Bulk Apply Button */}
                <button
                    className="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium cursor-pointer"
                    onClick={handleBulkSubmit}
                >
                    Bulk Apply
                </button>

                {/* Search Input */}
                <SearchInput
                    placeholder="Keywords..."
                    onChange={(e) => setSearch(e.target.value)}
                />

                {/* Edit Mode Toggle Button */}
                <button
                    className={`px-6 py-2 border rounded-lg transition-colors font-medium w-[180px] cursor-pointer ${
                        rename.formEdited
                            ? 'bg-blue-600 text-white border-blue-600 hover:bg-blue-700'
                            : 'bg-white text-blue-600 border-blue-600 hover:bg-blue-50'
                    }`}
                    onClick={() => setRename({ formEdited: !rename.formEdited })}
                >
                    {rename.formEdited ? 'Disable Edit Mode' : 'Enable Edit Mode'}
                </button>

                {/* Items Per Page Label */}
                <button
                    className="px-4 py-2 text-gray-700 hover:text-gray-900 font-medium cursor-pointer"
                    onClick={() => inputRef.current?.focus()}
                >
                    Items Per page (Max-1000)
                </button>

                {/* Items Per Page Input */}
                <input
                    ref={inputRef}
                    type="number"
                    className="w-20 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    onBlur={() => setSaveType(Types.UPDATE_OPTIONS)}
                    onChange={(event) => setOptions({ media_per_page: event.target.value })}
                    value={options.media_per_page}
                />
            </div>
            <BulkRanameModal />
        </header>
    );
}

export default RenamerMainHeader;
