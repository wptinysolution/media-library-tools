import React, { useState, useEffect } from 'react';
import { useStore } from '@/js/Utils/store';
import * as Types from '@/js/Utils/actionType';
import { getMedia, singleUpDateApi } from '@/js/Utils/Data';
import Modal from '@/js/Component/Common/Modal';
import BulkEditForm from '@/js/Component/ListTable/BulkEditForm';
import BulkAssignForm from '@/js/Component/ListTable/BulkAssignForm';

function BulkModal() {
    const { mediaData, setMediaData, bulkSubmitData, setBulkSubmitData, setSaveType } = useStore();
    const [IsButtonDisabled, setIsButtonDisabled] = useState(true);

    const isOpen = bulkSubmitData.isModalOpen;
    const isBulkAssign = 'bulkEditPostTitle' === bulkSubmitData.type;

    useEffect(() => {
        if (isOpen) {
            isTheButtonDisabled();
        }
    }, [isOpen]);

    const balkModalDataChange = (event: React.ChangeEvent<HTMLTextAreaElement>) => {
        const data = {
            ...bulkSubmitData.data,
            [event.target.name]: event.target.value
        };
        setBulkSubmitData({ data });
        const changeDetected = Object.values(data).some(value => value !== '');
        setIsButtonDisabled(!bulkSubmitData.ids.length || !changeDetected);
    };

    const isTheButtonDisabled = () => {
        let changeDetected = false;
        if ('bulkEditPostTitle' === bulkSubmitData.type) {
            changeDetected = !!bulkSubmitData.will_attached_post_title.length;
        } else {
            changeDetected = Object.values(bulkSubmitData.data).some(value => value !== '');
        }
        setIsButtonDisabled(!bulkSubmitData.ids.length || !changeDetected);
    };

    const addDataRecursively = async (prams: typeof bulkSubmitData): Promise<{ status: number } | undefined> => {
        setBulkSubmitData({
            progressBar: Math.floor(100 * (bulkSubmitData.progressTotal - prams.ids.length) / bulkSubmitData.progressTotal),
        });
        if (prams.ids.length === 0) {
            return;
        }
        const id = prams.ids[0];
        const response = await singleUpDateApi({ bulkEditPostTitle: bulkSubmitData.will_attached_post_title, ID: id });
        if (prams.ids.length && response.status) {
            await addDataRecursively({ ...prams, ids: prams.ids.slice(1) });
        }
        return response;
    };

    const mediaHandleBulkModalOk = async () => {
        setIsButtonDisabled(true);
        const response = await addDataRecursively(bulkSubmitData);
        if (200 === response?.status) {
            setTimeout(() => {
                setBulkSubmitData({ isModalOpen: false });
            }, 1000);
            const res = await getMedia(mediaData.postQuery as Record<string, unknown>);
            setMediaData({ ...res, isLoading: false });
            setIsButtonDisabled(false);
        }
    };

    const handleBulkModalOk = () => {
        if ('bulkEditPostTitle' === bulkSubmitData.type) {
            mediaHandleBulkModalOk();
        } else {
            setSaveType(Types.BULK_SUBMIT);
        }
    };

    const handleBulkModalCancel = () => {
        setBulkSubmitData({ isModalOpen: false });
    };

    const onToggleCheckbox = (value: string, checked: boolean) => {
        const current = bulkSubmitData.will_attached_post_title || [];
        const list = checked ? [...current, value] : current.filter(v => v !== value);
        setBulkSubmitData({ will_attached_post_title: list });
        setIsButtonDisabled(!list.length);
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={handleBulkModalCancel}
            title={isBulkAssign ? 'Bulk Assign' : 'Bulk Edit'}
            footer={
                <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200">
                    <button
                        type="button"
                        className="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors"
                        onClick={handleBulkModalCancel}
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        className={`px-5 py-2 text-sm font-medium text-white rounded-md transition-colors ${
                            IsButtonDisabled
                                ? 'bg-blue-400 cursor-not-allowed'
                                : 'bg-blue-600 hover:bg-blue-700 cursor-pointer'
                        }`}
                        disabled={IsButtonDisabled}
                        onClick={handleBulkModalOk}
                    >
                        Done
                    </button>
                </div>
            }
        >
            <div className="px-6 py-5">
                {isBulkAssign ? (
                    <BulkAssignForm
                        selectedValues={bulkSubmitData.will_attached_post_title}
                        onToggle={onToggleCheckbox}
                        progressBar={bulkSubmitData.progressBar}
                    />
                ) : (
                    <BulkEditForm
                        data={bulkSubmitData.data}
                        onChange={balkModalDataChange}
                    />
                )}
            </div>
        </Modal>
    );
}

export default BulkModal;
