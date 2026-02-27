import React, { useState, useEffect } from 'react';
import { useStore } from '@/js/Utils/store';
import { getMedia, singleUpDateApi } from '@/js/Utils/Data';
import Modal from '@/js/Component/Common/Modal';
import ProgressBar from '@/js/Component/Common/ProgressBar';
import TextInput from '@/js/Component/Common/TextInput';

function BulkModal() {
    const { mediaData, setMediaData, bulkSubmitData, setBulkSubmitData } = useStore();
    const [IsButtonDisabled, setIsButtonDisabled] = useState(true);

    const balkModalDataChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const data = {
            ...bulkSubmitData.data,
            [event.target.name]: event.target.value
        };
        setBulkSubmitData({ data });
        setIsButtonDisabled(!bulkSubmitData.ids.length || !data.file_name.length);
    };

    const renameIdsRecursively = async (prams: typeof bulkSubmitData): Promise<{ status: number } | undefined> => {
        setBulkSubmitData({
            progressBar: Math.floor(100 * (bulkSubmitData.progressTotal - prams.ids.length) / bulkSubmitData.progressTotal),
        });
        if (prams.ids.length === 0) {
            return;
        }
        const id = prams.ids[0];
        let newName: string;

        if (bulkSubmitData.type === 'bulkRenameByPostTitle') {
            newName = 'bulkRenameByPostTitle';
        } else if (bulkSubmitData.type === 'bulkRenameBySKU') {
            newName = 'bulkRenameBySKU';
        } else {
            newName = prams.data.file_name;
        }
        const response = await singleUpDateApi({ newname: newName, ID: id });
        await new Promise(resolve => setTimeout(resolve, 300));

        if (prams.ids.length && response.status) {
            await renameIdsRecursively({ ...prams, ids: prams.ids.slice(1) });
        }
        return response;
    };

    const handleBulkModalOk = async () => {
        setIsButtonDisabled(true);
        const response = await renameIdsRecursively(bulkSubmitData);
        if (200 === response?.status) {
            setTimeout(() => {
                setBulkSubmitData({ isModalOpen: false, progressBar: 0 });
            }, 1000);
            const res = await getMedia(mediaData.postQuery);
            setMediaData({ ...res, isLoading: false });
            setIsButtonDisabled(false);
        }
    };

    const handleBulkModalCancel = () => {
        setBulkSubmitData({ isModalOpen: false, progressBar: 0 });
    };

    const isTheButtonDisabled = () => {
        if (['bulkRenameBySKU', 'bulkRenameByPostTitle'].includes(bulkSubmitData.type)) {
            setIsButtonDisabled(false);
        } else {
            const isDisable = !bulkSubmitData.ids.length || !bulkSubmitData.data.file_name.length;
            setIsButtonDisabled(isDisable);
        }
    };

    useEffect(() => {
        if (bulkSubmitData.isModalOpen) {
            isTheButtonDisabled();
        }
    }, [bulkSubmitData.isModalOpen]);

    return (
        <Modal
            isOpen={bulkSubmitData.isModalOpen}
            onClose={handleBulkModalCancel}
            title="Bulk Rename"
            maxWidth="max-w-[520px]"
            closeOnBackdrop={false}
            footer={
                <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200">
                    <button
                        type="button"
                        className="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        onClick={handleBulkModalCancel}
                        disabled={IsButtonDisabled}
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        className="px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        onClick={handleBulkModalOk}
                        disabled={IsButtonDisabled}
                    >
                        Rename
                    </button>
                </div>
            }
        >
            <div className="px-6 py-5">
                {bulkSubmitData.type === 'bulkRenameByPostTitle' ? (
                    <h5 className="text-base! font-semibold text-gray-900 m-0!">
                        Are You Sure Bulk Rename Based on Associated Post Title?
                    </h5>
                ) : bulkSubmitData.type === 'bulkRenameBySKU' ? (
                    <h5 className="text-base! font-semibold text-gray-900 m-0!">
                        Are You Sure Bulk Rename Based on Product SKU?
                    </h5>
                ) : (
                    <>
                        <h5 className="text-base! font-semibold text-gray-900 m-0!">File name</h5>
                        <p className="text-sm text-red-600 mb-3">Prefix and suffix will not apply here.</p>
                        <TextInput
                            onChange={balkModalDataChange}
                            name="file_name"
                            value={bulkSubmitData.data.file_name}
                            placeholder="File Name"
                            className="mb-4"
                        />
                        {!bulkSubmitData.ids.length && (
                            <p className="text-sm text-red-600 mb-2">No Item selected for rename</p>
                        )}
                        {!bulkSubmitData.data.file_name.length && (
                            <p className="text-sm text-red-600 mb-2">Empty value not allowed.</p>
                        )}
                    </>
                )}
                <hr className="border-gray-200 my-4" />
                {bulkSubmitData.progressBar >= 0 && (
                    <>
                        <h5 className="text-base! font-semibold text-gray-900 mb-2">Progress:</h5>
                        <ProgressBar percent={bulkSubmitData.progressBar as number} />
                    </>
                )}
                <hr className="border-gray-200 mt-4" />
            </div>
        </Modal>
    );
}

export default BulkModal;
