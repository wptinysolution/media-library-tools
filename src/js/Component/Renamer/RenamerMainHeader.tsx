import { useEffect } from "react";
import { defaultBulkSubmitData } from "@/js/Utils/UtilData";
import { useStore } from "@/js/Utils/store";
import BulkRenameModal from "@/js/Component/Renamer/BulkRenameModal";
import { useSearchDebounce } from "@/js/Utils/Hooks";
import { notifications } from "@/js/Utils/Data";
import SearchInput from "@/js/Component/Common/SearchInput";

function RenamerMainHeader() {
    const {
        mediaData, setMediaData,
        options, setOptions,setGeneralData,
        rename, setRename,
        bulkSubmitData, setBulkSubmitData,
        setSaveType,
    } = useStore();

    const [search, searchQuery, setSearch] = useSearchDebounce();


    const handleChangeBulkType = (value: string) => {
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
                setBulkSubmitData({ isModalOpen: true, progressBar: 0 });
                setSaveType(null);
                break;
            default:
                notifications(false, 'No Actions are selected. Please select one.');
        }
    };

    const options_list: Array<{ value: string; label: string }> = [
        { value: 'bulkRename', label: 'Bulk Rename' },
        { value: 'bulkRenameByPostTitle', label: 'Rename Based on Attached Post Title' },
    ];
    if (tsmltParams?.hasWoo) {
        options_list.push({ value: 'bulkRenameBySKU', label: 'Rename Based on Product SKU' });
    }

    return (
        <header className="bg-white border-b border-gray-200 px-3 py-3 shadow-sm">
            <div className="flex items-start gap-2 px-4 py-2.5 mb-3 text-[13px] text-amber-800 bg-amber-50 border border-amber-200 rounded-md">
                <svg className="w-4 h-4 mt-0.5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span><strong>Note:</strong> Practice on a staging site first. Back up before renaming — file URLs will change. Max 1000 items per page.</span>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                {/* Bulk Actions group */}
                <div className="flex items-center gap-2">
                    <select
                        className="px-3! pr-5.5! py-2! text-sm! text-gray-900! bg-white! border! border-gray-300! rounded-md! shadow-none! min-w-[220px] focus:outline-none! focus:border-blue-500! focus:ring-2! focus:ring-blue-500/20! focus:shadow-none! hover:border-gray-400!"
                        onChange={(e) => handleChangeBulkType(e.target.value)}
                        value={bulkSubmitData.type || ""}
                    >
                        <option value="" disabled>Bulk Actions</option>
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

                {/* Search */}
                <SearchInput
                    placeholder="Search keywords..."
                    value={searchQuery}
                    onChange={(e) => setSearch(e.target.value)}
                    onClear={() => setSearch('')}
                />

                {/* Controls group */}
                <div className="flex items-center gap-2">
                    <button
                        className={`px-4 py-2 text-sm border rounded-md transition-colors font-medium whitespace-nowrap cursor-pointer ${
                            rename.formEdited
                                ? 'bg-blue-600 text-white border-blue-600 hover:bg-blue-700'
                                : 'bg-white text-blue-600 border-blue-300 hover:bg-blue-50 hover:border-blue-500'
                        }`}
                        onClick={() => setRename({ formEdited: !rename.formEdited })}
                    >
                        {rename.formEdited ? 'Disable Edit Mode' : 'Enable Edit Mode'}
                    </button>

                </div>

                <div className="flex items-center gap-1.5">
                    <label className="text-sm text-gray-500 whitespace-nowrap">Per page:</label>
                    <input
                        type="number"
                        className="w-16 px-2! py-1.5! text-sm! text-gray-900! bg-white! border! border-gray-300! rounded-md! shadow-none! focus:outline-none! focus:border-blue-500! focus:ring-2! focus:ring-blue-500/20! focus:shadow-none! hover:border-gray-400!"
                        value={options.media_per_page as number | string}
                        onChange={(event) => { localStorage.setItem('mlt_media_per_page', event.target.value); setOptions({ media_per_page: event.target.value }); }}
                        onBlur={() => setMediaData({ postQuery: { ...mediaData.postQuery, media_per_page: parseInt(String(options.media_per_page || 20), 10), paged: 1 } })}
                    />
                </div>

            </div>
            <BulkRenameModal />
        </header>
    );
}

export default RenamerMainHeader;
