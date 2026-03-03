import { useEffect } from "react";
import { useStore, initialExportImport } from "@/js/Utils/store";

function ExportImportRoot() {
    const { setExportImport } = useStore();

    const resetExportImport = () => {
        setExportImport(initialExportImport);
    };

    useEffect(() => {
        resetExportImport();
    }, []);

    return (
        <div className="min-h-screen bg-gray-50">
                <div className="flex items-center justify-center p-24">
                    <div className="flex flex-wrap gap-4 justify-center p-12">
                        <a
                            href="#/export"
                            className="w-50 h-17.5 text-2xl flex items-center justify-center gap-2 border border-blue-600 text-blue-600 rounded-md hover:bg-blue-50 transition-colors no-underline font-medium"
                        >
                            Export
                        </a>
                        <a
                            href="#/import"
                            className="w-50 h-17.5 text-2xl flex items-center justify-center gap-2 border border-blue-600 text-blue-600 rounded-md hover:bg-blue-50 transition-colors no-underline font-medium"
                        >
                            Import
                        </a>
                    </div>
                </div>
        </div>
    );
}

export default ExportImportRoot;
