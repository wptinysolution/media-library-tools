import React, { useState, useEffect } from "react";
import { useStateValue } from "@/js/Utils/StateProvider";
import { rescanDir, truncateUnlistedFile } from "@/js/Utils/Data";
import Axios from 'axios';
import * as Types from "@/js/Utils/actionType";

function DirectoryModal() {
    const [stateValue, dispatch] = useStateValue();
    const [dirListExist, setDirListExist] = useState([]);
    const [scanRubbishDirList, setScanRubbishDirList] = useState([]);
    const [scanRubbishDirLoading, setScanRubbishDirLoading] = useState(false);
    const [progressBar, setProgressBar] = useState(0);
    const [progressTotal, setProgressTotal] = useState(0);
    const [buttonSpain, setButtonSpain] = useState(null);
    const [instantDeletion, setInstantDeletion] = useState('not-instant');

    const [skip, setSkip] = useState([]);

    const handleDirModalCancel = () => {
        dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                isDirModalOpen: false,
            },
        });
    };

    const handleDirRescan = async (dir = "all") => {
        setScanRubbishDirLoading(true);
        try {
            const dirList = await rescanDir({ dir });
            setScanRubbishDirList(dirList.data.thedirlist);
        } finally {
            setScanRubbishDirLoading(false);
        }
    };

    const exclude_from_bulk_scan = (dir = "") => {
        setSkip([
            ...skip,
            dir
        ]);
    };

    const processDirectory = () => {
        var params = new URLSearchParams();
        params.append('action', 'immediately_search_rubbish_file');
        params.append('nonce', tsmltParams.tsmlt_wpnonce);
        params.append('instantDeletion', instantDeletion);
        skip.forEach((value) => {
            params.append('skip[]', value);
        });
        console.log('skip', skip);
        Axios
            .post(tsmltParams.ajaxUrl, params)
            .then((response) => {
                if (response && response.data) {
                    const { dirList, dirStatusList } = response.data.data;
                    setScanRubbishDirList(dirStatusList);
                    const list = Object.entries(dirList).map(([key]) => key);
                    const percent = Math.floor((100 * (progressTotal - list.length)) / progressTotal);
                    setProgressBar(percent);
                    setDirListExist(list);
                    console.log('percent', percent);
                    if (percent >= 100) {
                        window.location.reload();
                    }
                } else {
                    console.error("Invalid response structure:", response);
                }
            })
            .catch((error) => {
                console.error("Request failed:", error);
                console.log("Retrying directory processing in 1 second...");
                setTimeout(() => {
                    processDirectory();
                }, 2000);
            });
    };

    const handleDirScanManually = () => {
        setButtonSpain("bulkScan");
        processDirectory();
    };

    useEffect(() => {
        setScanRubbishDirList(stateValue.generalData.scanRubbishDirList);
    }, [stateValue.generalData.scanRubbishDirList]);

    useEffect(() => {
        const list = Object.entries(scanRubbishDirList).map(([key]) => key);
        setProgressTotal(list.length);
    }, [scanRubbishDirList]);

    useEffect(() => {
        if (dirListExist.length > 0) {
            processDirectory();
        }
    }, [scanRubbishDirList]);

    useEffect(() => {
        const handleEsc = (e) => {
            if (e.key === 'Escape' && stateValue.generalData.isDirModalOpen) {
                handleDirModalCancel();
            }
        };
        document.addEventListener('keydown', handleEsc);
        return () => document.removeEventListener('keydown', handleEsc);
    }, [stateValue.generalData.isDirModalOpen]);

    if (!stateValue.generalData.isDirModalOpen) return null;

    const dirEntries = Object.entries(scanRubbishDirList);

    return (
        <div className="fixed inset-0 z-[1000] flex items-center justify-center">
            <div className="absolute inset-0 bg-black/45" onClick={handleDirModalCancel} />
            <div className="relative bg-white rounded-lg shadow-xl w-full max-w-[950px] mx-4">
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h3 className="text-lg font-semibold text-gray-900 m-0!">Directory List</h3>
                    <button type="button" className="p-1 text-gray-400 hover:text-gray-600 cursor-pointer transition-colors" onClick={handleDirModalCancel}>
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Body */}
                <hr className="border-gray-200" />
                <div className="px-4 py-4 h-[450px] overflow-y-auto border-b border-gray-100">
                    {scanRubbishDirLoading ? (
                        <div className="flex items-center justify-center h-full">
                            <svg className="w-8 h-8 animate-spin text-blue-600" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                        </div>
                    ) : dirEntries.length === 0 ? (
                        <h5 className="text-[15px] font-semibold text-red-600 text-center py-8">
                            Directory will search in the next schedule. Please be patient
                        </h5>
                    ) : (
                        <div className="space-y-2">
                            {dirEntries.map(([key, item]) => {
                                const skippedItem = skip.includes(key);
                                return (
                                    <div key={key} className={`flex items-center justify-between py-3 px-3 border-b border-gray-100 last:border-0 ${skippedItem ? 'opacity-20' : ''}`}>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-medium text-gray-900 truncate">{key}</p>
                                            <p className="text-xs text-gray-500 mt-0.5">
                                                {item.total_items === 0
                                                    ? "This directory will be scanned again according to the schedule."
                                                    : <span className="text-blue-600">Scanned {item.counted} items of {item.total_items} items</span>
                                                }
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2 flex-shrink-0 ml-4">
                                            <button
                                                type="button"
                                                className="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700 cursor-pointer transition-colors"
                                                onClick={() => exclude_from_bulk_scan(key)}
                                            >
                                                Exclude from Bulk Scan
                                            </button>
                                            <button
                                                type="button"
                                                className="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700 cursor-pointer transition-colors"
                                                onClick={() => handleDirRescan(key)}
                                            >
                                                Re-Execute in Schedule
                                            </button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>

                {/* Progress */}
                {progressBar > 0 && (
                    <div className="px-6 py-3">
                        <h5 className="text-base font-semibold text-gray-900 mb-2">Directory Scanning Progress:</h5>
                        <div className="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
                            <div
                                className="bg-blue-600 h-full rounded-full transition-all duration-300 flex items-center justify-center text-white text-[10px] font-medium"
                                style={{ width: `${progressBar}%` }}
                            >
                                {progressBar}%
                            </div>
                        </div>
                    </div>
                )}

                {/* Footer */}
                <div className="px-6 py-4 border-t border-gray-200">
                    {tsmltParams.hasExtended && (
                        <div className="mb-3">
                            <label className="inline-flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                    onChange={(event) => setInstantDeletion(event.target.checked ? 'instant' : 'not-instant')}
                                />
                                <span className="text-sm text-gray-900">Rubbish File Instant Deletion?</span>
                            </label>
                            <p className="text-xs text-red-600 mt-1 text-left">
                                Enabling this will delete files immediately during a bulk scan. Use with caution, as it may result in permanent loss of files without confirmation.
                            </p>
                            <hr className="border-gray-200 my-3" />
                        </div>
                    )}
                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            className="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors"
                            onClick={async () => {
                                await handleDirRescan("all");
                                await truncateUnlistedFile();
                            }}
                        >
                            Delete Old History
                        </button>
                        <button
                            type="button"
                            className={`px-5 py-2 text-sm font-medium rounded-md cursor-pointer transition-colors inline-flex items-center gap-2 ${
                                buttonSpain === "bulkScan"
                                    ? 'bg-blue-600 text-white hover:bg-blue-700'
                                    : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'
                            }`}
                            onClick={handleDirScanManually}
                        >
                            Bulk Scan Immediately
                            {buttonSpain === "bulkScan" && (
                                <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                            )}
                        </button>
                        <button
                            type="button"
                            className="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors"
                            onClick={() => handleDirRescan("all")}
                        >
                            Re-Scan Directory
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default DirectoryModal;
