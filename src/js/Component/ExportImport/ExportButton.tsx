import { useState, useEffect } from "react";
import { useStore } from "@/js/Utils/store";
import { getMedia } from "@/js/Utils/Data";
import ExportModalCSV from "./ExportModalCSV";
import type { MediaPost } from "@/js/Utils/store";
import ProLabel from "@/js/Component/Badges/ProLabel";

function ExportButton() {
    const { exportImport, setExportImport, mediaData, setGeneralData } = useStore();

    const [percent, setPercent] = useState(0);
    const [isModalOpen, setModalOpen] = useState(false);
    const [showComplete, setShowComplete] = useState(false);

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
        }
    };

    const isDone = percent >= 100;

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
                <div className="max-w-3xl mx-auto px-6 py-8">
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
                                onClick={() => setModalOpen(true)}
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
            </div>
            {isModalOpen && <ExportModalCSV isModalOpen={isModalOpen} setModalOpen={setModalOpen} />}
        </>
    );
}

export default ExportButton;
