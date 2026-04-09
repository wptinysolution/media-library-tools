import React, { useState, useRef } from 'react';
import { useStore } from "@/js/Utils/store";
import Papa from 'papaparse';
import type { ExportImportSettings } from "@/js/Utils/store";

function UploadCsv() {
    const { exportImport, setExportImport } = useStore();
    const [filename, setFilename] = useState('');
    const fileInputRef = useRef<HTMLInputElement>(null);

    const settings = exportImport.settings as ExportImportSettings;

    const handleFileUpload = (file: File) => {
        const formData = new FormData();
        formData.append('file', file);

        fetch(`${tsmltParams.restApiUrl}wp/v2/media`, {
            method: 'POST',
            headers: {
                'X-WP-Nonce': tsmltParams.rest_nonce,
            },
            body: formData,
        })
            .then((res) => res.json())
            .then((response: { source_url: string }) => {
                const { source_url } = response;
                setFilename(file.name);

                fetch(source_url)
                    .then((res) => res.text())
                    .then((csvText) => {
                        Papa.parse(csvText, {
                            header: true,
                            dynamicTyping: true,
                            complete: (results) => {
                                const sessionId = Date.now().toString();
                                try {
                                    sessionStorage.setItem(`tsmlt_import_${sessionId}`, JSON.stringify(results.data));
                                    sessionStorage.setItem(`tsmlt_import_id`, sessionId);
                                } catch { /* quota exceeded */ }
                                setExportImport({
                                    mediaFiles: results.data as never[],
                                    fileCount: results.data.length,
                                    percent: 0,
                                    totalPage: results.data.length,
                                    csvFilename: file.name,
                                });
                            },
                        });
                    })
                    .catch((err) => console.error('Error fetching or parsing CSV:', err));
            })
            .catch((err) => console.error('Upload failed:', err));
    };

    const onFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            handleFileUpload(file);
        }
    };

    return (
        <>
            <input
                ref={fileInputRef}
                type="file"
                accept=".csv"
                className="hidden"
                onChange={onFileChange}
            />

            {exportImport.fileCount && !exportImport.runImporter ? (
                <>
                    <label className="inline-flex items-center gap-2 cursor-pointer mb-2">
                        <input
                            type="checkbox"
                            className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                            checked={!!settings.importUpdateContent}
                            onChange={(event) =>
                                setExportImport({
                                    settings: {
                                        ...settings,
                                        importUpdateContent: event.target.checked ? 'update' : false,
                                    },
                                })
                            }
                        />
                        <span className="text-sm text-gray-900">
                            Update existing media — matches by <strong>ID</strong> first, then by <strong>slug</strong>.
                        </span>
                    </label>
                    <p className="text-sm text-gray-500 mt-1">
                        Each row is matched to an existing attachment using the <strong>ID</strong> column. If no ID match is found, it falls back to the <strong>slug</strong> column. Rows with no match are skipped. Any column left blank will leave that field unchanged.
                    </p>
                    <hr className="border-gray-200 my-3" />
                    {settings.importUpdateContent ? (
                        <>
                            <label className="inline-flex items-center gap-2 cursor-pointer mb-2">
                                <input
                                    type="checkbox"
                                    className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                    checked={!!settings.importRename}
                                    onChange={(event) =>
                                        setExportImport({
                                            settings: {
                                                ...settings,
                                                importRename: event.target.checked ? 'importRename' : false,
                                            },
                                        })
                                    }
                                />
                                <span className="text-sm text-gray-900">
                                    Rename using the value located in the <strong>( rename_to )</strong> column.
                                </span>
                            </label>
                            <span className="text-sm text-gray-600">
                                Note: Rename media file that match by <strong>ID</strong> or <strong>slug</strong> And Any missing column data will be left unchanged.
                            </span>
                            <h5 className="border border-gray-200 px-4 py-3 my-3 text-[13px] text-red-600 text-center rounded">
                                We suggest you before renaming at first you should practice in your staging site.
                            </h5>
                            <hr className="border-gray-200 my-3" />
                        </>
                    ) : ''}

                    <button
                        type="button"
                        className="w-70 h-17.5 text-2xl! flex items-center justify-center gap-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 cursor-pointer transition-colors font-medium mx-auto"
                        onClick={() => setExportImport({ runImporter: true })}
                    >
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Run the importer
                    </button>
                    <p className="text-center text-sm text-gray-700">
                        {filename && <span>{filename}</span>}
                    </p>
                </>
            ) : ''}

            {!exportImport.fileCount ? (
                <button
                    type="button"
                    className="w-70 h-17.5 text-2xl! flex items-center justify-center gap-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 cursor-pointer transition-colors font-medium mx-auto"
                    onClick={() => fileInputRef.current?.click()}
                >
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Upload CSV File
                </button>
            ) : ''}
        </>
    );
}

export default UploadCsv;
