import { useStore } from "@/js/Utils/store";

function formatBytes(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

export default function DuplicateHeader() {
    const { duplicateData } = useStore();

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 bg-white border-b border-gray-200">
            <div className="flex items-center gap-4">
                <h2 className="text-base font-semibold text-gray-900 m-0!">Duplicate Files</h2>
                {duplicateData.scanned > 0 && (
                    <div className="flex items-center gap-3 text-sm text-gray-500">
                        <span className="inline-flex items-center gap-1.5 px-2.5 py-1 bg-blue-50 text-blue-700 rounded-full text-xs font-medium">
                            {duplicateData.totalGroups} {duplicateData.totalGroups === 1 ? 'group' : 'groups'}
                        </span>
                        {duplicateData.potentialSavings > 0 && (
                            <span className="inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-50 text-amber-700 rounded-full text-xs font-medium">
                                {formatBytes(duplicateData.potentialSavings)} can be saved
                            </span>
                        )}
                        <span className="text-xs text-gray-400">
                            {duplicateData.scanned} / {duplicateData.totalAttachments} scanned
                        </span>
                    </div>
                )}
            </div>
        </div>
    );
}
