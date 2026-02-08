import React, { useState } from "react";

import { useStateValue } from "@/js/Utils/StateProvider";

import * as Types from "@/js/Utils/actionType";

import { getMedia } from "@/js/Utils/Data";

import MainHeader from "@/js/Component/MainHeader";

import ExportModalCSV from "./ExportModalCSV";

function ExportButton() {

    const [stateValue, dispatch] = useStateValue();

    const [percent, setPercent] = useState(0);

    const [isModalOpen, setModalOpen] = useState(false);

    const isExport = stateValue.exportImport.isExport;

    const handleExport = async (type) => {
        if (!tsmltParams.hasExtended) {
            dispatch({
                type: Types.GENERAL_DATA,
                generalData: {
                    ...stateValue.generalData,
                    openProModal: true,
                },
            });
            return;
        }

        let exportImport = {
            ...stateValue.exportImport,
            isExport: 'export' === type,
            isImport: false,
            runImporter: false,
            runExporter: false,
            mediaFiles: [],
            fileCount: 0,
            percent: 0,
            totalPage: 0,
        };

        await dispatch({ type: Types.EXPORT_IMPORT, exportImport });

        let allMedia = [];
        let page = 1;
        let totalPages = 1;

        try {
            do {
                const query = {
                    ...stateValue.mediaData.postQuery,
                    paged: page,
                };

                const res = await getMedia(query);

                const data = res?.posts || [];
                totalPages = res?.total_page || 1;

                allMedia = [...allMedia, ...data];

                const progress = Math.round((page / totalPages) * 100);
                setPercent(progress);

                page++;
            } while (page <= totalPages);
            dispatch({
                type: Types.EXPORT_IMPORT,
                exportImport: {
                    ...exportImport,
                    mediaFiles: allMedia,
                    fileCount: allMedia.length,
                    percent: 100,
                    totalPage: totalPages,
                },
            });
        } catch (error) {
            console.error('Export failed:', error);
        }
    };

    return (
        <>
            <MainHeader/>
            <div className="min-h-screen bg-gray-50">
                <div className="p-24 rounded-md shadow-sm">
                    <div className="flex items-center flex-wrap gap-4">
                        {isExport && (
                            <div className="w-full bg-gray-200 rounded-full h-[30px] overflow-hidden">
                                <div
                                    className="bg-blue-600 h-full rounded-full transition-all duration-300 flex items-center justify-center text-white text-xs font-medium"
                                    style={{ width: `${percent}%` }}
                                >
                                    {percent}%
                                </div>
                            </div>
                        )}
                        <div className="flex items-center gap-4 w-full justify-center">
                            {percent >= 100 ? (
                                <button
                                    type="button"
                                    className="w-[200px] h-[70px] text-2xl flex items-center justify-center gap-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 cursor-pointer transition-colors font-medium"
                                    onClick={() => setModalOpen(true)}
                                >
                                    Download Csv
                                </button>
                            ) : (
                                <button
                                    type="button"
                                    className="w-[200px] h-[70px] text-2xl flex items-center justify-center gap-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 cursor-pointer transition-colors font-medium"
                                    onClick={() => handleExport('export')}
                                >
                                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                    </svg>
                                    Run Exporter
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            </div>
            {isModalOpen ? <ExportModalCSV isModalOpen={isModalOpen} setModalOpen={setModalOpen}/> : null}
        </>
    );
}
export default ExportButton;
