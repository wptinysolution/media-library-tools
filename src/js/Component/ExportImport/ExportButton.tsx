import { useState, useEffect } from "react";
import { useStore } from "@/js/Utils/store";
import { getMedia } from "@/js/Utils/Data";
import ExportModalCSV from "./ExportModalCSV";
import { loadHistory, saveHistory, redownloadCsv } from "./ExportCSV";
import type { ExportRecord } from "./ExportCSV";
import type { MediaPost } from "@/js/Utils/store";
import ProLabel from "@/js/Component/Badges/ProLabel";

function ExportButton() {
    const { exportImport, setExportImport, mediaData, setGeneralData } = useStore();

    const [percent, setPercent] = useState(0);
    const [isModalOpen, setModalOpen] = useState(false);
    const [showComplete, setShowComplete] = useState(false);
    const [history, setHistory] = useState<ExportRecord[]>(() => loadHistory().reverse());

    const isExport = exportImport.isExport;

    const handleExport = async () => {
        if (!tsmltParams.hasExtended) {
            setGeneralData({ openProModal: true });
            return;
        }

        setExportImport({
            isExport: true,
            isImport: false,
            runImporter: false,
            runExporter: false,
            mediaFiles: [],
            fileCount: 0,
            percent: 0,
            totalPage: 0,
        });

        let allMedia: MediaPost[] = [];
        let page = 1;
        let totalPages = 1;

        try {
            do {
                const res = await getMedia({ ...mediaData.postQuery, paged: page }) as {
                    posts?: MediaPost[];
                    total_page?: number;
                };
                allMedia = [...allMedia, ...(res?.posts || [])];
                totalPages = res?.total_page || 1;
                setPercent(Math.round((page / totalPages) * 100));
                page++;
            } while (page <= totalPages);

            setExportImport({ mediaFiles: allMedia, fileCount: allMedia.length, percent: 100, totalPage: totalPages });
        } catch (error) {
            console.error('Export failed:', error);
            setExportImport({ isExport: false });
            setPercent(0);
        }
    };

    const isDone = percent >= 100;

    const deleteRecord = (id: string) => {
        const updated = loadHistory().filter(r => r.id !== id);
        saveHistory(updated);
        setHistory([...updated].reverse());
    };

    const clearHistory = () => {
        saveHistory([]);
        setHistory([]);
    };

    // Refresh history list after a modal closes (download happened inside modal).
    const handleModalClose = (open: boolean) => {
        setModalOpen(open);
        if (!open) setHistory([...loadHistory()].reverse());
    };

    useEffect(() => {
        if ( isDone ) {
            const timer = setTimeout(() => setShowComplete(true), 1000);
            return () => clearTimeout(timer);
        }
        setShowComplete(false);
    }, [isDone]);

    return (
        <>
            <div className="min-h-screen bg-gray-50">
                <div className="max-w-3xl mx-auto py-8">
                    <div className="mb-8">
                        <h1 className="text-2xl font-semibold text-gray-900 m-0! inline-flex items-center gap-2">
                            CSV Export
                            {!tsmltParams.hasExtended && <ProLabel />}
                        </h1>
                        <p className="text-sm text-gray-500 mt-1">Export your media library data to a CSV file.</p>
                    </div>

                    <div className="bg-white rounded-lg border border-gray-200 p-10 text-center">
                        {/* Icon */}
                        <div className={`w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 ${showComplete ? 'bg-green-100' : 'bg-blue-100'}`}>
                            {showComplete ? (
                                <svg className="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                            ) : (
                                <svg className="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                            )}
                        </div>

                        {/* Title & description */}
                        <h3 className="text-lg font-medium text-gray-900 mb-2">
                            {showComplete ? 'Export Complete' : 'Export Media to CSV'}
                        </h3>
                        <p className="text-sm text-gray-500 mb-6">
                            {showComplete
                                ? `${exportImport.fileCount} media files are ready to download.`
                                : 'Fetch all media from your library and download as a CSV file.'}
                        </p>

                        {/* Progress bar */}
                        {isExport && !showComplete && (
                            <div className="mb-6 text-left">
                                <div className="flex items-center justify-between mb-1.5">
                                    <span className="text-sm font-medium text-gray-700">Fetching media…</span>
                                    <span className="text-sm text-gray-500">{percent}%</span>
                                </div>
                                <div className="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                                    <div
                                        className="bg-blue-600 h-full rounded-full transition-all duration-300"
                                        style={{ width: `${percent}%` }}
                                    />
                                </div>
                            </div>
                        )}

                        {/* Action button */}
                        {showComplete ? (
                            <button
                                type="button"
                                className="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer transition-colors"
                                onClick={() => handleModalClose(true)}
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                Download CSV
                            </button>
                        ) : (
                            <button
                                type="button"
                                className="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                                onClick={handleExport}
                                disabled={!!isExport}
                            >
                                {isExport ? (
                                    <>
                                        <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                        </svg>
                                        Fetching…
                                    </>
                                ) : (
                                    <>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                        </svg>
                                        Run Exporter
                                    </>
                                )}
                            </button>
                        )}
                    </div>
                </div>

                {/* Export history */}
                {history.length > 0 && (
                    <div className="max-w-3xl mx-auto px-6 py-8 bg-white rounded-lg border border-gray-200 overflow-hidden">
                        <div className="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50">
                            <span className="text-sm font-medium text-gray-700">
                                Export History ({history.length})
                            </span>
                            <button
                                type="button"
                                onClick={clearHistory}
                                className="text-xs text-red-500 hover:text-red-700 cursor-pointer transition-colors"
                            >
                                Clear all
                            </button>
                        </div>
                        <ul className="divide-y divide-gray-100">
                            {history.map(record => {
                                const canRedownload = !!sessionStorage.getItem(`tsmlt_csv_${record.id}`);
                                return (
                                    <li key={record.id} className="flex items-center justify-between gap-4 px-4 py-2.5 hover:bg-gray-50">
                                        <div className="min-w-0 flex-1">
                                            <p className="text-xs font-medium text-gray-800 truncate">{record.filename}</p>
                                            <p className="text-[11px] text-gray-400 mt-0.5">
                                                {record.rows.toLocaleString()} rows &middot; {new Date(record.date).toLocaleString()}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2 shrink-0">
                                            {canRedownload && (
                                                <button
                                                    type="button"
                                                    onClick={() => redownloadCsv(record.id, record.filename)}
                                                    className="text-xs text-blue-600 hover:text-blue-800 cursor-pointer transition-colors inline-flex items-center gap-1"
                                                    title="Re-download this CSV"
                                                >
                                                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                    </svg>
                                                    Download
                                                </button>
                                            )}
                                            <button
                                                type="button"
                                                onClick={() => deleteRecord(record.id)}
                                                className="text-gray-400 hover:text-red-500 cursor-pointer transition-colors"
                                                title="Remove from history"
                                            >
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                )}

            </div>
            {isModalOpen && <ExportModalCSV isModalOpen={isModalOpen} setModalOpen={handleModalClose} />}
        </>
    );
}

export default ExportButton;
