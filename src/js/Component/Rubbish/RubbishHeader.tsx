import { useEffect, useState } from "react";
import { useStore } from "@/js/Utils/store";
import { getDirList, getRubbishFileType, notifications } from "@/js/Utils/Data";
import RubbishConfirmationModal from "./RubbishConfirmationModal";

function RubbishHeader() {
    const {
        options, setOptions,
        generalData,
        setGeneralData,
        rubbishMedia,
        setRubbishMedia,
        bulkRubbishData,
        setBulkRubbishData,
        setBulkSubmitData,
    } = useStore();

    const [filterItems, setFilterItems] = useState<Array<{ value: string; label: string }>>([]);

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
        <header className="bg-white border-b border-gray-200 px-6 py-3 shadow-sm">
            <div className="flex items-start gap-2 px-4 py-2.5 mb-3 text-[13px] text-amber-800 bg-amber-50 border border-amber-200 rounded-md">
                <svg className="w-4 h-4 mt-0.5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span><strong>Note:</strong> A "Rubbish File" exists in the directory but is not in the media library. Back up before making changes. Max 1000 items per page.</span>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                {/* Bulk Actions group */}
                <div className="flex items-center gap-2">
                    <select
                        className="px-3! py-2! text-sm! text-gray-900! bg-white! border! border-gray-300! rounded-md! shadow-none! w-[150px] focus:outline-none! focus:border-blue-500! focus:ring-2! focus:ring-blue-500/20! focus:shadow-none! hover:border-gray-400!"
                        onChange={(e) => handleChangeBulkType(e.target.value)}
                        defaultValue=""
                    >
                        <option value="" disabled>Bulk Action</option>
                        {options_list.map(option => (
                            <option key={option.value} value={option.value}>{option.label}</option>
                        ))}
                    </select>

                    <button
                        className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-md transition-colors font-medium cursor-pointer whitespace-nowrap"
                        onClick={handleBulkSubmit}
                    >
                        Apply
                    </button>
                </div>

                {/* Divider */}
                <span className="hidden sm:block h-6 w-px bg-gray-300 mx-1" />

                {/* Filters group */}
                <div className="flex items-center gap-2">
                    <label className="text-sm text-gray-600 whitespace-nowrap">Status:</label>
                    <select
                        className="px-3! py-2! text-sm! text-gray-900! bg-white! border! border-gray-300! rounded-md! shadow-none! w-[140px] focus:outline-none! focus:border-blue-500! focus:ring-2! focus:ring-blue-500/20! focus:shadow-none! hover:border-gray-400!"
                        onChange={(e) => statusFilterApply(e.target.value || 'show')}
                        defaultValue={rubbishMedia.postQuery.fileStatus || "show"}
                    >
                        <option value="show">Default</option>
                        <option value="ignore">Ignored File</option>
                    </select>

                    <label className="text-sm text-gray-600 whitespace-nowrap">Extension:</label>
                    <select
                        className="px-3! py-2! text-sm! text-gray-900! bg-white! border! border-gray-300! rounded-md! shadow-none! w-[130px] focus:outline-none! focus:border-blue-500! focus:ring-2! focus:ring-blue-500/20! focus:shadow-none! hover:border-gray-400!"
                        onChange={(e) => fileTypeFilterApply(e.target.value || null)}
                        defaultValue=""
                    >
                        {filterItems.map(item => (
                            <option key={item.value} value={item.value}>{item.label}</option>
                        ))}
                    </select>
                </div>

                {/* Divider */}
                <span className="hidden sm:block h-6 w-px bg-gray-300 mx-1" />

                {/* Controls group */}
                <div className="flex items-center gap-2">
                    <button
                        className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-md transition-colors font-medium cursor-pointer whitespace-nowrap"
                        onClick={openDirModal}
                    >
                        Scan Directory
                    </button>
                </div>
                <div className="flex items-center gap-1.5">
                    <label className="text-sm text-gray-500 whitespace-nowrap">Per page:</label>
                    <input
                        type="number"
                        className="w-16 px-2! py-1.5! text-sm! text-gray-900! bg-white! border! border-gray-300! rounded-md! shadow-none! focus:outline-none! focus:border-blue-500! focus:ring-2! focus:ring-blue-500/20! focus:shadow-none! hover:border-gray-400!"
                        value={options.rubbish_per_page as number | string}
                        onChange={(event) => { localStorage.setItem('mlt_rubbish_per_page', event.target.value); setOptions({ rubbish_per_page: event.target.value }); }}
                        onBlur={() => setRubbishMedia({ isLoading: true, postQuery: { ...rubbishMedia.postQuery, postsPerPage: parseInt(String(options.rubbish_per_page || 20), 10), paged: 1, isQueryUpdate: true } })}
                    />
                </div>

            </div>
            <RubbishConfirmationModal />
        </header>
    );
}

export default RubbishHeader;
