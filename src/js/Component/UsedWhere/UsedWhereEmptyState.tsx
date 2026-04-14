interface UsedWhereEmptyStateProps {
    isPreScan: boolean;
    activeFilter: 'used' | 'unused' | 'trash';
    searchQuery: string;
    scanProgress: { processed: number; total: number };
    isScanning: boolean;
    onStartScan: () => void;
}

export default function UsedWhereEmptyState({
    isPreScan,
    activeFilter,
    searchQuery,
    scanProgress,
    isScanning,
    onStartScan,
}: UsedWhereEmptyStateProps) {
    return (
        <div className="text-center py-12">
            {isPreScan && activeFilter !== 'trash' ? (
                <>
                    <svg className="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <p className="text-gray-500 text-sm mb-4">Run a scan to see results.</p>
                    <button
                        type="button"
                        className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer transition-colors"
                        onClick={onStartScan}
                        disabled={isScanning}
                    >
                        Scan Media Usage
                    </button>
                </>
            ) : (
                <>
                    <svg className="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p className="text-gray-500 text-sm">
                        {activeFilter === 'trash'
                            ? 'No images in Trash. Move unused images here first.'
                            : searchQuery
                                ? `No images found matching "${searchQuery}".`
                                : activeFilter === 'unused'
                                    ? (scanProgress.processed > 0
                                        ? 'All images are attached to posts. No unused images found!'
                                        : 'Click "Scan Media Usage " first to detect image usage.')
                                    : (scanProgress.processed > 0
                                        ? 'No images found in use. Your media library may contain orphaned files!'
                                        : 'Click "Scan Media Usage " to detect where images are used on your site.')}
                    </p>
                </>
            )}
        </div>
    );
}
