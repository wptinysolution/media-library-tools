import React, { useRef, useEffect } from "react";

import { defaultBulkSubmitData } from "@/js/Utils/UtilData";

import { useStateValue } from "@/js/Utils/StateProvider";

import * as Types from "@/js/Utils/actionType";

import BulkRanameModal from "./BulkRanameModal";

import { useSearchDebounce } from "@/js/Utils/Hooks";
import { notifications } from "@/js/Utils/Data";

function RenamerMainHeader() {

    const [stateValue, dispatch] = useStateValue();

    const [search, setSearch] = useSearchDebounce();

    const inputRef = useRef(null);

    const handleChangeBulkType = (value) => {
        const data = 'bulkRename' === value ? stateValue.bulkSubmitData.data : defaultBulkSubmitData.data;
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...stateValue.bulkSubmitData,
                type: value,
                data,
            },
        });
    };

    const upDateQuery = async () => {
        if (stateValue.mediaData.postQuery.searchKeyWords === search) {
            return;
        }
        await dispatch({
            type: Types.GET_MEDIA_LIST,
            mediaData: {
                ...stateValue.mediaData,
                postQuery: {
                    ...stateValue.mediaData.postQuery,
                    searchKeyWords: search
                }
            },
        });
        console.log('search', search);
    };

    useEffect(() => {
        upDateQuery();
    }, [search]);

    const handleBulkSubmit = () => {
        if (['bulkRenameBySKU', 'bulkRenameByPostTitle'].includes(stateValue.bulkSubmitData.type) && !tsmltParams.hasExtended) {
            dispatch({
                type: Types.GENERAL_DATA,
                generalData: {
                    ...stateValue.generalData,
                    openProModal: true,
                },
            });
            return;
        }

        if (!stateValue.bulkSubmitData.ids.length) {
            notifications(false, 'No checkboxes are checked. Please select at least one item.');
            return;
        }

        switch (stateValue.bulkSubmitData.type) {
            case 'bulkRename':
            case 'bulkRenameBySKU':
            case 'bulkRenameByPostTitle':
                dispatch({
                    ...stateValue,
                    type: Types.BULK_SUBMIT,
                    saveType: null,
                    bulkSubmitData: {
                        ...stateValue.bulkSubmitData,
                        isModalOpen: true,
                    },
                });
                break;
            default:
                notifications(false, 'No Actions are selected. Please select one.');
        }
    };

    const options = [
        { value: 'bulkRename', label: 'Bulk Rename' },
        { value: 'bulkRenameByPostTitle', label: 'Rename Based on Attached Post Title' },
    ];
    if (tsmltParams?.hasWoo) {
        options.push({ value: 'bulkRenameBySKU', label: 'Rename Based on Product SKU' });
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
                    {options.map(option => (
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
                <div className="relative">
                    <input
                        type="text"
                        className="px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Keywords..."
                        onChange={(e) => setSearch(e.target.value)}
                    />
                    <svg
                        className="w-5 h-5 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                        />
                    </svg>
                </div>

                {/* Edit Mode Toggle Button */}
                <button
                    className={`px-6 py-2 border rounded-lg transition-colors font-medium w-[180px] cursor-pointer ${
                        stateValue.rename.formEdited
                            ? 'bg-blue-600 text-white border-blue-600 hover:bg-blue-700'
                            : 'bg-white text-blue-600 border-blue-600 hover:bg-blue-50'
                    }`}
                    onClick={() => dispatch({
                        type: Types.UPDATE_RENAMER_MEDIA,
                        rename: {
                            ...stateValue.rename,
                            formEdited: !stateValue.rename.formEdited,
                        }
                    })}
                >
                    {stateValue.rename.formEdited ? 'Disable Edit Mode' : 'Enable Edit Mode'}
                </button>

                {/* Items Per Page Label */}
                <button
                    className="px-4 py-2 text-gray-700 hover:text-gray-900 font-medium"
                    onClick={() => inputRef.current?.focus()}
                >
                    Items Per page (Max-1000)
                </button>

                {/* Items Per Page Input */}
                <input
                    ref={inputRef}
                    type="number"
                    className="w-20 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    onBlur={() => dispatch({
                        ...stateValue,
                        type: Types.UPDATE_OPTIONS,
                        saveType: Types.UPDATE_OPTIONS,
                    })}
                    onChange={(event) => dispatch({
                        ...stateValue,
                        type: Types.UPDATE_OPTIONS,
                        options: {
                            ...stateValue.options,
                            media_per_page: event.target.value,
                        }
                    })}
                    value={stateValue.options.media_per_page}
                />
            </div>
            <BulkRanameModal />
        </header>
    );
}

export default RenamerMainHeader;
