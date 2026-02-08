import React, { useEffect } from "react";

import * as Types from "@/js/Utils/actionType";

import MainHeader from "@/js/Component/MainHeader";

import { initialState } from "@/js/Utils/reducer";
import { useStateValue } from "@/js/Utils/StateProvider";

function ExportImportRoot() {

    const [stateValue, dispatch] = useStateValue();

    const resetExportImport = async () => {
        await dispatch({
            type: Types.EXPORT_IMPORT,
            exportImport: initialState.exportImport,
        });
    };

    useEffect(() => {
        resetExportImport();
    }, []);

    return (
        <>
            <MainHeader/>
            <div className="min-h-screen bg-gray-50">
                <div className="flex items-center justify-center p-24">
                    <div className="flex flex-wrap gap-4 justify-center p-12">
                        <a
                            href="#/export"
                            className="w-[200px] h-[70px] text-2xl flex items-center justify-center gap-2 border border-blue-600 text-blue-600 rounded-md hover:bg-blue-50 transition-colors no-underline font-medium"
                        >
                            Export
                        </a>
                        <a
                            href="#/import"
                            className="w-[200px] h-[70px] text-2xl flex items-center justify-center gap-2 border border-blue-600 text-blue-600 rounded-md hover:bg-blue-50 transition-colors no-underline font-medium"
                        >
                            Import
                        </a>
                    </div>
                </div>
            </div>
        </>
    );
}
export default ExportImportRoot;
