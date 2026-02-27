import { useStore } from "@/js/Utils/store";
import ImportInfo from "./ImportInfo";
import UploadCsv from "./UploadCsv";

function ImportButton() {
    const { exportImport, setExportImport, setGeneralData } = useStore();

    const isImport = exportImport.isImport;

    const handleImport = (type: string) => {
        if (!tsmltParams.hasExtended) {
            setGeneralData({ openProModal: true });
            return;
        }
        setExportImport({
            isImport: 'import' === type,
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
                <div className="p-24 rounded-md shadow-sm flex items-center">
                    <div className="p-12 w-full">
                        <h5 className="border border-gray-200 px-4 py-3 mb-3 text-[13px] text-red-600 text-center rounded">
                            If file import fails, Try importing in small batches at a time. Its depend in your server capacity.
                            <br />
                            CSV File Accepted Column Header <strong>( ID, slug, url, rename_to, title, caption, description, alt_text, custom_meta:_custom_meta_key, custom_meta:_meta_key_2, custom_meta:_meta_key_3 )</strong>
                        </h5>

                        {isImport && (
                            <>
                                {exportImport.runImporter ? <ImportInfo /> : ''}
                                <div className="flex flex-wrap justify-center">
                                    <UploadCsv />
                                </div>
                            </>
                        )}
                        {!isImport && (
                            <div className="flex justify-center gap-4">
                                <button
                                    type="button"
                                    className="w-50 h-17.5 text-2xl! flex items-center justify-center gap-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 cursor-pointer transition-colors font-medium"
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
    );
}

export default ImportButton;
