import React, { useEffect, useState } from "react";

import { useStore } from "@/js/Utils/store";

import { importOneByOne } from "@/js/Utils/Data";

import { Link } from "react-router-dom";

function ImportInfo() {

    const { exportImport } = useStore();

    const [percent, setPercent] = useState(0);

    const [uploadedFile, setUploadedFile] = useState([]);

    const [currentFile, setCurrentFile] = useState(null);

    const totalMedia = exportImport.totalPage;

    const getFileNameFromURL = (url) => {
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

    const uploadMediaRecursively = async (mediaFiles) => {
        const countPercent = Math.floor(100 * (totalMedia - mediaFiles?.length) / totalMedia);
        await setPercent((prevState) => countPercent);

        if (mediaFiles?.length <= 0) {
            return;
        }

        const firstObject = mediaFiles.shift();
        if (firstObject['url']?.length || exportImport.settings.importUpdateContent) {
            setCurrentFile(firstObject['url']);
            const importedItem = await importOneByOne({ media: firstObject, settings: exportImport.settings });
            await setUploadedFile((prevState) => [
                ...prevState,
                importedItem.data
            ]);
            setCurrentFile(null);
        }

        await uploadMediaRecursively(mediaFiles);
    };

    useEffect(() => {
        uploadMediaRecursively(exportImport.mediaFiles);
    }, []);

    return (
        <div className="max-w-[1500px] mx-auto w-full">
            <h3 className="text-2xl font-semibold text-gray-900 mb-1">
                Import media from CSV file
            </h3>
            <p className="text-sm text-gray-500 mb-4">
                This tool allows you to import (or merge) Media data to your media library from a CSV.
            </p>
            <hr className="border-gray-200 my-4" />

            {/* Progress bar */}
            <div className="w-full bg-gray-200 rounded-full h-[30px] overflow-hidden mb-4">
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
                    className="w-[200px] h-[70px] text-2xl flex items-center justify-center gap-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 cursor-pointer transition-colors font-medium mx-auto mb-4"
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

            {uploadedFile.length ? (
                <div className="h-[400px] overflow-auto px-4 border border-gray-300 rounded-lg">
                    {uploadedFile.slice().reverse().map((item) => (
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
