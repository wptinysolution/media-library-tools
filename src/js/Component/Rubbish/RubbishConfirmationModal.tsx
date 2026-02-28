import { useState, useEffect } from "react";
import { useStore } from "@/js/Utils/store";
import { rubbishBulkDeleteApi, singleIgnoreApi, singleShowApi } from "@/js/Utils/Data";
import Modal from "@/js/Component/Common/Modal";
import ProgressBar from "@/js/Component/Common/ProgressBar";
import type { BulkRubbishData } from "@/js/Utils/store";

function RubbishConfirmationModal() {
    const { rubbishMedia, setRubbishMedia, bulkRubbishData, setBulkRubbishData } = useStore();

    const [buttonDisabled, setButtonDisabled] = useState(true);
    const [theFile, setTheFile] = useState('');
    const [total, setTotal] = useState(0);

    const rubbishBulkActionLoop = async (prams: BulkRubbishData): Promise<{ status: number } | undefined> => {
        const files = [...prams.files];
        const progressTotal = bulkRubbishData.progressTotal;

        if ('delete' === bulkRubbishData.type && tsmltParams?.proVersion) {
            setBulkRubbishData({ progressBar: 50 });
            const response = await rubbishBulkDeleteApi({ file_paths: files }) as { status: number };
            setBulkRubbishData({ progressBar: 100 });
            return response;
        }

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            let response: { status: number } | undefined;

            setBulkRubbishData({
                progressBar: Math.floor(100 * i / progressTotal),
            });
            setTotal(files.length - i);
            setTheFile(file.path);

            if ('ignore' === bulkRubbishData.type) {
                response = await singleIgnoreApi({ file_path: file.path }) as { status: number };
            } else if ('show' === bulkRubbishData.type) {
                response = await singleShowApi({ file_path: file.path }) as { status: number };
            }

            if (!prams.ids.length || !response?.status) break;
        }

        return { status: 200 };
    };

    const handleBulkModalOk = async () => {
        setButtonDisabled(true);
        const response = await rubbishBulkActionLoop(bulkRubbishData);
        if (200 === response?.status) {
            setTimeout(() => {
                setBulkRubbishData({
                    bulkChecked: false,
                    progressBar: false,
                    progressTotal: 0,
                    isModalOpen: false,
                    files: [],
                    ids: [],
                });
            }, 1000);

            setRubbishMedia({
                postQuery: {
                    ...rubbishMedia.postQuery,
                    isQueryUpdate: !rubbishMedia.postQuery.isQueryUpdate,
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

                {typeof bulkRubbishData.progressBar === 'number' && bulkRubbishData.progressBar > 0 && (
                    <div className="mb-3">
                        <ProgressBar percent={bulkRubbishData.progressBar as number} />
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
                            <svg className="w-4 h-4 animate-spin shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24">
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
