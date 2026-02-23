import React, { useState, useEffect } from "react";

import { useStore } from "@/js/Utils/store";

import { rubbishBulkDeleteApi, singleDeleteApi, singleIgnoreApi, singleShowApi } from "@/js/Utils/Data";

import Modal from "@/js/Component/Common/Modal";

import ProgressBar from "@/js/Component/Common/ProgressBar";

function RubbishConfirmationModal() {

    const { rubbishMedia, setRubbishMedia, bulkRubbishData, setBulkRubbishData } = useStore();

    const [buttonDisabled, setButtonDisabled] = useState(true);

    const [theFile, setTheFile] = useState('');

    const [total, setTotal] = useState(0);

    const rubbishBulkActionRecursively = async (prams) => {
        let response = {};
        setBulkRubbishData({
            progressBar: Math.floor(100 * (bulkRubbishData.progressTotal - prams.files.length) / bulkRubbishData.progressTotal),
        });
        setTotal(prams.files.length);
        if (prams.files.length === 0) {
            response.status = 200;
            return response;
        }
        const file = prams.files[0];

        if ('ignore' === bulkRubbishData.type) {
            response = await singleIgnoreApi({ file_path: file.path });
        } else if (tsmltParams?.proVersion && 'delete' === bulkRubbishData.type) {
            response = await rubbishBulkDeleteApi({ file_paths: prams.files });
            prams.files = [];
        } else if ('show' === bulkRubbishData.type) {
            response = await singleShowApi({ file_path: file.path });
        }
        setTheFile(prevState => file.path);

        if (prams.ids.length && response?.status) {
            return await rubbishBulkActionRecursively({ ...prams, files: prams.files.slice(1) });
        }
        return response;
    };

    const handleBulkModalOk = async () => {
        setButtonDisabled(true);
        const response = await rubbishBulkActionRecursively(bulkRubbishData);
        if (200 === response?.status) {
            setTimeout(() => {
                setBulkRubbishData({
                    bulkChecked: false,
                    progressBar: false,
                    progressTotal: 0,
                    isModalOpen: false,
                    files: [],
                    ids: []
                });
            }, 1000);

            setRubbishMedia({
                postQuery: {
                    ...rubbishMedia.postQuery,
                    isQueryUpdate: !rubbishMedia.postQuery.isQueryUpdate
                }
            });
        }
    };

    const handleBulkModalCancel = () => {
        setBulkRubbishData({ isModalOpen: false });
    };

    useEffect(() => {
        if (bulkRubbishData.isModalOpen) {
            setButtonDisabled(!bulkRubbishData.ids.length);
        }
    }, [bulkRubbishData.isModalOpen]);

    return (
        <Modal
            isOpen={bulkRubbishData.isModalOpen}
            onClose={handleBulkModalCancel}
            title={`Bulk ${'ignore' === bulkRubbishData.type ? 'Ignore' : 'Delete'} Action`}
            maxWidth="max-w-[520px]"
            closeOnBackdrop={false}
            footer={
                <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200">
                    <button
                        type="button"
                        className="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        onClick={handleBulkModalCancel}
                        disabled={buttonDisabled}
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        className="px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        onClick={handleBulkModalOk}
                        disabled={buttonDisabled}
                    >
                        {'ignore' === bulkRubbishData.type ? 'Ignore' : 'Delete'}
                    </button>
                </div>
            }
        >
            <div className="px-6 py-5">
                <hr className="border-gray-200 mb-4" />
                <h5 className="text-base font-semibold text-gray-900 mb-4">
                    {!buttonDisabled ? (
                        <>
                            Are You Confirm {'ignore' === bulkRubbishData.type ? 'To Ignore' : 'show' === bulkRubbishData.type ? 'To Make Deletable' : 'To Delete'}?
                        </>
                    ) : (
                        <span className="inline-flex items-center gap-2 flex-wrap">
                            Remaining {`- ${total}`}
                        </span>
                    )}
                </h5>

                {bulkRubbishData.progressBar >= 0 && (
                    <div className="mb-3">
                        <ProgressBar percent={bulkRubbishData.progressBar} />
                    </div>
                )}
                {!bulkRubbishData.ids.length && (
                    <p className="text-sm text-red-600">
                        No Item selected {'ignore' === bulkRubbishData.type ? 'To Ignore' : 'To Delete'}
                    </p>
                )}
                <div className="flex items-start gap-2 overflow-hidden max-w-full text-sm text-gray-600">
                    {total && theFile.length > 0 ? (
                        <>
                            <svg className="w-4 h-4 animate-spin flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            <span className="break-all">{theFile}</span>
                        </>
                    ) : ''}
                </div>
                <hr className="border-gray-200 mt-4" />
            </div>
        </Modal>
    );
}
export default RubbishConfirmationModal;
