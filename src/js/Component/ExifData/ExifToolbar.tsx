import ProgressBar from "@/js/Component/Common/ProgressBar";

interface ExifToolbarProps {
    selectedCount: number;
    totalImages: number;
    allSelected: boolean;
    isIndeterminate: boolean;
    isStripping: boolean;
    stripProgress: { processed: number; total: number };
    bulkAction: string;
    onToggleSelectAll: () => void;
    onBulkActionChange: (action: string) => void;
    onBulkApply: () => void;
}

export default function ExifToolbar({
    selectedCount,
    totalImages,
    allSelected,
    isIndeterminate,
    isStripping,
    stripProgress,
    bulkAction,
    onToggleSelectAll,
    onBulkActionChange,
    onBulkApply,
}: ExifToolbarProps) {
    return (
        <div className="bg-white rounded-t-xl border border-gray-200">
            <div className="flex items-center gap-3 px-5 py-3">
                {/* Select all */}
                <label className="flex items-center gap-2 cursor-pointer select-none">
                    <input
                        type="checkbox"
                        className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                        ref={(el) => { if (el) el.indeterminate = isIndeterminate; }}
                        checked={allSelected}
                        onChange={onToggleSelectAll}
                    />
                    <span className="text-sm text-gray-600">
                        {selectedCount > 0 ? `${selectedCount} selected` : 'Select all'}
                    </span>
                </label>

                {/* Divider */}
                <div className="w-px h-5 bg-gray-200" />

                {/* Bulk actions */}
                <select
                    className="h-8 px-2.5 text-sm border border-gray-300 rounded-md bg-white text-gray-700 cursor-pointer focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    value={bulkAction}
                    onChange={(e) => onBulkActionChange(e.target.value)}
                >
                    <option value="" disabled>Bulk Actions</option>
                    <option value="read_exif">Read EXIF Data</option>
                    <option value="add_exif">Add EXIF Data</option>
                    <option value="edit_exif">Edit EXIF Data</option>
                    <option value="delete_exif">Delete EXIF Data</option>
                </select>
                <button
                    type="button"
                    className="h-8 px-3.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                    onClick={onBulkApply}
                    disabled={!bulkAction || selectedCount === 0 || isStripping}
                >
                    Apply
                </button>

                {/* Count */}
                <div className="ml-auto flex items-center gap-2">
                    <span className="text-sm text-gray-500">{totalImages} images</span>
                </div>
            </div>

            {/* Strip progress */}
            {isStripping && (
                <div className="px-5 py-3 border-t border-gray-100">
                    <p className="text-sm text-gray-600 mb-2 mt-0!">
                        Deleting EXIF data... {stripProgress.processed} / {stripProgress.total}
                    </p>
                    <ProgressBar percent={stripProgress.total > 0 ? Math.round((stripProgress.processed / stripProgress.total) * 100) : 0} />
                </div>
            )}
        </div>
    );
}
