import React from "react";

import { useStateValue } from "@/js/Utils/StateProvider";

import ImportInfo from "./ImportInfo";

import * as Types from "@/js/Utils/actionType";

import UploadCsv from "./UploadCsv";

import MainHeader from "@/js/Component/MainHeader";

function ImportButton() {

    const [stateValue, dispatch] = useStateValue();

    const isImport = stateValue.exportImport.isImport;

    const handleImport = async (type) => {
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
            isImport: 'import' === type,
            runImporter: false,
            runExporter: false,
            mediaFiles: [],
            fileCount: 0,
            percent: 0,
            totalPage: 0,
        };

        await dispatch({
            type: Types.EXPORT_IMPORT,
            exportImport: exportImport,
        });
    };

    return (
        <>
            <MainHeader/>
            <div className="min-h-screen bg-gray-50">
                <div className="p-24 rounded-md shadow-sm flex items-center">
                    <div className="p-12 w-full">
                        <h5 className="border border-gray-200 px-4 py-3 mb-3 text-[13px] text-red-600 text-center rounded">
                            If file import fails, Try importing in small batches at a time. Its depend in your server capacity.
                            <br/>
                            CSV File Accepted Column Header <strong>( ID, slug, url, rename_to, title, caption, description, alt_text, custom_meta:_custom_meta_key, custom_meta:_meta_key_2, custom_meta:_meta_key_3 )</strong>
                        </h5>

                        {isImport && (
                            <>
                                {stateValue.exportImport.runImporter ? <ImportInfo/> : ''}
                                <div className="flex flex-wrap justify-center">
                                    <UploadCsv/>
                                </div>
                            </>
                        )}
                        {!isImport && (
                            <div className="flex justify-center gap-4">
                                <button
                                    type="button"
                                    className="w-[200px] h-[70px] text-2xl flex items-center justify-center gap-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 cursor-pointer transition-colors font-medium"
                                    onClick={() => handleImport('import')}
                                >
                                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    CSV Import
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
export default ImportButton;
