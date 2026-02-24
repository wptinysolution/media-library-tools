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

    const [filterItems, setFilterItems] = useState<Array<{ value: string; label: string }>>([]);
    const perPageRef = useRef<HTMLInputElement>(null);

    const getTheRubbishFileType = async () => {
        const rubbishFile = await getRubbishFileType() as { fileTypes: string[] };
        const types = rubbishFile.fileTypes.map((item) => ({ value: item, label: item }));
        setFilterItems([{ value: '', label: 'Default' }, ...types]);
    };

    const handleDirForModal = async () => {
        if (!generalData.isDirModalOpen) {
            return;
        }
        const responseDate = await getDirList() as { data: string };
        const preparedDate = JSON.parse(responseDate.data) as {
            dirList: Record<string, { total_items: number; counted: number }>;
            nextSchedule: string;
        };
        setGeneralData({
            scanRubbishDirList: preparedDate.dirList,
            scanDirNextSchedule: preparedDate.nextSchedule,
            scanRubbishDirLoading: false,
        });

        setBulkSubmitData({
            progressTotal: Object.entries(preparedDate.dirList).length,
        });

        console.log('getDirList');
    };

    const openDirModal = () => {
        setGeneralData({ isDirModalOpen: true });
    };

    const handleChangeBulkType = (value: string) => {
        setBulkRubbishData({ type: value });
    };

    const statusFilterApply = (value: string) => {
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

    const fileTypeFilterApply = (value: string | null) => {
        setRubbishMedia({
            isLoading: true,
            postQuery: {
                ...rubbishMedia.postQuery,
                filterExtension: value ?? '',
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
                <select
                    className="!px-4 !py-2 !text-sm !text-gray-900 !bg-white !border !border-gray-300 !rounded-lg !shadow-none w-[150px] focus:!outline-none focus:!border-blue-500 focus:!ring-2 focus:!ring-blue-500/20 focus:!shadow-none hover:!border-gray-400"
                    onChange={(e) => handleChangeBulkType(e.target.value)}
                    defaultValue=""
                >
                    <option value="" disabled>Bulk Action</option>
                    {options_list.map(option => (
                        <option key={option.value} value={option.value}>{option.label}</option>
                    ))}
                </select>

                <button
                    className="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium cursor-pointer"
                    onClick={handleBulkSubmit}
                >
                    Bulk Actions
                </button>

                <span className="px-4 py-2 text-gray-700 font-medium">Status</span>
                <select
                    className="!px-4 !py-2 !text-sm !text-gray-900 !bg-white !border !border-gray-300 !rounded-lg !shadow-none w-[150px] focus:!outline-none focus:!border-blue-500 focus:!ring-2 focus:!ring-blue-500/20 focus:!shadow-none hover:!border-gray-400"
                    onChange={(e) => statusFilterApply(e.target.value || 'show')}
                    defaultValue={rubbishMedia.postQuery.fileStatus || "show"}
                >
                    <option value="show">Default</option>
                    <option value="ignore">Ignored File</option>
                </select>

                <span className="px-4 py-2 text-gray-700 font-medium">Extension</span>
                <select
                    className="!px-4 !py-2 !text-sm !text-gray-900 !bg-white !border !border-gray-300 !rounded-lg !shadow-none w-[150px] focus:!outline-none focus:!border-blue-500 focus:!ring-2 focus:!ring-blue-500/20 focus:!shadow-none hover:!border-gray-400"
                    onChange={(e) => fileTypeFilterApply(e.target.value || null)}
                    defaultValue=""
                >
                    {filterItems.map(item => (
                        <option key={item.value} value={item.value}>{item.label}</option>
                    ))}
                </select>

                <button
                    className="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium w-[150px] cursor-pointer"
                    onClick={openDirModal}
                >
                    Scan Directory
                </button>

                <button
                    className="px-4 py-2 text-gray-700 hover:text-gray-900 font-medium cursor-pointer"
                    onClick={() => perPageRef.current?.focus()}
                >
                    Items Per page
                </button>
                <input
                    ref={perPageRef}
                    type="number"
                    className="w-20 !px-3 !py-2 !text-sm !text-gray-900 !bg-white !border !border-gray-300 !rounded-lg !shadow-none focus:!outline-none focus:!border-blue-500 focus:!ring-2 focus:!ring-blue-500/20 focus:!shadow-none hover:!border-gray-400"
                    onBlur={async () => {
                        setSaveType(Types.UPDATE_OPTIONS);
                        setRubbishMedia({ isLoading: true });
                    }}
                    onChange={(event) => setOptions({ rubbish_per_page: event.target.value })}
                    value={options.rubbish_per_page as number | string | undefined}
                />
            </div>
            <RubbishConfirmationModal />
        </header>
    );
}

export default RubbishHeader;
