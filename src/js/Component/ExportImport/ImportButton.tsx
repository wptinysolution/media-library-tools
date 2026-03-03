import { useStore } from "@/js/Utils/store";
import ImportInfo from "./ImportInfo";
import UploadCsv from "./UploadCsv";

function ImportButton() {
    const { exportImport, setExportImport, setGeneralData } = useStore();

    const isImport = exportImport.isImport;

    const handleImport = () => {
        if (!tsmltParams.hasExtended) {
            setGeneralData({ openProModal: true });
            return;
        }
        setExportImport({
            isImport: true,
            runImporter: false,
            runExporter: false,
            mediaFiles: [],
            fileCount: 0,
            percent: 0,
            totalPage: 0,
        });
    };

    return (
        <div className="min-h-screen bg-gray-50">
            <div className="max-w-3xl mx-auto px-6 py-8">
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-gray-900 m-0!">CSV Import</h1>
                    <p className="text-sm text-gray-500 mt-1">Import and update media library data from a CSV file.</p>
                </div>

                {/* Info notice */}
                <div className="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mb-6 text-sm text-amber-800">
                    <p className="m-0!">If import fails, try importing in smaller batches — performance depends on your server capacity.</p>
                    <p className="mt-1 m-0!">
                        Accepted column headers:{' '}
                        <strong>ID, slug, url, rename_to, title, caption, description, alt_text, custom_meta:_key</strong>
                    </p>
                </div>

                <div className="bg-white rounded-lg border border-gray-200 p-10">
                    {isImport ? (
                        <>
                            {exportImport.runImporter ? (
                                <ImportInfo />
                            ) : (
                                <div className="flex flex-col justify-center">
                                    <UploadCsv />
                                </div>
                            )}
                        </>
                    ) : (
                        <div className="text-center">
                            <div className="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg className="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                            </div>
                            <h3 className="text-lg font-medium text-gray-900 mb-2">Import Media from CSV</h3>
                            <p className="text-sm text-gray-500 mb-6">
                                Upload a CSV file to update your media library data in bulk.
                            </p>
                            <button
                                type="button"
                                className="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer transition-colors"
                                onClick={handleImport}
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                Start Import
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

export default ImportButton;
