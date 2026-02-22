import React, { useEffect, useRef } from "react";

import { defaultBulkSubmitData } from '@/js/Utils/UtilData';

import { useStateValue } from "@/js/Utils/StateProvider";

import { useSearchDebounce } from "@/js/Utils/Hooks";

import * as Types from "@/js/Utils/actionType";

import { notifications } from "@/js/Utils/Data";

import SearchInput from "@/js/Component/Common/SearchInput";

const bulkOptions = [
    { value: 'csv_export', label: 'Export CSV' },
    { value: 'trash', label: 'Move to Trash' },
    { value: 'inherit', label: 'Restore' },
    { value: 'update', label: 'Update' },
    { value: 'delete', label: 'Delete Permanently' },
    { value: 'searchUses', label: 'Search Uses' },
    { value: 'bulkedit', label: 'Bulk Edit' },
    { value: 'bulkEditPostTitle', label: 'Bulk Edit Post Title' },
];

function TheHeader() {

    const [stateValue, dispatch] = useStateValue();

    const [search, setSearch] = useSearchDebounce();

    const inputRef = useRef(null);

    const handleSelectChange = (value, fieldName) => {
        dispatch({
            type: Types.GET_MEDIA_LIST,
            mediaData: {
                ...stateValue.mediaData,
                isLoading: true,
                postQuery: {
                    ...stateValue.mediaData.postQuery,
                    filtering: true,
                    paged: 1,
                    [fieldName]: value
                }
            },
        });

        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: defaultBulkSubmitData,
        });
    };

    const handleChangeBulkType = (value) => {
        const data = 'bulkedit' === value ? stateValue.bulkSubmitData.data : defaultBulkSubmitData.data;
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...stateValue.bulkSubmitData,
                type: value,
                data,
            },
        });
    };

    const handleBulkSubmit = () => {

        if ('bulkEditPostTitle' === stateValue.bulkSubmitData.type && !tsmltParams.hasExtended) {
            dispatch({
                type: Types.GENERAL_DATA,
                generalData: { ...stateValue.generalData, openProModal: true },
            });
            return;
        }

        if (!stateValue.bulkSubmitData.ids.length) {
            notifications(false, 'No checkboxes are checked. Please select at least one item.');
            return;
        }
        switch (stateValue.bulkSubmitData.type) {
            case 'csv_export':
                dispatch({
                    ...stateValue,
                    type: Types.EXPORT_CSV,
                    saveType: Types.EXPORT_CSV,
                    bulkExport: { ...stateValue.bulkExport, isModalOpen: true },
                });
                break;
            case 'trash':
            case 'inherit':
            case 'update':
            case 'delete':
            case 'searchUses':
                dispatch({ ...stateValue, type: Types.BULK_SUBMIT, saveType: Types.BULK_SUBMIT });
                break;
            case 'bulkedit':
            case 'bulkEditPostTitle':
                dispatch({
                    ...stateValue,
                    type: Types.BULK_SUBMIT,
                    saveType: null,
                    bulkSubmitData: { ...stateValue.bulkSubmitData, isModalOpen: true },
                });
                break;
            default:
                notifications(false, 'No Actions are selected. Please select one.');
        }
    };

    const upDateQuery = async () => {
        if (stateValue.mediaData.postQuery.searchKeyWords === search) {
            return;
        }
        await dispatch({
            type: Types.GET_MEDIA_LIST,
            mediaData: {
                ...stateValue.mediaData,
                postQuery: { ...stateValue.mediaData.postQuery, searchKeyWords: search }
            },
        });
        console.log(search);
    };

    const postQuery = stateValue.mediaData.postQuery;

    useEffect(() => {
        upDateQuery();
    }, [search]);

    const filteredBulkOptions = postQuery.filtering && 'trash' === postQuery.status
        ? bulkOptions.filter(item => 'trash' !== item.value)
        : bulkOptions.filter(item => 'inherit' !== item.value);

    return (
        <header className="bg-white border-b border-gray-200 px-6 py-4">
            <div className="flex flex-wrap items-center gap-3">
                {/* Bulk Actions Select */}
                <select
                    className="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent min-w-[280px] bg-white"
                    onChange={(e) => handleChangeBulkType(e.target.value)}
                    defaultValue=""
                >
                    <option value="" disabled>Bulk Apply</option>
                    {filteredBulkOptions.map(option => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>

                {/* Bulk Apply Button */}
                <button
                    className="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium"
                    onClick={handleBulkSubmit}
                >
                     Bulk Apply
                </button>

                {/* Status Select */}
                <select
                    className="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white"
                    onChange={(e) => handleSelectChange(e.target.value || null, 'status')}
                    defaultValue={stateValue.mediaData.postQuery.status || ""}
                >
                    <option value="">Status</option>
                    <option value="trash">Trash</option>
                </select>

                {/* Date Select */}
                <select
                    className="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white"
                    onChange={(e) => handleSelectChange(e.target.value || null, 'date')}
                    defaultValue={stateValue.mediaData.postQuery.date || ""}
                >
                    <option value="">All dates</option>
                    {stateValue.generalData?.dateList ? stateValue.generalData?.dateList?.map(date => (
                        <option key={date.value} value={date.value}>
                            {date.label}
                        </option>
                    )) : null}
                </select>

                {/* Categories Select */}
                <select
                    className="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white"
                    onChange={(e) => handleSelectChange(e.target.value || null, 'categories')}
                    defaultValue={stateValue.mediaData.postQuery.categories || ""}
                >
                    <option value="">Categories</option>
                    {stateValue.generalData.termsList?.map(term => (
                        <option key={term.value} value={term.value}>
                            {term.label}
                        </option>
                    ))}
                </select>

                {/* Search Input */}
                <SearchInput
                    placeholder="Keywords..."
                    onChange={(e) => setSearch(e.target.value)}
                />

                {/* Edit Mode Toggle Button */}
                <button
                    className={`px-6 py-2 border rounded-lg transition-colors font-medium w-[180px] ${
                        stateValue.singleMedia.formEdited
                            ? 'bg-blue-600 text-white border-blue-600 hover:bg-blue-700'
                            : 'bg-white text-blue-600 border-blue-600 hover:bg-blue-50'
                    }`}
                    onClick={() => dispatch({
                        type: Types.UPDATE_SINGLE_MEDIA,
                        singleMedia: {
                            ...stateValue.singleMedia,
                            formEdited: !stateValue.singleMedia.formEdited,
                        }
                    })}
                >
                    {stateValue.singleMedia.formEdited ? 'Disable Edit Mode' : 'Enable Edit Mode'}
                </button>

                {/* Items Per Page Label */}
                <button
                    className="px-4 py-2 text-gray-700 hover:text-gray-900 font-medium"
                    onClick={() => {
                        inputRef.current?.focus();
                    }}
                >
                    Items Per page
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
                    onChange={(event) => {
                        dispatch({
                            type: Types.UPDATE_OPTIONS,
                            options: { ...stateValue.options, media_per_page: event.target.value },
                        });
                    }}
                    value={stateValue.options.media_per_page}
                />
            </div>
        </header>
    );
}

export default TheHeader;
