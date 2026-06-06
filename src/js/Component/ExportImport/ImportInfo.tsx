import { useEffect, useState, useMemo, useRef } from "react";
import { useStore } from "@/js/Utils/store";
import { importOneByOne } from "@/js/Utils/Data";
import { Link } from "react-router-dom";
import type { ExportImportSettings } from "@/js/Utils/store";
import { loadImportHistory, saveImportHistory } from "./ExportCSV";
import MediaThumbnail from "@/js/Component/Common/MediaThumbnail";

interface UploadedItem {
    id: string | number;
    url: string;
    status: string;
    message?: string;
}

function ImportInfo({ onComplete }: { onComplete?: () => void }) {
    const { exportImport } = useStore();

    const [percent, setPercent] = useState(0);
    const [uploadedFile, setUploadedFile] = useState<UploadedItem[]>([]);
    const [currentFile, setCurrentFile] = useState<string | null>(null);
    const settings = exportImport.settings as ExportImportSettings;
    const recordedRef = useRef(false);

    const getFileNameFromURL = (url: string): string | false => {
        if (!url) {
            return false;
        }
        const urlObject = new URL(url);
        const pathnameParts = urlObject.pathname.split('/');
        return pathnameParts[pathnameParts.length - 1];
    };

    const handleExitImport = () => {
        location.reload();
    };

    const uploadMediaSequentially = async () => {
        const mediaFiles = [...exportImport.mediaFiles];
        const total = mediaFiles.length;

        for (let i = 0; i < total; i++) {
            const item = mediaFiles[i];
            setPercent(Math.floor(100 * i / total));

            if (!item || typeof item !== 'object') {
                continue;
            }

            const url = (item['url'] as string) || '';
            const idCell = (item['ID'] as string | number | undefined)?.toString().trim() ?? '';
            const slugCell = (item['slug'] as string | undefined)?.toString().trim() ?? '';
            const hasUrl = url.toString().length > 0;
            const hasMatchKey = idCell.length > 0 || slugCell.length > 0;
            const isUpdateMode = !!settings.importUpdateContent;

            // Send the row when:
            //  - update mode + ID/slug is present (update existing attachment), OR
            //  - a URL is present (create new attachment).
            // Skip and report rows that have neither so the user sees what was ignored
            // instead of a phantom "100% done" with no changes.
            const shouldSend = (isUpdateMode && hasMatchKey) || hasUrl;

            if (!shouldSend) {
                const reason = isUpdateMode
                    ? 'No ID or slug in this row — cannot match an existing attachment.'
                    : 'No URL in this row — enable "Update existing content" to edit by ID/slug.';
                setUploadedFile(prev => [...prev, {
                    id: item['ID'] || i,
                    url,
                    status: 'skipped',
                    message: reason,
                }]);
                continue;
            }

            setCurrentFile(url);
            try {
                const importedItem = await importOneByOne({ media: item, settings: exportImport.settings }) as { data: UploadedItem };
                const result = importedItem?.data;
                if (result && typeof result === 'object') {
                    setUploadedFile(prev => [...prev, result]);
                } else {
                    setUploadedFile(prev => [...prev, { id: item['ID'] || i, url, status: 'failed' }]);
                }
            } catch {
                setUploadedFile(prev => [...prev, { id: item['ID'] || i, url, status: 'failed' }]);
            }
            setCurrentFile(null);
        }

        setPercent(100);
    };

    useEffect(() => {
        uploadMediaSequentially();
    }, []);

    // Record to import history once when the import completes.
    useEffect(() => {
        if (percent < 100 || recordedRef.current) return;
        recordedRef.current = true;
        const succeeded = uploadedFile.filter(f => f.status === 'uploaded').length;
        const sessionId = sessionStorage.getItem('tsmlt_import_id') || '';
        const record = {
            id: Date.now().toString(),
            sessionId,
            filename: exportImport.csvFilename || 'import.csv',
            rows: exportImport.fileCount,
            succeeded,
            date: new Date().toISOString(),
        };
        saveImportHistory([...loadImportHistory(), record]);
        onComplete?.();
    }, [percent]);

    const reversedFiles = useMemo(() => uploadedFile.slice(-10).reverse(), [uploadedFile]);

    const counts = useMemo(() => {
        const imported = uploadedFile.filter(f => f.status === 'uploaded').length;
        const failed = uploadedFile.length - imported;
        return { imported, failed };
    }, [uploadedFile]);

    return (
        <div className="max-w-375 mx-auto w-full">
            <h3 className="text-2xl font-semibold text-gray-900 mb-1">
                Import media from CSV file
            </h3>
            <p className="text-sm text-gray-500 mb-4">
                This tool allows you to import (or merge) Media data to your media library from a CSV.
            </p>
            <hr className="border-gray-200 my-4" />

            <div className="w-full bg-gray-200 rounded-full h-7.5 overflow-hidden mb-4">
                <div
                    className="bg-blue-600 h-full rounded-full transition-all duration-300 flex items-center justify-center text-white text-xs font-medium"
                    style={{ width: `${percent}%` }}
                >
                    {percent}%
                </div>
            </div>
            <hr className="border-gray-200 my-4" />

            {100 <= percent && (
                <button
                    type="button"
                    className="w-50 h-17.5 text-2xl! flex items-center justify-center gap-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 cursor-pointer transition-colors font-medium mx-auto mb-4"
                    onClick={() => handleExitImport()}
                >
                    <Link to="/mediaTable" className="text-white! no-underline">Done !! Exit Now</Link>
                </button>
            )}

            {currentFile && (
                <p className="text-sm text-gray-700 mb-2">
                    Uploading: <span className="text-green-600">{currentFile}</span>
                </p>
            )}
            <hr className="border-gray-200 my-4" />

            {reversedFiles.length ? (
                <div className="border border-gray-300 rounded-lg overflow-hidden">
                    <div className="flex items-center justify-between px-4 py-2.5 bg-gray-50 border-b border-gray-200">
                        <span className="text-sm font-medium text-gray-700">
                            Imported (<span className="text-green-600">{counts.imported}</span>)
                        </span>
                        <div className="flex items-center gap-3">
                            {counts.failed > 0 && (
                                <span className="text-sm font-medium text-red-600">
                                    Failed ({counts.failed})
                                </span>
                            )}
                            {uploadedFile.length > 10 && (
                                <span className="text-xs text-gray-400">showing last 10</span>
                            )}
                        </div>
                    </div>
                    <div className="h-100 overflow-auto px-4">
                    {reversedFiles.map((item) => (
                        <div key={item.id} className="flex items-center gap-3 py-3 border-b border-gray-100 last:border-0">
                            {item.url ? (
                                <div className="w-10 h-10 rounded-full overflow-hidden bg-gray-100 shrink-0 flex items-center justify-center">
                                    <MediaThumbnail
                                        url={item.url}
                                        fileName={item.url}
                                        alt=""
                                        className="w-full h-full object-cover"
                                        iconClassName="w-5 h-5 text-gray-400"
                                    />
                                </div>
                            ) : (
                                <div className="w-10 h-10 rounded-full bg-gray-200 shrink-0 flex items-center justify-center text-gray-400 text-xs">N/A</div>
                            )}
                            <div className="min-w-0">
                                <a
                                    target="_blank"
                                    href={item.url}
                                    className={`text-sm font-medium no-underline ${
                                        'uploaded' === item.status
                                            ? item.message ? 'text-amber-600' : 'text-green-600'
                                            : 'skipped' === item.status || 'not_found' === item.status || 'not_allowed' === item.status
                                                ? 'text-amber-600'
                                                : 'text-red-600'
                                    }`}
                                >
                                    {item.url ? getFileNameFromURL(item.url) : `Row ID(${item.id})`}
                                </a>
                                <p className="text-xs text-gray-500 mt-0.5">
                                    {'uploaded' === item.status && (item.message ? `Imported (with warning): ${item.message}` : 'Successfully imported')}
                                    {'skipped' === item.status && `Skipped: ${item.message ?? 'no actionable data in this row'}`}
                                    {'not_found' === item.status && `No matching attachment: ${item.message ?? `ID ${item.id}`}`}
                                    {'not_allowed' === item.status && `Not allowed: ${item.message ?? 'this file type is disabled in settings'}`}
                                    {('uploaded' !== item.status && 'skipped' !== item.status && 'not_found' !== item.status && 'not_allowed' !== item.status) && `CSV ID(${item.id}) : Import failed${item.message ? ` — ${item.message}` : ''}`}
                                </p>
                            </div>
                        </div>
                    ))}
                    </div>
                </div>
            ) : ''}
        </div>
    );
}

export default ImportInfo;
