import { useState } from "react";
import { useStore } from "@/js/Utils/store";
import ImportInfo from "./ImportInfo";
import UploadCsv from "./UploadCsv";
import ProLabel from "@/js/Component/Badges/ProLabel";
import { loadImportHistory, saveImportHistory } from "./ExportCSV";
import type { ImportRecord } from "./ExportCSV";

function ImportButton() {
    const { exportImport, setExportImport, setGeneralData } = useStore();
    const [history, setHistory] = useState<ImportRecord[]>(() => [...loadImportHistory()].reverse());

    const isImport = exportImport.isImport;

    const deleteRecord = (id: string) => {
        const updated = loadImportHistory().filter(r => r.id !== id);
        saveImportHistory(updated);
        setHistory([...updated].reverse());
    };

    const clearHistory = () => {
        saveImportHistory([]);
        setHistory([]);
    };

    // Refresh history list when the importer finishes (navigates back).
    const refreshHistory = () => setHistory([...loadImportHistory()].reverse());

    const handleReimport = (record: ImportRecord) => {
        if (!tsmltParams.hasExtended) {
            setGeneralData({ openProModal: true });
            return;
        }
        try {
            const raw = sessionStorage.getItem(`tsmlt_import_${record.sessionId}`);
            if (!raw) return;
            const parsed = JSON.parse(raw) as never[];
            setExportImport({
                isImport: true,
                runImporter: false,
                runExporter: false,
                mediaFiles: parsed,
                fileCount: parsed.length,
                percent: 0,
                totalPage: parsed.length,
                csvFilename: record.filename,
            });
        } catch { /* session expired */ }
    };

    const handleImport = () => {
        if (!tsmltParams.hasExtended) {
            setGeneralData({ openProModal: true });
            return;
        }
        setExportImport({
            isImport: true,
            runImporter: false,
            runExporter: false,
            mediaFiles: [],
            fileCount: 0,
            percent: 0,
            totalPage: 0,
        });
    };

    return (
        <div className="min-h-screen bg-gray-50">
            <div className="max-w-3xl mx-auto px-6 py-8">
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-gray-900 m-0! inline-flex items-center gap-2">
                        CSV Import
                        {!tsmltParams.hasExtended && <ProLabel /> }
                    </h1>
                    <p className="text-sm text-gray-500 mt-1">Import and update media library data from a CSV file.</p>
                </div>

                {/* Info notice */}
                <div className="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mb-6 text-sm text-amber-800">
                    <p className="m-0!">If import fails, try importing in smaller batches — performance depends on your server capacity.</p>
                    <p className="mt-1 m-0!">
                        Accepted column headers:{' '}
                        <strong>ID, slug, url, rename_to, title, caption, description, alt_text, post_parent, menu_order, groups, custom_meta:_key</strong>
                    </p>
                    <p className="mt-1 m-0!">
                        <strong>groups</strong> takes comma-separated group names (e.g. <em>Folder 1, Folder 2</em>) and creates any that
                        do not exist yet. <strong>post_parent</strong> is the attached post ID (use <em>0</em> to detach) and{' '}
                        <strong>menu_order</strong> is the sort order. Empty cells leave the existing value unchanged.
                    </p>
                </div>

                <div className="bg-white rounded-lg border border-gray-200 p-10">
                    {isImport ? (
                        <>
                            {exportImport.runImporter ? (
                                <ImportInfo onComplete={refreshHistory} />
                            ) : (
                                <div className="flex flex-col justify-center">
                                    <UploadCsv />
                                </div>
                            )}
                        </>
                    ) : (
                        <div className="text-center">
                            <div className="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg className="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                            </div>
                            <h3 className="text-lg font-medium text-gray-900 mb-2">Import Media from CSV</h3>
                            <p className="text-sm text-gray-500 mb-6">
                                Upload a CSV file to update your media library data in bulk.
                            </p>
                            <button
                                type="button"
                                className="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer transition-colors"
                                onClick={handleImport}
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                Start Import
                            </button>
                        </div>
                    )}
                </div>

                {/* Import history */}
                {history.length > 0 && (
                    <div className="mt-6 bg-white rounded-lg border border-gray-200 overflow-hidden">
                        <div className="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50">
                            <span className="text-sm font-medium text-gray-700">
                                Import History ({history.length})
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
                                const canReimport = !!record.sessionId && !!sessionStorage.getItem(`tsmlt_import_${record.sessionId}`);
                                return (
                                    <li key={record.id} className="flex items-center justify-between gap-4 px-4 py-2.5 hover:bg-gray-50">
                                        <div className="min-w-0 flex-1">
                                            <p className="text-xs font-medium text-gray-800 truncate">{record.filename}</p>
                                            <p className="text-[11px] text-gray-400 mt-0.5">
                                                {record.succeeded.toLocaleString()} / {record.rows.toLocaleString()} succeeded &middot; {new Date(record.date).toLocaleString()}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2 shrink-0">
                                            {canReimport && (
                                                <button
                                                    type="button"
                                                    onClick={() => handleReimport(record)}
                                                    className="text-xs text-blue-600 hover:text-blue-800 cursor-pointer transition-colors inline-flex items-center gap-1"
                                                    title="Re-import this CSV"
                                                >
                                                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                    </svg>
                                                    Re-import
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
        </div>
    );
}

export default ImportButton;
