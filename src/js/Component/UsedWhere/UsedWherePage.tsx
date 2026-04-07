import { useEffect, useState, useCallback, useRef } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { usedWhereScanBatch, getUsedWhereResults, getUsedWhereStatus, clearUsedWhereScan } from "@/js/Utils/Data";
import ProgressBar from "@/js/Component/Common/ProgressBar";
import Pagination from "@/js/Component/Common/Pagination";
import SearchInput from "@/js/Component/Common/SearchInput";

type FilterTab = 'used' | 'unused';

const tabs: { key: FilterTab; label: string }[] = [
    { key: 'unused', label: 'Unused' },
    { key: 'used', label: 'Used' },
];

export default function UsedWherePage() {
    const { filter: filterParam, page: pageParam } = useParams<{ filter?: string; page?: string }>();
    const navigate = useNavigate();

    const activeFilter: FilterTab = 'used' === filterParam ? 'used' : 'unused';
    const currentPageFromUrl = parseInt(pageParam || '1', 10);

    const [isScanning, setIsScanning] = useState(false);
    const [scanProgress, setScanProgress] = useState({ processed: 0, total: 0 });
    const [usages, setUsages] = useState<any[]>([]);
    const [totalUsages, setTotalUsages] = useState(0);
    const [currentPage, setCurrentPage] = useState(currentPageFromUrl);
    const [isLoading, setIsLoading] = useState(false);
    const [expandedId, setExpandedId] = useState<number | null>(null);
    const [searchInput, setSearchInput] = useState('');
    const [searchQuery, setSearchQuery] = useState('');
    const debounceRef = useRef<ReturnType<typeof setTimeout>>(undefined);

    const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const val = e.target.value;
        setSearchInput(val);
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            setSearchQuery(val);
            // Reset pagination on search.
            navigate(`/usedWhere/${activeFilter}`);
        }, 500);
    };

    const handleSearchClear = () => {
        setSearchInput('');
        setSearchQuery('');
        navigate(`/usedWhere/${activeFilter}`);
    };

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

    const loadResults = useCallback(async (page = 1, filter: FilterTab = activeFilter, search: string = searchQuery) => {
        setIsLoading(true);
        try {
            const result = await getUsedWhereResults({
                limit: 10,
                offset: (page - 1) * 10,
                filter,
                search,
            }) as any;
            setUsages(result.usages || []);
            setTotalUsages(result.total || 0);
            setCurrentPage(page);
        } catch (error) {
            console.error('Error loading results:', error);
        } finally {
            setIsLoading(false);
        }
    }, [activeFilter, searchQuery]);

    const handleTabChange = (filter: FilterTab) => {
        setExpandedId(null);
        navigate(`/usedWhere/${filter}`);
    };

    const startScan = async () => {
        setIsScanning(true);
        setScanProgress({ processed: 0, total: 0 });
        let offset = 0;
        let complete = false;

        try {
            while (!complete) {
                const response = await usedWhereScanBatch({
                    offset,
                    batch_size: 20,
                }) as any;
                const result = response.data || response;
                offset = result.processed;
                complete = result.complete;
                setScanProgress({ processed: result.processed, total: result.total });
            }

            setIsScanning(false);
            await loadStatus();
            await loadResults(1, activeFilter, searchQuery);
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

    // Load status on mount.
    useEffect(() => {
        loadStatus();
    }, []);

    // Load results when filter, page, or search changes.
    useEffect(() => {
        loadResults(currentPageFromUrl, activeFilter, searchQuery);
    }, [activeFilter, currentPageFromUrl, searchQuery]);

    const scanPercent = scanProgress.total > 0
        ? Math.round((scanProgress.processed / scanProgress.total) * 100)
        : 0;

    const totalPages = Math.ceil(totalUsages / 10);

    return (
        <div className="mx-auto px-6 py-8 min-h-screen bg-gray-50">
            {/* Header */}
            <div className="mb-8">
                <div className="flex items-center gap-3 mb-2">
                    <h1 className="text-2xl font-semibold text-gray-900 m-0!">Media Usage Tracker</h1>
                    <span className="inline-flex items-center px-2.5 py-0.5 text-xs font-medium text-amber-800 bg-amber-100 rounded-full">
                        Free Feature
                    </span>
                </div>
                <p className="text-sm text-gray-500">Track where media file are used across your website — posts, pages, custom post types, and more.</p>
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

                <div className="ml-auto">
                    <SearchInput
                        placeholder="Search images..."
                        value={searchInput}
                        onChange={handleSearchChange}
                        onClear={handleSearchClear}
                    />
                </div>
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

            {/* Filter tabs */}
            <div className="flex bg-white border-b border-gray-200">
                {tabs.map((tab) => (
                    <button
                        key={tab.key}
                        type="button"
                        className={`px-5 py-3 text-sm font-medium border-b-2 transition-colors cursor-pointer ${
                            activeFilter === tab.key
                                ? 'border-blue-600 text-blue-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                        }`}
                        onClick={() => handleTabChange(tab.key)}
                    >
                        {tab.label}
                        {!isLoading && activeFilter === tab.key && (
                            <span className="ml-2 inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium text-gray-600 bg-gray-100 rounded-full">
                                {totalUsages}
                            </span>
                        )}
                    </button>
                ))}
            </div>

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
                            {searchQuery
                                ? `No images found matching "${searchQuery}".`
                                : activeFilter === 'unused'
                                    ? (scanProgress.processed > 0
                                        ? 'All images are attached to posts. No unused images found!'
                                        : 'Click "Scan All Posts" first to detect image usage.')
                                    : (scanProgress.processed > 0
                                        ? 'No images found in use. Your media library may contain orphaned files!'
                                        : 'Click "Scan All Posts" to detect where images are used on your site.')}
                        </p>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {usages.map((usage) => {
                            const isExpanded = expandedId === usage.attachment_id;
                            const posts: any[] = usage.posts || [];

                            return (
                                <div key={usage.attachment_id} className="bg-white rounded-lg border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
                                    {/* Main row */}
                                    <div
                                        className={`flex items-center gap-4 p-4 ${posts.length > 0 ? 'cursor-pointer' : ''}`}
                                        onClick={() => posts.length > 0 && setExpandedId(isExpanded ? null : usage.attachment_id)}
                                    >
                                        {posts.length > 0 ? (
                                            <svg
                                                className={`w-4 h-4 shrink-0 text-gray-400 transition-transform ${isExpanded ? 'rotate-90' : ''}`}
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            >
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                            </svg>
                                        ) : (
                                            <div className="w-4 h-4 shrink-0" />
                                        )}

                                        <div className="shrink-0 w-14 h-14 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center">
                                            {usage.url ? (
                                                <img src={usage.url} alt={usage.title} className="w-full h-full object-cover" />
                                            ) : (
                                                <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            )}
                                        </div>

                                        <div className="flex-1 min-w-0">
                                            <h3 className="text-sm mt-0! mb-1.5 font-semibold text-gray-900 truncate">
                                                {usage.title || `(ID: ${usage.attachment_id})`}
                                            </h3>
                                            <p className="text-xs mt-0! mb-0! text-gray-500 truncate">{usage.url}</p>
                                        </div>

                                        <div className="shrink-0 flex items-center gap-3 text-xs">
                                            {usage.usage_count > 0 ? (
                                                <>
                                                    <span className="inline-flex items-center px-2 py-1 font-medium text-gray-700 bg-gray-100 rounded">
                                                        {usage.usage_count} usage{usage.usage_count !== 1 ? 's' : ''}
                                                    </span>
                                                    <span className="inline-flex items-center px-2 py-1 font-medium text-blue-700 bg-blue-50 rounded">
                                                        {usage.used_in_posts} post{usage.used_in_posts !== 1 ? 's' : ''}
                                                    </span>
                                                    {Object.entries(usage.usage_by_type || {}).map(([type, count]: [string, any]) => (
                                                        <span key={type} className="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 bg-emerald-50 rounded">
                                                            {type}: {count}
                                                        </span>
                                                    ))}
                                                </>
                                            ) : (
                                                <span className="inline-flex items-center px-2 py-1 font-medium text-red-700 bg-red-50 rounded">
                                                    No uses found
                                                </span>
                                            )}
                                        </div>
                                    </div>

                                    {isExpanded && posts.length > 0 && (
                                        <div className="border-t border-gray-100 bg-gray-50 px-4 py-3">
                                            <div className="space-y-1.5">
                                                {posts.map((post: any, idx: number) => (
                                                    <div key={idx} className="flex items-center gap-3 text-sm py-2 px-3 bg-white rounded border border-gray-100">
                                                        <span className="flex-1 min-w-0 truncate font-medium text-gray-800">
                                                            {post.post_title || `(ID: ${post.post_id})`}
                                                        </span>
                                                        <span className="shrink-0 inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium text-purple-700 bg-purple-50 rounded">
                                                            {post.post_type}
                                                        </span>
                                                        <span className="shrink-0 inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium text-amber-700 bg-amber-50 rounded">
                                                            {post.usage_type}
                                                        </span>
                                                        {post.post_link && (
                                                            <a
                                                                href={post.post_link}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="shrink-0 text-xs text-blue-600 hover:text-blue-700"
                                                                onClick={(e) => e.stopPropagation()}
                                                            >
                                                                View
                                                            </a>
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            );
                        })}

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
