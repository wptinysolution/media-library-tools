import React, { useEffect, useRef, useState } from "react";

import { useStore } from "@/js/Utils/store";

import * as Types from "@/js/Utils/actionType";

import { getDirList, getRubbishFileType, notifications } from "@/js/Utils/Data";

import RubbishConfirmationModal from "./RubbishConfirmationModal";

function RubbishHeader() {

    const {
        options, setOptions,
        generalData, setGeneralData,
        rubbishMedia, setRubbishMedia,
        bulkRubbishData, setBulkRubbishData,
        bulkSubmitData, setBulkSubmitData,
        setSaveType,
    } = useStore();

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
        if (!generalData.isDirModalOpen) {
            return;
        }
        const responseDate = await getDirList();
        const preparedDate = await JSON.parse(responseDate.data);
        setGeneralData({
            scanRubbishDirList: preparedDate.dirList,
            scanDirNextSchedule: preparedDate.nextSchedule,
            scanRubbishDirLoading: false,
        });

        setBulkSubmitData({
            progressTotal: Object.entries(preparedDate.dirList).length
        });

        console.log('getDirList');
    };

    const openDirModal = () => {
        setGeneralData({ isDirModalOpen: true });
    };

    const handleChangeBulkType = (value) => {
        setBulkRubbishData({ type: value });
    };

    const statusFilterApply = (value) => {
        handleChangeBulkType('default');
        setRubbishMedia({
            isLoading: true,
            postQuery: {
                ...rubbishMedia.postQuery,
                fileStatus: value,
                paged: 1,
            }
        });
    };

    const fileTypeFilterApply = (value) => {
        setRubbishMedia({
            isLoading: true,
            postQuery: {
                ...rubbishMedia.postQuery,
                filterExtension: value,
                paged: 1,
            }
        });
    };

    const handleBulkSubmit = async () => {
        if (!tsmltParams.hasExtended) {
            setGeneralData({ openProModal: true });
            return;
        }
        if (!bulkRubbishData.ids.length) {
            notifications(false, 'No checkboxes are checked. Please select at least one item.');
            return;
        }

        if (!bulkRubbishData.type || 'default' === bulkRubbishData.type) {
            notifications(false, 'No Actions are selected. Please select one.');
            return;
        }

        setBulkRubbishData({ isModalOpen: true });
    };

    let options_list = [
        { value: 'delete', label: 'Delete' },
        { value: 'ignore', label: 'Ignore' },
    ];

    if ('ignore' === rubbishMedia.postQuery.fileStatus) {
        options_list = [
            { value: 'show', label: 'Make Deletable' },
        ];
    }

    useEffect(() => {
        getTheRubbishFileType();
    }, []);

    useEffect(() => {
        handleDirForModal();
    }, [generalData.isDirModalOpen]);

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
                    {options_list.map(option => (
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
                    defaultValue={rubbishMedia.postQuery.fileStatus || "show"}
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
                        setSaveType(Types.UPDATE_OPTIONS);
                        setRubbishMedia({ isLoading: true });
                    }}
                    onChange={(event) => {
                        setOptions({ rubbish_per_page: event.target.value });
                    }}
                    value={options.rubbish_per_page}
                />
            </div>
            <RubbishConfirmationModal/>
        </header>
    );
}

export default RubbishHeader;
