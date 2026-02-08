import React, { useState, useEffect } from "react";

import { useStateValue } from "@/js/Utils/StateProvider";

import * as Types from "@/js/Utils/actionType";

import { rubbishBulkDeleteApi, singleDeleteApi, singleIgnoreApi, singleShowApi } from "@/js/Utils/Data";

function RubbishConfirmationModal() {

    const [stateValue, dispatch] = useStateValue();

    const [buttonDisabled, setButtonDisabled] = useState(true);

    const [theFile, setTheFile] = useState('');

    const [total, setTotal] = useState(0);

    const rubbishBulkActionRecursively = async (prams) => {
        let response = {};
        dispatch({
            type: Types.BALK_RUBBISH,
            bulkRubbishData: {
                ...stateValue.bulkRubbishData,
                progressBar: Math.floor(100 * (stateValue.bulkRubbishData.progressTotal - prams.files.length) / stateValue.bulkRubbishData.progressTotal),
            },
        });
        setTotal(prams.files.length);
        if (prams.files.length === 0) {
            response.status = 200;
            return response;
        }
        const file = prams.files[0];

        if ('ignore' === stateValue.bulkRubbishData.type) {
            response = await singleIgnoreApi({ file_path: file.path });
        } else if (tsmltParams?.proVersion && 'delete' === stateValue.bulkRubbishData.type) {
            response = await rubbishBulkDeleteApi({ file_paths: prams.files });
            prams.files = [];
        } else if ('show' === stateValue.bulkRubbishData.type) {
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
        const response = await rubbishBulkActionRecursively(stateValue.bulkRubbishData);
        if (200 === response?.status) {
            setTimeout(() => {
                dispatch({
                    type: Types.BALK_RUBBISH,
                    bulkRubbishData: {
                        ...stateValue.bulkRubbishData,
                        bulkChecked: false,
                        progressBar: false,
                        progressTotal: 0,
                        isModalOpen: false,
                        files: [],
                        ids: []
                    },
                });
            }, 1000);

            await dispatch({
                type: Types.RUBBISH_MEDIA,
                rubbishMedia: {
                    ...stateValue.rubbishMedia,
                    postQuery: {
                        ...stateValue.rubbishMedia.postQuery,
                        isQueryUpdate: !stateValue.rubbishMedia.postQuery.isQueryUpdate
                    }
                },
            });
        }
    };

    const handleBulkModalCancel = () => {
        dispatch({
            type: Types.BALK_RUBBISH,
            bulkRubbishData: {
                ...stateValue.bulkRubbishData,
                isModalOpen: false,
            },
        });
    };

    useEffect(() => {
        if (stateValue.bulkRubbishData.isModalOpen) {
            setButtonDisabled(!stateValue.bulkRubbishData.ids.length);
        }
    }, [stateValue.bulkRubbishData.isModalOpen]);

    useEffect(() => {
        const handleEsc = (e) => {
            if (e.key === 'Escape' && stateValue.bulkRubbishData.isModalOpen) {
                handleBulkModalCancel();
            }
        };
        document.addEventListener('keydown', handleEsc);
        return () => document.removeEventListener('keydown', handleEsc);
    }, [stateValue.bulkRubbishData.isModalOpen]);

    if (!stateValue.bulkRubbishData.isModalOpen) return null;

    return (
        <div className="fixed inset-0 z-[1000] flex items-center justify-center">
            <div className="absolute inset-0 bg-black/45" />
            <div className="relative bg-white rounded-lg shadow-xl w-full max-w-[520px] mx-4">
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h3 className="text-lg font-semibold text-gray-900 m-0!">
                        Bulk {'ignore' === stateValue.bulkRubbishData.type ? 'Ignore' : 'Delete'} Action
                    </h3>
                    <button type="button" className="p-1 text-gray-400 hover:text-gray-600 cursor-pointer transition-colors" onClick={handleBulkModalCancel}>
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Body */}
                <div className="px-6 py-5">
                    <hr className="border-gray-200 mb-4" />
                    <h5 className="text-base font-semibold text-gray-900 mb-4">
                        {!buttonDisabled ? (
                            <>
                                Are You Confirm {'ignore' === stateValue.bulkRubbishData.type ? 'To Ignore' : 'show' === stateValue.bulkRubbishData.type ? 'To Make Deletable' : 'To Delete'}?
                            </>
                        ) : (
                            <span className="inline-flex items-center gap-2 flex-wrap">
                                Remaining {`- ${total}`}
                            </span>
                        )}
                    </h5>

                    {stateValue.bulkRubbishData.progressBar >= 0 && (
                        <div className="w-full bg-gray-200 rounded-full h-4 overflow-hidden mb-3">
                            <div
                                className="bg-blue-600 h-full rounded-full transition-all duration-300 flex items-center justify-center text-white text-[10px] font-medium"
                                style={{ width: `${stateValue.bulkRubbishData.progressBar}%` }}
                            >
                                {stateValue.bulkRubbishData.progressBar}%
                            </div>
                        </div>
                    )}
                    {!stateValue.bulkRubbishData.ids.length && (
                        <p className="text-sm text-red-600">
                            No Item selected {'ignore' === stateValue.bulkRubbishData.type ? 'To Ignore' : 'To Delete'}
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

                {/* Footer */}
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
                        {'ignore' === stateValue.bulkRubbishData.type ? 'Ignore' : 'Delete'}
                    </button>
                </div>
            </div>
        </div>
    );
}
export default RubbishConfirmationModal;
