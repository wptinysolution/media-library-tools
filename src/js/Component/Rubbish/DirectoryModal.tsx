import { useState, useEffect, useRef } from "react";
import { useStore } from "@/js/Utils/store";
import { rescanDir, truncateUnlistedFile } from "@/js/Utils/Data";
import Axios from 'axios';
import Modal from "@/js/Component/Common/Modal";
import ProgressBar from "@/js/Component/Common/ProgressBar";
import DirectoryList, { trimPath } from "@/js/Component/Rubbish/DirectoryList";

const MAX_RETRIES = 3;

interface DirStatusItem {
    total_items: number;
    counted: number;
}

function DirectoryModal() {
    const { generalData, setGeneralData } = useStore();
    const [dirListExist, setDirListExist] = useState<string[]>([]);
    const [scanRubbishDirList, setScanRubbishDirList] = useState<Record<string, DirStatusItem>>({});
    const [scanRubbishDirLoading, setScanRubbishDirLoading] = useState(false);
    const [progressBar, setProgressBar] = useState(0);
    const [progressTotal, setProgressTotal] = useState(0);
    const [buttonSpain, setButtonSpain] = useState<string | null>(null);
    const [instantDeletion, setInstantDeletion] = useState('not-instant');
    const [skip, setSkip] = useState<string[]>([]);
    const [currentScanDir, setCurrentScanDir] = useState<string>('');

    const retryCount = useRef(0);
    const instantDeletionRef = useRef(instantDeletion);
    const skipRef = useRef(skip);
    const progressTotalRef = useRef(progressTotal);
    const dirListExistRef = useRef<string[]>([]);

    useEffect(() => { instantDeletionRef.current = instantDeletion; }, [instantDeletion]);
    useEffect(() => { skipRef.current = skip; }, [skip]);
    useEffect(() => { progressTotalRef.current = progressTotal; }, [progressTotal]);
    useEffect(() => { dirListExistRef.current = dirListExist; }, [dirListExist]);

    const handleDirModalCancel = () => {
        setGeneralData({ isDirModalOpen: false });
    };

    const handleDirRescan = async (dir: string = "all") => {
        setScanRubbishDirLoading(true);
        try {
            const dirList = await rescanDir({ dir }) as { data: { thedirlist: Record<string, DirStatusItem> } };
            setScanRubbishDirList(dirList.data.thedirlist);
        } finally {
            setScanRubbishDirLoading(false);
        }
    };

    const exclude_from_bulk_scan = (dir: string = "") => {
        setSkip(prev => [...prev, dir]);
    };

    const processDirectory = () => {
        const currentList = dirListExistRef.current;
        if (currentList.length > 0) {
            // PHP picks the last unfinished directory in the list
            setCurrentScanDir(currentList[currentList.length - 1]);
        }

        const params = new URLSearchParams();
        params.append('action', 'immediately_search_rubbish_file');
        params.append('nonce', tsmltParams.tsmlt_wpnonce);
        params.append('instantDeletion', instantDeletionRef.current);
        skipRef.current.forEach((value) => {
            params.append('skip[]', value);
        });

        Axios
            .post(tsmltParams.ajaxUrl, params)
            .then((response) => {
                if (response && response.data) {
                    retryCount.current = 0;
                    const { dirList, dirStatusList } = response.data.data as {
                        dirList: Record<string, unknown>;
                        dirStatusList: Record<string, DirStatusItem>;
                    };
                    setScanRubbishDirList(dirStatusList);
                    const list = Object.entries(dirList).map(([key]) => key);
                    const percent = Math.floor((100 * (progressTotalRef.current - list.length)) / progressTotalRef.current);
                    setProgressBar(percent);
                    setDirListExist(list);
                    if (percent >= 100) {
                        setCurrentScanDir('');
                        window.location.reload();
                    }
                } else {
                    console.error("Invalid response structure:", response);
                }
            })
            .catch((error) => {
                console.error("Request failed:", error);
                if (retryCount.current < MAX_RETRIES) {
                    retryCount.current++;
                    console.log(`Retrying... attempt ${retryCount.current} of ${MAX_RETRIES}`);
                    setTimeout(() => processDirectory(), 2000);
                } else {
                    retryCount.current = 0;
                    console.error("Max retries reached. Stopping.");
                    setButtonSpain(null);
                }
            });
    };

    const handleDirScanManually = () => {
        setButtonSpain("bulkScan");
        processDirectory();
    };

    useEffect(() => {
        setScanRubbishDirList(generalData.scanRubbishDirList as Record<string, DirStatusItem>);
    }, [generalData.scanRubbishDirList]);

    useEffect(() => {
        const list = Object.entries(scanRubbishDirList).map(([key]) => key);
        setProgressTotal(list.length);
    }, [scanRubbishDirList]);

    useEffect(() => {
        if (dirListExist.length > 0) {
            processDirectory();
        }
    }, [dirListExist]);

    const dirEntries = Object.entries(scanRubbishDirList) as [string, DirStatusItem][];

    return (
        <Modal
            isOpen={generalData.isDirModalOpen}
            onClose={handleDirModalCancel}
            title="Directory List"
            maxWidth="max-w-[950px]"
            footer={
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
            }
        >
            <hr className="border-gray-200" />
            <div className="px-4 py-4 h-[450px] overflow-y-auto border-b border-gray-100">
                <DirectoryList
                    dirEntries={dirEntries}
                    skip={skip}
                    onExclude={exclude_from_bulk_scan}
                    onRescan={handleDirRescan}
                    loading={scanRubbishDirLoading}
                />
            </div>

            {progressBar > 0 && (
                <div className="px-6 py-3">
                    <h5 className="text-base! font-semibold mt-0!  text-gray-900 mb-2">Progress:</h5>
                    <ProgressBar percent={progressBar} />
                    {currentScanDir && (
                        <p className="text-xs text-gray-500 mt-1.5 truncate">
                            Scanning: <span className="font-medium text-gray-700">{trimPath(currentScanDir)}</span>
                        </p>
                    )}
                </div>
            )}
        </Modal>
    );
}

export default DirectoryModal;
