import { useEffect, useRef } from "react";
import { defaultBulkSubmitData } from '@/js/Utils/UtilData';
import { useStore } from "@/js/Utils/store";
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
    const {
        mediaData, setMediaData,
        options, setOptions,
        generalData, setGeneralData,
        singleMedia, setSingleMedia,
        bulkSubmitData, setBulkSubmitData,setBulkExport,
        setSaveType,
    } = useStore();

    const [search, setSearch] = useSearchDebounce();
    const inputRef = useRef<HTMLInputElement>(null);

    const handleSelectChange = (value: string | null, fieldName: string) => {
        setMediaData({
            isLoading: true,
            postQuery: {
                ...mediaData.postQuery,
                filtering: true,
                paged: 1,
                [fieldName]: value,
            }
        });
        setBulkSubmitData(defaultBulkSubmitData);
    };

    const handleChangeBulkType = (value: string) => {
        const data = 'bulkedit' === value ? bulkSubmitData.data : defaultBulkSubmitData.data;
        setBulkSubmitData({ type: value, data });
    };

    const handleBulkSubmit = () => {
        if ('bulkEditPostTitle' === bulkSubmitData.type && !tsmltParams.hasExtended) {
            setGeneralData({ openProModal: true });
            return;
        }

        if (!bulkSubmitData.ids.length) {
            notifications(false, 'No checkboxes are checked. Please select at least one item.');
            return;
        }

        switch (bulkSubmitData.type) {
            case 'csv_export':
                setBulkExport({ isModalOpen: true });
                break;
            case 'trash':
            case 'inherit':
            case 'update':
            case 'delete':
            case 'searchUses':
                setSaveType(Types.BULK_SUBMIT);
                break;
            case 'bulkedit':
            case 'bulkEditPostTitle':
                setBulkSubmitData({ isModalOpen: true });
                setSaveType(null);
                break;
            default:
                notifications(false, 'No Actions are selected. Please select one.');
        }
    };

    const upDateQuery = async () => {
        if (mediaData.postQuery.searchKeyWords === search) {
            return;
        }
        setMediaData({
            postQuery: { ...mediaData.postQuery, searchKeyWords: search }
        });
        console.log(search);
    };

    const postQuery = mediaData.postQuery;

    useEffect(() => {
        upDateQuery();
    }, [search]);

    const filteredBulkOptions = postQuery.filtering && 'trash' === postQuery.status
        ? bulkOptions.filter(item => 'trash' !== item.value)
        : bulkOptions.filter(item => 'inherit' !== item.value);

    return (
        <header className="bg-white border-b border-gray-200 px-6 py-3 shadow-sm">
            <div className="flex flex-wrap items-center gap-2">

                {/* Bulk Actions group */}
                <div className="flex items-center gap-2">
                    <select
                        className="!px-3 !py-2 !text-sm !text-gray-900 !bg-white !border !border-gray-300 !rounded-md !shadow-none min-w-[200px] focus:!outline-none focus:!border-blue-500 focus:!ring-2 focus:!ring-blue-500/20 focus:!shadow-none hover:!border-gray-400"
                        onChange={(e) => handleChangeBulkType(e.target.value)}
                        defaultValue=""
                    >
                        <option value="" disabled>Bulk Actions</option>
                        {filteredBulkOptions.map(option => (
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

                {/* Filter group */}
                <div className="flex items-center gap-2 flex-wrap">
                    <select
                        className="!px-3 !py-2 !text-sm !text-gray-900 !bg-white !border !border-gray-300 !rounded-md !shadow-none focus:!outline-none focus:!border-blue-500 focus:!ring-2 focus:!ring-blue-500/20 focus:!shadow-none hover:!border-gray-400"
                        onChange={(e) => handleSelectChange(e.target.value || null, 'status')}
                        defaultValue={mediaData.postQuery.status || ""}
                    >
                        <option value="">All Status</option>
                        <option value="trash">Trash</option>
                    </select>

                    <select
                        className="!px-3 !py-2 !text-sm !text-gray-900 !bg-white !border !border-gray-300 !rounded-md !shadow-none focus:!outline-none focus:!border-blue-500 focus:!ring-2 focus:!ring-blue-500/20 focus:!shadow-none hover:!border-gray-400"
                        onChange={(e) => handleSelectChange(e.target.value || null, 'date')}
                        defaultValue={mediaData.postQuery.date || ""}
                    >
                        <option value="">All Dates</option>
                        {generalData?.dateList?.map(date => (
                            <option key={date.value} value={date.value}>{date.label}</option>
                        ))}
                    </select>

                    <select
                        className="!px-3 !py-2 !text-sm !text-gray-900 !bg-white !border !border-gray-300 !rounded-md !shadow-none focus:!outline-none focus:!border-blue-500 focus:!ring-2 focus:!ring-blue-500/20 focus:!shadow-none hover:!border-gray-400"
                        onChange={(e) => handleSelectChange(e.target.value || null, 'categories')}
                        defaultValue={mediaData.postQuery.categories || ""}
                    >
                        <option value="">All Categories</option>
                        {generalData.termsList?.map(term => (
                            <option key={term.value} value={term.value}>{term.label}</option>
                        ))}
                    </select>

                    <SearchInput
                        placeholder="Search keywords..."
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </div>

                {/* Divider */}
                <span className="hidden sm:block h-6 w-px bg-gray-300 mx-1" />

                {/* Controls group */}
                <div className="flex items-center gap-2">
                    <button
                        className={`px-4 py-2 text-sm border rounded-md transition-colors font-medium whitespace-nowrap cursor-pointer ${
                            singleMedia.formEdited
                                ? 'bg-blue-600 text-white border-blue-600 hover:bg-blue-700'
                                : 'bg-white text-blue-600 border-blue-300 hover:bg-blue-50 hover:border-blue-500'
                        }`}
                        onClick={() => setSingleMedia({ formEdited: !singleMedia.formEdited })}
                    >
                        {singleMedia.formEdited ? 'Disable Edit Mode' : 'Enable Edit Mode'}
                    </button>

                    <div className="flex items-center gap-1.5">
                        <label className="text-sm text-gray-600 whitespace-nowrap cursor-pointer" onClick={() => inputRef.current?.focus()}>
                            Per page:
                        </label>
                        <input
                            ref={inputRef}
                            type="number"
                            className="w-16 !px-2 !py-2 !text-sm !text-gray-900 !bg-white !border !border-gray-300 !rounded-md !shadow-none focus:!outline-none focus:!border-blue-500 focus:!ring-2 focus:!ring-blue-500/20 focus:!shadow-none hover:!border-gray-400"
                            onBlur={() => setSaveType(Types.UPDATE_OPTIONS)}
                            onChange={(event) => setOptions({ media_per_page: event.target.value })}
                            value={options.media_per_page as number | string}
                        />
                    </div>
                </div>
            </div>
        </header>
    );
}

export default TheHeader;
