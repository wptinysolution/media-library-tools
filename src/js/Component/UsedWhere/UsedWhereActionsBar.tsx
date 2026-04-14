import { useCallback } from "react";
import SearchInput from "@/js/Component/Common/SearchInput";

interface UsedWhereActionsBarProps {
    isScanning: boolean;
    scanProgress: { processed: number; total: number };
    onStartScan: () => void;
    onClear: () => void;
    perPage: number;
    onPerPageChange: (value: number) => void;
    searchInput: string;
    onSearchChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
    onSearchClear: () => void;
}

export default function UsedWhereActionsBar({
    isScanning,
    scanProgress,
    onStartScan,
    onClear,
    perPage,
    onPerPageChange,
    searchInput,
    onSearchChange,
    onSearchClear,
}: UsedWhereActionsBarProps) {
    return (
        <div className="flex items-center gap-3 px-4 py-3 border-b border-gray-200 bg-white rounded-t-lg">
            <button
                type="button"
                className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer transition-colors disabled:opacity-50"
                onClick={onStartScan}
                disabled={isScanning}
            >
                {isScanning ? 'Scanning...' : (scanProgress.processed > 0 ? 'Re-scan' : 'Scan Media Usage ')}
            </button>
            <button
                type="button"
                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors"
                onClick={onClear}
            >
                Clear Results
            </button>

            <div className="flex items-center gap-2">
                <label htmlFor="perPage" className="text-sm font-medium text-gray-700">
                    Per page:
                </label>
                <select
                    id="perPage"
                    value={perPage}
                    onChange={(e) => onPerPageChange(parseInt(e.target.value, 10))}
                    className="w-20 px-3 py-2 text-sm border border-gray-300 rounded-md hover:border-gray-400 cursor-pointer transition-colors"
                >
                    <option value={10}>10</option>
                    <option value={25}>25</option>
                    <option value={50}>50</option>
                    <option value={100}>100</option>
                </select>
            </div>

            <div className="ml-auto">
                <SearchInput
                    placeholder="Search images..."
                    value={searchInput}
                    onChange={onSearchChange}
                    onClear={onSearchClear}
                />
            </div>
        </div>
    );
}
