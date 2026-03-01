import { useEffect, useState } from "react";
import { defaultBulkSubmitData } from '@/js/Utils/UtilData';
import { useStore } from "@/js/Utils/store";
import { useSearchDebounce } from "@/js/Utils/Hooks";
import * as Types from "@/js/Utils/actionType";
import { notifications } from "@/js/Utils/Data";
import SearchInput from "@/js/Component/Common/SearchInput";
import Modal from "@/js/Component/Common/Modal";

const bulkOptions = [
    { value: 'bulkedit', label: 'Bulk Edit' },
    { value: 'bulkEditPostTitle', label: 'Bulk Edit by Post Title' },
    { value: 'csv_export', label: 'Export CSV' },
    { value: 'inherit', label: 'Restore' },
    { value: 'searchUses', label: 'Search Uses' },
    { value: 'delete', label: 'Delete Permanently' },
    { value: 'trash', label: 'Move to Trash' },

];

function TheHeader() {
    const {
        mediaData, setMediaData,
        generalData, setGeneralData,
        singleMedia, setSingleMedia,
        bulkSubmitData, setBulkSubmitData,setBulkExport,
        setSaveType,
    } = useStore();

    const [search, searchQuery, setSearch] = useSearchDebounce();

    const [confirmAction, setConfirmAction] = useState<'trash' | 'delete' | 'searchUses' | null>(null);

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
            case 'delete':
            case 'searchUses':
                setConfirmAction(bulkSubmitData.type as 'trash' | 'delete' | 'searchUses');
                break;
            case 'inherit':
            case 'update':
                setSaveType(Types.BULK_SUBMIT);
                break;
            case 'bulkedit':
            case 'bulkEditPostTitle':
                setBulkSubmitData({ isModalOpen: true, progressBar: 0 });
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

    };

    const postQuery = mediaData.postQuery;

    useEffect(() => {
        upDateQuery();
    }, [search]);

    const filteredBulkOptions = postQuery.filtering && 'trash' === postQuery.status
        ? bulkOptions.filter(item => 'trash' !== item.value)
        : bulkOptions.filter(item => 'inherit' !== item.value);

    return (
        <>
        <header className="bg-white border-b border-gray-200 px-3 py-3 shadow-sm">
            <div className="flex flex-wrap items-center gap-2">

                {/* Bulk Actions group */}
                <div className="flex items-center gap-2">
                    <select
                        className="px-3! py-2! text-sm! text-gray-900! bg-white! border! border-gray-300! rounded-md! shadow-none! min-w-50 focus:outline-none! focus:border-blue-500! focus:ring-2! focus:ring-blue-500/20! focus:shadow-none! hover:border-gray-400!"
                        onChange={(e) => handleChangeBulkType(e.target.value)}
                        value={bulkSubmitData.type || ""}
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

                {/* Filter group */}
                {/*<div className="flex items-center gap-2 flex-wrap">*/}
                    <select
                        className="pl-3! pr-5! py-2! text-sm! text-gray-900! bg-white! border! border-gray-300! rounded-md! shadow-none! focus:outline-none! focus:border-blue-500! focus:ring-2! focus:ring-blue-500/20! focus:shadow-none! hover:border-gray-400!"
                        onChange={(e) => handleSelectChange(e.target.value || null, 'status')}
                        defaultValue={mediaData.postQuery.status || ""}
                    >
                        <option value="">All Status</option>
                        <option value="trash">Trash</option>
                    </select>

                    <select
                        className="px-3! pr-5! py-2! text-sm! text-gray-900! bg-white! border! border-gray-300! rounded-md! shadow-none! focus:outline-none! focus:border-blue-500! focus:ring-2! focus:ring-blue-500/20! focus:shadow-none! hover:border-gray-400!"
                        onChange={(e) => handleSelectChange(e.target.value || null, 'date')}
                        defaultValue={mediaData.postQuery.date || ""}
                    >
                        <option value="">All Dates</option>
                        {generalData?.dateList?.map(date => (
                            <option key={date.value} value={date.value}>{date.label}</option>
                        ))}
                    </select>

                    <select
                        className="px-3! py-2! pr-5! text-sm! text-gray-900! bg-white! border! border-gray-300! rounded-md! shadow-none! focus:outline-none! focus:border-blue-500! focus:ring-2! focus:ring-blue-500/20! focus:shadow-none! hover:border-gray-400!"
                        onChange={(e) => handleSelectChange(e.target.value || null, 'categories')}
                        defaultValue={mediaData.postQuery.categories || ""}
                    >
                        <option value="">All Groups</option>
                        {generalData.termsList?.map(term => (
                            <option key={term.value} value={term.value}>{term.label}</option>
                        ))}
                    </select>

                    <SearchInput
                        placeholder="Search keywords..."
                        value={searchQuery}
                        onChange={(e) => setSearch(e.target.value)}
                        onClear={() => setSearch('')}
                    />
                {/*</div>*/}

                {/* Controls group */}
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


            </div>
        </header>

        <Modal
            isOpen={!!confirmAction}
            onClose={() => setConfirmAction(null)}
            title={confirmAction === 'delete' ? 'Delete Permanently?' : confirmAction === 'trash' ? 'Move to Trash?' : 'Search Uses?'}
            maxWidth="max-w-md"
            closeOnBackdrop={false}
            footer={
                <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200">
                    <button
                        type="button"
                        className="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors"
                        onClick={() => setConfirmAction(null)}
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        className={`px-5 py-2 text-sm font-medium text-white rounded-md cursor-pointer transition-colors ${
                            confirmAction === 'delete'
                                ? 'bg-red-600 hover:bg-red-700'
                                : confirmAction === 'trash'
                                ? 'bg-orange-500 hover:bg-orange-600'
                                : 'bg-blue-600 hover:bg-blue-700'
                        }`}
                        onClick={() => {
                            setSaveType(Types.BULK_SUBMIT);
                            setConfirmAction(null);
                        }}
                    >
                        {confirmAction === 'delete' ? 'Yes, Delete' : confirmAction === 'trash' ? 'Yes, Move to Trash' : 'Yes, Search Uses'}
                    </button>
                </div>
            }
        >
            <div className="px-6 py-5">
                {confirmAction === 'delete' ? (
                    <p className="text-sm! text-gray-600 m-0!">
                        You are about to <strong className="text-red-600">permanently delete</strong> {bulkSubmitData.ids.length} item{bulkSubmitData.ids.length !== 1 ? 's' : ''}. This action <strong>cannot be undone</strong>.
                    </p>
                ) : confirmAction === 'trash' ? (
                    <p className="text-sm! text-gray-600 m-0!">
                        You are about to move <strong>{bulkSubmitData.ids.length} item{bulkSubmitData.ids.length !== 1 ? 's' : ''}</strong> to the trash. You can restore them later.
                    </p>
                ) : (
                    <p className="text-sm! text-gray-600 m-0!">
                        This will search Attached Post for <strong>{bulkSubmitData.ids.length} item{bulkSubmitData.ids.length !== 1 ? 's' : ''}</strong>. Do you want to continue?
                    </p>
                )}
            </div>
        </Modal>
        </>
    );
}

export default TheHeader;
