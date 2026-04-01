import { useEffect, useState, useCallback } from "react";
import { usedWhereScanBatch, getUsedWhereResults, getUsedWhereStatus, clearUsedWhereScan } from "@/js/Utils/Data";
import ProgressBar from "@/js/Component/Common/ProgressBar";
import Pagination from "@/js/Component/Common/Pagination";

export default function UsedWherePage() {
    const [isScanning, setIsScanning] = useState(false);
    const [scanProgress, setScanProgress] = useState({ processed: 0, total: 0 });
    const [usages, setUsages] = useState<any[]>([]);
    const [totalUsages, setTotalUsages] = useState(0);
    const [currentPage, setCurrentPage] = useState(1);
    const [isLoading, setIsLoading] = useState(false);

    const loadStatus = useCallback(async () => {
        try {
            const status = await getUsedWhereStatus() as any;
            setScanProgress({
                processed: status.scanned || 0,
                total: status.total || 0,
            });
        } catch (error) {
            console.error('Error loading status:', error);
        }
    }, []);

    const loadResults = useCallback(async (page = 1) => {
        setIsLoading(true);
        try {
            const result = await getUsedWhereResults({
                limit: 10,
                offset: (page - 1) * 10,
            }) as any;
            setUsages(result.usages || []);
            setTotalUsages(result.total || 0);
            setCurrentPage(page);
        } catch (error) {
            console.error('Error loading results:', error);
        } finally {
            setIsLoading(false);
        }
    }, []);

    const startScan = async () => {
        setIsScanning(true);
        setScanProgress({ processed: 0, total: 0 });
        let offset = 0;
        let complete = false;

        try {
            while (!complete) {
                const result = await usedWhereScanBatch({
                    offset,
                    batch_size: 20,
                }) as any;
                offset = result.processed;
                complete = result.complete;
                setScanProgress({ processed: result.processed, total: result.total });
            }

            setIsScanning(false);
            await loadStatus();
            await loadResults(1);
        } catch (error) {
            console.error('Error during scan:', error);
            setIsScanning(false);
        }
    };

    const handleClear = async () => {
        if (!confirm('Are you sure you want to clear all scan results?')) {
            return;
        }
        try {
            await clearUsedWhereScan();
            setUsages([]);
            setTotalUsages(0);
            setScanProgress({ processed: 0, total: 0 });
            setCurrentPage(1);
        } catch (error) {
            console.error('Error clearing results:', error);
        }
    };

    useEffect(() => {
        loadStatus();
        loadResults(1);
    }, []);

    const scanPercent = scanProgress.total > 0
        ? Math.round((scanProgress.processed / scanProgress.total) * 100)
        : 0;

    const totalPages = Math.ceil(totalUsages / 10);

    return (
        <div className="mx-auto px-6 py-8 min-h-screen bg-gray-50">
            {/* Header */}
            <div className="mb-8">
                <div className="flex items-center gap-3 mb-2">
                    <h1 className="text-2xl font-semibold text-gray-900 m-0!">Image Usage Tracker</h1>
                    <span className="inline-flex items-center px-2.5 py-0.5 text-xs font-medium text-amber-800 bg-amber-100 rounded-full">
                        Free Feature
                    </span>
                </div>
                <p className="text-sm text-gray-500">Track where images are used across your website. Both backend scanning and frontend passive detection.</p>
            </div>

            {/* Actions bar */}
            <div className="flex items-center gap-3 px-4 py-3 border-b border-gray-200 bg-white rounded-t-lg">
                <button
                    type="button"
                    className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer transition-colors disabled:opacity-50"
                    onClick={startScan}
                    disabled={isScanning}
                >
                    {isScanning ? 'Scanning...' : (scanProgress.processed > 0 ? 'Re-scan' : 'Scan All Posts')}
                </button>
                {scanProgress.processed > 0 && !isScanning && (
                    <button
                        type="button"
                        className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors"
                        onClick={handleClear}
                    >
                        Clear Results
                    </button>
                )}
            </div>

            {/* Scan progress */}
            {isScanning && (
                <div className="px-4 py-4 bg-white border-b border-gray-200">
                    <p className="text-sm text-gray-600 mb-2 mt-0!">
                        Scanning posts... {scanProgress.processed} / {scanProgress.total}
                    </p>
                    <ProgressBar percent={scanPercent} />
                </div>
            )}

            {/* Results */}
            <div className="bg-white rounded-b-lg border border-t-0 border-gray-200 p-4">
                {isLoading ? (
                    <div className="text-center py-12 text-gray-500">Loading...</div>
                ) : usages.length === 0 ? (
                    <div className="text-center py-12">
                        <svg className="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p className="text-gray-500 text-sm">
                            {scanProgress.processed > 0
                                ? 'No images found in use. Your media library may contain orphaned files!'
                                : 'Click "Scan All Posts" to detect where images are used on your site.'}
                        </p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {usages.map((usage) => (
                            <div key={usage.attachment_id} className="bg-white rounded-lg border border-gray-200 p-4 hover:shadow-md transition-shadow">
                                <div className="flex items-start gap-4">
                                    {/* Image thumbnail */}
                                    <div className="shrink-0 w-20 h-20 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center">
                                        {usage.url ? (
                                            <img src={usage.url} alt={usage.title} className="w-full h-full object-cover" />
                                        ) : (
                                            <svg className="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        )}
                                    </div>

                                    {/* Details */}
                                    <div className="flex-1 min-w-0">
                                        <h3 className="text-sm font-semibold text-gray-900 truncate mb-1">
                                            {usage.title || `(ID: ${usage.attachment_id})`}
                                        </h3>
                                        <p className="text-xs text-gray-500 truncate mb-2">{usage.url}</p>

                                        {/* Usage stats */}
                                        <div className="flex items-center gap-4 text-xs">
                                            <span className="flex items-center gap-1">
                                                <span className="font-medium text-gray-700">{usage.usage_count}</span>
                                                <span className="text-gray-500">total usages</span>
                                            </span>
                                            <span className="flex items-center gap-1">
                                                <span className="font-medium text-gray-700">{usage.used_in_posts}</span>
                                                <span className="text-gray-500">post{usage.used_in_posts !== 1 ? 's' : ''}</span>
                                            </span>

                                            {/* Usage type badges */}
                                            <div className="flex gap-1">
                                                {Object.entries(usage.usage_by_type).map(([type, count]: [string, any]) => (
                                                    <span key={type} className="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium text-blue-700 bg-blue-50 rounded">
                                                        {type}: {count}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                    </div>

                                    {/* Edit link */}
                                    <a
                                        href={`/wp-admin/post.php?post=${usage.attachment_id}&action=edit`}
                                        className="shrink-0 inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-600 hover:text-blue-700 transition-colors"
                                    >
                                        Edit
                                    </a>
                                </div>
                            </div>
                        ))}

                        {totalPages > 1 && (
                            <Pagination
                                currentPage={currentPage}
                                totalPages={totalPages}
                                totalPosts={totalUsages}
                                postsPerPage={10}
                                onPageChange={(page) => loadResults(page)}
                            />
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
