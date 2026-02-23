import React, { useEffect, useRef, useState } from "react";

import { useStateValue } from "@/js/Utils/StateProvider";

import * as Types from "@/js/Utils/actionType";

import { getDirList, getRubbishFileType, notifications } from "@/js/Utils/Data";

import RubbishConfirmationModal from "./RubbishConfirmationModal";

function RubbishHeader() {

    const [stateValue, dispatch] = useStateValue();

    const [filterItems, setFilterItems] = useState([]);

    const perPageRef = useRef(null);

    const getTheRubbishFileType = async () => {
        const rubbishFile = await getRubbishFileType();
        const types = await rubbishFile.fileTypes.map(
            (item) => ({
                value: item,
                label: item
            })
        );
        await setFilterItems([
            { value: '', label: 'Default' },
            ...types
        ]);
    };

    const handleDirForModal = async () => {
        if (!stateValue.generalData.isDirModalOpen) {
            return;
        }
        const responseDate = await getDirList();
        const preparedDate = await JSON.parse(responseDate.data);
        await dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                scanRubbishDirList: preparedDate.dirList,
                scanDirNextSchedule: preparedDate.nextSchedule,
                scanRubbishDirLoading: false,
            },
        });

        await dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...stateValue.bulkSubmitData,
                progressTotal: Object.entries(preparedDate.dirList).length
            },
        });

        console.log('getDirList');
    };

    const openDirModal = () => {
        dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                isDirModalOpen: true
            },
        });
    };

    const handleChangeBulkType = (value) => {
        dispatch({
            type: Types.BALK_RUBBISH,
            bulkRubbishData: {
                ...stateValue.bulkRubbishData,
                type: value
            },
        });
    };

    const statusFilterApply = (value) => {
        handleChangeBulkType('default');
        dispatch({
            type: Types.RUBBISH_MEDIA,
            rubbishMedia: {
                ...stateValue.rubbishMedia,
                isLoading: true,
                postQuery: {
                    ...stateValue.rubbishMedia.postQuery,
                    fileStatus: value,
                    paged: 1,
                }
            },
        });
    };

    const fileTypeFilterApply = (value) => {
        dispatch({
            type: Types.RUBBISH_MEDIA,
            rubbishMedia: {
                ...stateValue.rubbishMedia,
                isLoading: true,
                postQuery: {
                    ...stateValue.rubbishMedia.postQuery,
                    filterExtension: value,
                    paged: 1,
                }
            },
        });
    };

    const handleBulkSubmit = async () => {
        if (!tsmltParams.hasExtended) {
            await dispatch({
                type: Types.GENERAL_DATA,
                generalData: {
                    ...stateValue.generalData,
                    openProModal: true,
                },
            });
            return;
        }
        if (!stateValue.bulkRubbishData.ids.length) {
            notifications(false, 'No checkboxes are checked. Please select at least one item.');
            return;
        }

        if (!stateValue.bulkRubbishData.type || 'default' === stateValue.bulkRubbishData.type) {
            notifications(false, 'No Actions are selected. Please select one.');
            return;
        }

        dispatch({
            type: Types.BALK_RUBBISH,
            bulkRubbishData: {
                ...stateValue.bulkRubbishData,
                isModalOpen: true,
            },
        });
    };

    let options = [
        { value: 'delete', label: 'Delete' },
        { value: 'ignore', label: 'Ignore' },
    ];

    if ('ignore' === stateValue.rubbishMedia.postQuery.fileStatus) {
        options = [
            { value: 'show', label: 'Make Deletable' },
        ];
    }

    useEffect(() => {
        getTheRubbishFileType();
    }, []);

    useEffect(() => {
        handleDirForModal();
    }, [stateValue.generalData.isDirModalOpen]);

    return (
        <header className="bg-white border-b border-gray-200 px-6 py-4">
            <div className="border border-gray-200 px-4 py-3 mb-4 text-[13px] text-red-600 font-medium rounded">
                Rubbish File Note: A "Rubbish File" refers to a file that exists within a directory but is not included in the media library or database.
                Before making any changes it is highly recommended to take a backup. Item Per page maximum allowed 1000 for ignoring server capacity issue.
            </div>
            <div className="flex flex-wrap items-center gap-3">
                {/* Bulk Action Select */}
                <select
                    className="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent w-[150px] bg-white"
                    onChange={(e) => handleChangeBulkType(e.target.value)}
                    defaultValue=""
                >
                    <option value="" disabled>Bulk Action</option>
                    {options.map(option => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>

                {/* Bulk Actions Button */}
                <button
                    className="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium cursor-pointer"
                    onClick={handleBulkSubmit}
                >
                    Bulk Actions
                </button>

                {/* Status Label + Select */}
                <span className="px-4 py-2 text-gray-700 font-medium">Status</span>
                <select
                    className="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent w-[150px] bg-white"
                    onChange={(e) => statusFilterApply(e.target.value || 'show')}
                    defaultValue={stateValue.rubbishMedia.postQuery.fileStatus || "show"}
                >
                    <option value="show">Default</option>
                    <option value="ignore">Ignored File</option>
                </select>

                {/* Extension Label + Select */}
                <span className="px-4 py-2 text-gray-700 font-medium">Extension</span>
                <select
                    className="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent w-[150px] bg-white"
                    onChange={(e) => fileTypeFilterApply(e.target.value || null)}
                    defaultValue=""
                >
                    {filterItems.map(item => (
                        <option key={item.value} value={item.value}>
                            {item.label}
                        </option>
                    ))}
                </select>

                {/* Scan Directory Button */}
                <button
                    className="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium w-[150px] cursor-pointer"
                    onClick={openDirModal}
                >
                    Scan Directory
                </button>

                {/* Items Per Page */}
                <button
                    className="px-4 py-2 text-gray-700 hover:text-gray-900 font-medium cursor-pointer"
                    onClick={() => perPageRef.current?.focus()}
                >
                    Items Per page
                </button>
                <input
                    ref={perPageRef}
                    type="number"
                    className="w-20 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    onBlur={async () => {
                        await dispatch({
                            ...stateValue,
                            type: Types.UPDATE_OPTIONS,
                            saveType: Types.UPDATE_OPTIONS,
                        });
                        await dispatch({
                            type: Types.RUBBISH_MEDIA,
                            rubbishMedia: {
                                ...stateValue.rubbishMedia,
                                isLoading: true,
                            },
                        });
                    }}
                    onChange={(event) => {
                        dispatch({
                            type: Types.UPDATE_OPTIONS,
                            options: {
                                ...stateValue.options,
                                rubbish_per_page: event.target.value,
                            }
                        });
                    }}
                    value={stateValue.options.rubbish_per_page}
                />
            </div>
            <RubbishConfirmationModal/>
        </header>
    );
}

export default RubbishHeader;
