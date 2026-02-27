import React, { useEffect, useState, useMemo } from "react";
import { useStore } from "@/js/Utils/store";
import { importOneByOne } from "@/js/Utils/Data";
import { Link } from "react-router-dom";
import type { ExportImportSettings } from "@/js/Utils/store";

interface UploadedItem {
    id: string | number;
    url: string;
    status: string;
}

function ImportInfo() {
    const { exportImport } = useStore();

    const [percent, setPercent] = useState(0);
    const [uploadedFile, setUploadedFile] = useState<UploadedItem[]>([]);
    const [currentFile, setCurrentFile] = useState<string | null>(null);

    const totalMedia = exportImport.totalPage;
    const settings = exportImport.settings as ExportImportSettings;

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

            if (item['url']?.toString().length || settings.importUpdateContent) {
                setCurrentFile(item['url'] as string);
                const importedItem = await importOneByOne({ media: item, settings: exportImport.settings }) as { data: UploadedItem };
                setUploadedFile(prev => [...prev, importedItem.data]);
                setCurrentFile(null);
            }
        }

        setPercent(100);
    };

    useEffect(() => {
        uploadMediaSequentially();
    }, []);

    const reversedFiles = useMemo(() => [...uploadedFile].reverse(), [uploadedFile]);

    return (
        <div className="max-w-[1500px] mx-auto w-full">
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
                    className="w-50 h-17.5 text-2xl flex items-center justify-center gap-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 cursor-pointer transition-colors font-medium mx-auto mb-4"
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
                <div className="h-[400px] overflow-auto px-4 border border-gray-300 rounded-lg">
                    {reversedFiles.map((item) => (
                        <div key={item.id} className="flex items-center gap-3 py-3 border-b border-gray-100 last:border-0">
                            <img
                                src={item.url}
                                alt=""
                                className="w-10 h-10 rounded-full object-cover flex-shrink-0"
                            />
                            <div className="min-w-0">
                                <a
                                    target="_blank"
                                    href={item.url}
                                    className={`text-sm font-medium no-underline ${
                                        'uploaded' === item.status ? 'text-green-600' : 'text-red-600'
                                    }`}
                                >
                                    {item.url && getFileNameFromURL(item.url)}
                                </a>
                                <p className="text-xs text-gray-500 mt-0.5">
                                    {'uploaded' === item.status ? 'Successfully upload' : `CSV ID(${item.id}) : Upload Failed`}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
            ) : ''}
        </div>
    );
}

export default ImportInfo;
