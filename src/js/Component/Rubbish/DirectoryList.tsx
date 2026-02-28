
interface DirectoryItem {
    total_items: number;
    counted: number;
}

interface DirectoryListProps {
    dirEntries: [string, DirectoryItem][];
    skip: string[];
    onExclude: (key: string) => void;
    onRescan: (key: string) => void;
    loading: boolean;
}

export const trimPath = (fullPath: string): string => {
    const marker = 'wp-content/';
    const idx = fullPath.indexOf(marker);
    return idx !== -1 ? fullPath.slice(idx) : fullPath;
};

export default function DirectoryList({ dirEntries, skip, onExclude, onRescan, loading }: DirectoryListProps) {
    if (loading) {
        return (
            <div className="flex items-center justify-center h-full">
                <svg className="w-8 h-8 animate-spin text-blue-600" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                </svg>
            </div>
        );
    }

    if (dirEntries.length === 0) {
        return (
            <h5 className="text-[15px] font-semibold text-red-600 text-center py-8">
                Directory will search in the next schedule. Please be patient
            </h5>
        );
    }

    return (
        <div className="space-y-2">
            {dirEntries.map(([key, item]) => {
                const skippedItem = skip.includes(key);
                return (
                    <div key={key} className={`flex items-center justify-between py-3 px-3 border-b border-gray-100 last:border-0 ${skippedItem ? 'opacity-20' : ''}`}>
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-medium text-gray-900 truncate">{trimPath(key)}</p>
                            <p className="text-xs text-gray-500 mt-0.5">
                                {item.total_items === 0
                                    ? "This directory will be scanned again according to the schedule."
                                    : <span className="text-blue-600">Scanned {item.counted} items of {item.total_items} items</span>
                                }
                            </p>
                        </div>
                        <div className="flex items-center gap-2 flex-shrink-0 ml-4">
                            <button
                                type="button"
                                className="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700 cursor-pointer transition-colors"
                                onClick={() => onExclude(key)}
                            >
                                Exclude from Bulk Scan
                            </button>
                            <button
                                type="button"
                                className="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700 cursor-pointer transition-colors"
                                onClick={() => onRescan(key)}
                            >
                                Re-Execute in Schedule
                            </button>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
