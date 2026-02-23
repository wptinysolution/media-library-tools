import React, { useState, useRef } from 'react';
import { useStore } from "@/js/Utils/store";
import Papa from 'papaparse';

function UploadCsv() {
    const { exportImport, setExportImport } = useStore();
    const [filename, setFilename] = useState('');
    const fileInputRef = useRef(null);

    const handleFileUpload = (file) => {
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
            .then((response) => {
                const { source_url } = response;
                setFilename(file.name);

                fetch(source_url)
                    .then((res) => res.text())
                    .then((csvText) => {
                        Papa.parse(csvText, {
                            header: true,
                            dynamicTyping: true,
                            complete: (results) => {
                                setExportImport({
                                    mediaFiles: results.data,
                                    fileCount: results.data.length,
                                    percent: 0,
                                    totalPage: results.data.length,
                                });
                            },
                        });
                    })
                    .catch((err) => console.error('Error fetching or parsing CSV:', err));
            })
            .catch((err) => console.error('Upload failed:', err));
    };

    const onFileChange = (e) => {
        const file = e.target.files[0];
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
                            checked={exportImport.settings.importUpdateContent}
                            onChange={(event) =>
                                setExportImport({
                                    settings: {
                                        ...exportImport.settings,
                                        importUpdateContent: event.target.checked ? 'update' : false,
                                    },
                                })
                            }
                        />
                        <span className="text-sm text-gray-900">
                            Existing media file that match by <strong>ID</strong> or <strong>slug</strong> will be updated.
                        </span>
                    </label>
                    <br />
                    <span className="text-sm text-gray-600">
                        Media that do not exist will be skipped and missing column data will remain unchanged.
                    </span>
                    <hr className="border-gray-200 my-3" />
                    {exportImport.settings.importUpdateContent ? (
                        <>
                            <label className="inline-flex items-center gap-2 cursor-pointer mb-2">
                                <input
                                    type="checkbox"
                                    className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                    checked={exportImport.settings.importRename}
                                    onChange={(event) =>
                                        setExportImport({
                                            settings: {
                                                ...exportImport.settings,
                                                importRename: event.target.checked ? 'importRename' : false,
                                            },
                                        })
                                    }
                                />
                                <span className="text-sm text-gray-900">
                                    Rename using the value located in the <strong>( rename_to )</strong> column.
                                </span>
                            </label>
                            <br />
                            <span className="text-sm text-gray-600">
                                Note: Rename media file that match by <strong>ID</strong> or <strong>slug</strong> And Any missing column data will be left unchanged.
                            </span>
                            <h5 className="border border-gray-200 px-4 py-3 my-3 text-[13px] text-red-600 text-center rounded">
                                We suggest you before renaming at first you should practice in your staging site.
                            </h5>
                            <hr className="border-gray-200 my-3" />
                        </>
                    ) : (
                        ''
                    )}

                    <button
                        type="button"
                        className="w-[280px] h-[70px] text-2xl flex items-center justify-center gap-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 cursor-pointer transition-colors font-medium mx-auto"
                        onClick={() => setExportImport({ runImporter: true })}
                    >
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Run the importer
                    </button>
                    <hr className="border-gray-200 my-2" />
                    <p className="text-center text-sm text-gray-700">
                        {filename && <span>{filename}</span>}
                    </p>
                </>
            ) : (
                ''
            )}

            {!exportImport.fileCount ? (
                <button
                    type="button"
                    className="w-[280px] h-[70px] text-2xl flex items-center justify-center gap-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 cursor-pointer transition-colors font-medium mx-auto"
                    onClick={() => fileInputRef.current?.click()}
                >
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Upload CSV File
                </button>
            ) : (
                ''
            )}
        </>
    );
}

export default UploadCsv;
