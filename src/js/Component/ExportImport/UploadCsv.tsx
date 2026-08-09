import React, { useState, useRef, useMemo } from 'react';
import { useStore } from "@/js/Utils/store";
import Papa from 'papaparse';
import toast from 'react-hot-toast';
import type { ExportImportSettings } from "@/js/Utils/store";

function UploadCsv() {
    const { exportImport, setExportImport } = useStore();
    const [filename, setFilename] = useState('');
    const [parsing, setParsing] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const settings = exportImport.settings as ExportImportSettings;
    const isUpdateMode = !!settings.importUpdateContent;
    const rowCount = exportImport.fileCount;

    // An ID column with real values almost always means the file came from CSV Export,
    // so create mode would duplicate the library rather than edit it.
    const hasIdColumn = useMemo(
        () => exportImport.mediaFiles.some(
            row => !!(row as Record<string, string>)?.['ID']?.toString().trim()
        ),
        [exportImport.mediaFiles]
    );

    const handleFileUpload = (file: File) => {
        setParsing(true);
        setFilename(file.name);

        const reader = new FileReader();
        reader.onerror = () => {
            setParsing(false);
            toast.error('Failed to read the CSV file.');
        };
        reader.onload = (e) => {
            let csvText = e.target?.result as string;
            if (!csvText) {
                setParsing(false);
                toast.error('CSV file is empty.');
                return;
            }

            // Strip BOM (UTF-8 / UTF-16) that can break header detection.
            csvText = csvText.replace(/^\uFEFF/, '');

            // Parse without header mode to avoid PapaParse header-detection
            // bugs with certain CSV files, then manually map headers.
            const results = Papa.parse(csvText, {
                header: false,
                skipEmptyLines: 'greedy',
            });

            setParsing(false);

            const rawRows = results.data as string[][];
            if (!rawRows.length || rawRows.length < 2) {
                toast.error('CSV file has no data rows.');
                return;
            }

            // First row = headers, rest = data.
            const headers = rawRows[0].map(h => (h ?? '').toString().trim());
            const rows: Record<string, string>[] = [];

            for (let i = 1; i < rawRows.length; i++) {
                const cells = rawRows[i];
                // Skip completely empty rows.
                if (!cells || !cells.some(v => v != null && v !== '')) continue;
                const row: Record<string, string> = {};
                for (let j = 0; j < headers.length; j++) {
                    row[headers[j]] = cells[j] ?? '';
                }
                rows.push(row);
            }

            if (!rows.length) {
                toast.error('No valid rows found in CSV file. Check the file format.');
                return;
            }

            const sessionId = Date.now().toString();
            try {
                sessionStorage.setItem(`tsmlt_import_${sessionId}`, JSON.stringify(rows));
                sessionStorage.setItem(`tsmlt_import_id`, sessionId);
            } catch { /* quota exceeded — re-import won't be available but import still works */ }
            setExportImport({
                mediaFiles: rows as never[],
                fileCount: rows.length,
                percent: 0,
                totalPage: rows.length,
                csvFilename: file.name,
            });
        };
        reader.readAsText(file, 'UTF-8');
    };

    // Drop the parsed file but stay on the import screen so another CSV can be picked.
    const handleClearFile = () => {
        setFilename('');
        setExportImport({
            mediaFiles: [],
            fileCount: 0,
            percent: 0,
            totalPage: 0,
            csvFilename: '',
        });
    };

    // Leave the importer entirely, back to the CSV Import landing screen.
    const handleCancelImport = () => {
        setFilename('');
        setExportImport({
            isImport: false,
            runImporter: false,
            mediaFiles: [],
            fileCount: 0,
            percent: 0,
            totalPage: 0,
            csvFilename: '',
        });
    };

    // Only create mode asks for confirmation — the safe path stays a single click.
    const handleRun = () => {
        if (!isUpdateMode) {
            const message = `This will create ${rowCount.toLocaleString()} new media ${1 === rowCount ? 'item' : 'items'}.\n\n`
                + 'Nothing will be updated. If this CSV came from CSV Export, you will end up with duplicates of media you already have.\n\n'
                + 'Continue?';
            if (!window.confirm(message)) {
                return;
            }
        }
        setExportImport({ runImporter: true });
    };

    const onFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            handleFileUpload(file);
        }
        // Reset so the same file can be re-selected if needed.
        e.target.value = '';
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
                    <div className="flex items-center justify-between gap-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2.5 mb-5">
                        <div className="flex items-center gap-2.5 min-w-0">
                            <svg className="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <div className="min-w-0">
                                <p className="text-sm font-medium text-gray-900 truncate m-0!">
                                    {exportImport.csvFilename || filename || 'Selected file'}
                                </p>
                                <p className="text-xs text-gray-500 mt-0.5 m-0!">
                                    {rowCount.toLocaleString()} {1 === rowCount ? 'row' : 'rows'} ready to import
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            className="text-sm text-blue-600 hover:text-blue-800 cursor-pointer transition-colors shrink-0 bg-transparent border-0 p-0"
                            onClick={handleClearFile}
                        >
                            Choose a different file
                        </button>
                    </div>

                    <p className="text-sm font-medium text-gray-900 mb-3">
                        What should this import do?
                    </p>

                    <label
                        className={`flex items-start gap-3 cursor-pointer rounded-md border p-3 mb-2 transition-colors ${
                            isUpdateMode ? 'border-blue-500 bg-blue-50/50' : 'border-gray-200 hover:bg-gray-50'
                        }`}
                    >
                        <input
                            type="radio"
                            name="tsmlt-import-mode"
                            className="w-4 h-4 mt-0.5 border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer shrink-0"
                            checked={isUpdateMode}
                            onChange={() =>
                                setExportImport({
                                    settings: { ...settings, importUpdateContent: 'update' },
                                })
                            }
                        />
                        <span className="min-w-0">
                            <span className="block text-sm font-medium text-gray-900">
                                Update existing media
                            </span>
                            <span className="block text-sm text-gray-500 mt-0.5">
                                Matches each row to an existing attachment by <strong>ID</strong>, falling back to <strong>slug</strong>. Rows with no match are skipped and nothing new is created. Any column left blank leaves that field unchanged.
                            </span>
                        </span>
                    </label>

                    <label
                        className={`flex items-start gap-3 cursor-pointer rounded-md border p-3 transition-colors ${
                            !isUpdateMode ? 'border-amber-500 bg-amber-50/50' : 'border-gray-200 hover:bg-gray-50'
                        }`}
                    >
                        <input
                            type="radio"
                            name="tsmlt-import-mode"
                            className="w-4 h-4 mt-0.5 border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer shrink-0"
                            checked={!isUpdateMode}
                            onChange={() =>
                                setExportImport({
                                    settings: { ...settings, importUpdateContent: false, importRename: false },
                                })
                            }
                        />
                        <span className="min-w-0">
                            <span className="block text-sm font-medium text-gray-900">
                                Import as new media
                            </span>
                            <span className="block text-sm text-gray-500 mt-0.5">
                                Creates a brand new attachment for every row using the <strong>url</strong> column. Existing media is left untouched.
                            </span>
                            <span className="block text-sm text-amber-700 mt-1">
                                ⚠ Running the same file twice will create duplicates.
                            </span>
                        </span>
                    </label>

                    {!isUpdateMode && hasIdColumn && (
                        <div className="mt-3 rounded-md border border-amber-300 bg-amber-50 px-3 py-2.5 text-sm text-amber-800">
                            This file has an <strong>ID</strong> column filled in, which usually means it came from CSV Export. Importing it as new media will duplicate {rowCount.toLocaleString()} {1 === rowCount ? 'item' : 'items'}. Did you mean <strong>Update existing media</strong>?
                        </div>
                    )}

                    {isUpdateMode ? (
                        <div className="ml-7 mt-2 border-l-2 border-gray-200 pl-4">
                            <label className="flex items-start gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    className="w-4 h-4 mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer shrink-0"
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
                                <span className="min-w-0">
                                    <span className="block text-sm font-medium text-gray-900">
                                        Also rename the file using the <code className="text-[13px] bg-gray-100 rounded px-1 py-0.5">rename_to</code> column
                                    </span>
                                    <span className="block text-sm text-gray-500 mt-0.5">
                                        Renames the actual file on disk for matched rows. Rows with an empty <code className="text-[13px] bg-gray-100 rounded px-1 py-0.5">rename_to</code> keep their current filename.
                                    </span>
                                </span>
                            </label>

                            {!!settings.importRename && (
                                <div className="mt-2 rounded-md border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700">
                                    Renaming changes file URLs. Any hardcoded link to the old filename will break, so test on a staging site first.
                                </div>
                            )}
                        </div>
                    ) : ''}

                    <hr className="border-gray-200 my-4" />

                    <div className="flex flex-col items-center gap-3">
                        <button
                            type="button"
                            className={`min-w-70 px-6 h-17.5 text-2xl! flex items-center justify-center gap-2 text-white rounded-md cursor-pointer transition-colors font-medium ${
                                isUpdateMode ? 'bg-blue-600 hover:bg-blue-700' : 'bg-amber-600 hover:bg-amber-700'
                            }`}
                            onClick={handleRun}
                        >
                            <svg className="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            {isUpdateMode
                                ? `Update ${rowCount.toLocaleString()} media ${1 === rowCount ? 'item' : 'items'}`
                                : `Create ${rowCount.toLocaleString()} new media ${1 === rowCount ? 'item' : 'items'}`}
                        </button>
                        <button
                            type="button"
                            className="text-sm text-gray-500 hover:text-gray-700 cursor-pointer transition-colors bg-transparent border-0 p-0"
                            onClick={handleCancelImport}
                        >
                            Cancel
                        </button>
                    </div>
                </>
            ) : ''}

            {!exportImport.fileCount ? (
                <div className="flex flex-col items-center gap-3">
                    <button
                        type="button"
                        className="w-70 h-17.5 text-2xl! flex items-center justify-center gap-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 cursor-pointer transition-colors font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                        onClick={() => fileInputRef.current?.click()}
                        disabled={parsing}
                    >
                        {parsing ? (
                            <>
                                <svg className="w-6 h-6 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                                Parsing CSV...
                            </>
                        ) : (
                            <>
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                Upload CSV File
                            </>
                        )}
                    </button>
                    {!parsing && (
                        <button
                            type="button"
                            className="text-sm text-gray-500 hover:text-gray-700 cursor-pointer transition-colors bg-transparent border-0 p-0"
                            onClick={handleCancelImport}
                        >
                            Cancel
                        </button>
                    )}
                </div>
            ) : ''}
        </>
    );
}

export default UploadCsv;
