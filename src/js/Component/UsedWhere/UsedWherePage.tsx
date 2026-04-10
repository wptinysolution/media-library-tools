import { useEffect, useState, useCallback, useRef } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { usedWhereScanBatch, getUsedWhereResults, getUsedWhereStatus, clearUsedWhereScan, usedWhereBulkDelete, usedWhereTrash, usedWhereUntrash, getUsedWhereTrashed } from "@/js/Utils/Data";
import ProgressBar from "@/js/Component/Common/ProgressBar";
import Pagination from "@/js/Component/Common/Pagination";
import SearchInput from "@/js/Component/Common/SearchInput";
import Modal from "@/js/Component/Common/Modal";

type FilterTab = 'used' | 'unused' | 'trash';

const tabs: { key: FilterTab; label: string }[] = [
    { key: 'unused', label: 'Unused' },
    { key: 'used', label: 'Used' },
    { key: 'trash', label: 'Trash' },
];

export default function UsedWherePage() {
    const { filter: filterParam, page: pageParam } = useParams<{ filter?: string; page?: string }>();
    const navigate = useNavigate();

    const activeFilter: FilterTab = filterParam === 'used' ? 'used' : filterParam === 'trash' ? 'trash' : 'unused';
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

    // Bulk delete state (unused and trash tabs).
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
    const [isDeleting, setIsDeleting] = useState(false);
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [showTrashModal, setShowTrashModal] = useState(false);

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
        setSelectedIds(new Set());
        try {
            if (filter === 'trash') {
                // Load trash items from WordPress DB
                const result = await getUsedWhereTrashed() as any;
                setUsages(result.items || []);
                setTotalUsages(result.total || 0);
            } else {
                // Load used/unused items
                const result = await getUsedWhereResults({
                    limit: 10,
                    offset: (page - 1) * 10,
                    filter,
                    search,
                }) as any;
                setUsages(result.usages || []);
                setTotalUsages(result.total || 0);
            }
            setCurrentPage(page);
        } catch (error) {
            console.error('Error loading results:', error);
        } finally {
            setIsLoading(false);
        }
    }, [activeFilter, searchQuery]);

    const handleTabChange = (filter: FilterTab) => {
        setExpandedId(null);
        setSelectedIds(new Set());
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
            setSelectedIds(new Set());
            setSearchInput('');
            setSearchQuery('');
            // Navigate to base route to ensure clean state
            navigate('/usedWhere/unused');
        } catch (error) {
            console.error('Error clearing results:', error);
        }
    };

    // ── Bulk delete ──────────────────────────────────────────────────────────

    const toggleSelect = (id: number) => {
        setSelectedIds(prev => {
            const next = new Set(prev);
            next.has(id) ? next.delete(id) : next.add(id);
            return next;
        });
    };

    const toggleSelectAll = () => {
        if (selectedIds.size === usages.length) {
            setSelectedIds(new Set());
        } else {
            setSelectedIds(new Set(usages.map((u: any) => u.attachment_id)));
        }
    };

    const handleMoveToTrash = () => {
        if (selectedIds.size === 0) return;
        setShowTrashModal(true);
    };

    const confirmMoveToTrash = async () => {
        setShowTrashModal(false);
        setIsDeleting(true);
        try {
            const ids = Array.from(selectedIds);
            await usedWhereTrash(ids);
            setUsages(prev => prev.filter(u => !selectedIds.has(u.attachment_id)));
            setTotalUsages(prev => prev - selectedIds.size);
            setSelectedIds(new Set());
        } catch (error) {
            console.error('Error moving to trash:', error);
        } finally {
            setIsDeleting(false);
        }
    };

    const handleBulkDelete = () => {
        if (selectedIds.size === 0) return;
        setShowDeleteModal(true);
    };

    const handleRestoreFromTrash = async () => {
        if (selectedIds.size === 0) return;
        setIsDeleting(true);
        try {
            await usedWhereUntrash(Array.from(selectedIds));
            setUsages(prev => prev.filter(u => !selectedIds.has(u.attachment_id)));
            setTotalUsages(prev => prev - selectedIds.size);
            setSelectedIds(new Set());
        } catch (error) {
            console.error('Error restoring from trash:', error);
        } finally {
            setIsDeleting(false);
        }
    };

    const confirmBulkDelete = async () => {
        setShowDeleteModal(false);
        setIsDeleting(true);
        try {
            await usedWhereBulkDelete(Array.from(selectedIds));
            setUsages(prev => prev.filter(u => !selectedIds.has(u.attachment_id)));
            setTotalUsages(prev => prev - selectedIds.size);
            setSelectedIds(new Set());
        } catch (error) {
            console.error('Error deleting attachments:', error);
        } finally {
            setIsDeleting(false);
        }
    };

    // Load status on mount.
    useEffect(() => {
        loadStatus();
    }, []);

    // Load results when filter, page, or search changes.
    // For Used/Unused tabs: only load if scan has been completed (processed > 0)
    // For Trash tab: always load from DB (trash data is persistent)
    useEffect(() => {
        // Trash tab always loads from DB
        if (activeFilter === 'trash') {
            loadResults(currentPageFromUrl, activeFilter, searchQuery);
        } else if (scanProgress.processed > 0) {
            // Used/Unused only load if scan has been completed
            loadResults(currentPageFromUrl, activeFilter, searchQuery);
        }
    }, [activeFilter, currentPageFromUrl, searchQuery, loadResults, scanProgress.processed]);

    const scanPercent = scanProgress.total > 0
        ? Math.round((scanProgress.processed / scanProgress.total) * 100)
        : 0;

    const isPreScan = scanProgress.processed === 0 && !isScanning;

    const displayTotalPages = Math.ceil(totalUsages / 10);

    const totalPages = displayTotalPages;
    const allSelected = usages.length > 0 && selectedIds.size === usages.length;
    const someSelected = selectedIds.size > 0 && !allSelected;

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
                <button
                    type="button"
                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors"
                    onClick={handleClear}
                >
                    Clear Results
                </button>

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

            {/* Bulk action toolbar — unused and trash tabs only */}
            {(activeFilter === 'unused' || activeFilter === 'trash') && usages.length > 0 && !isLoading && (
                <div className="flex items-center gap-3 px-4 py-2.5 bg-white border-b border-gray-200">
                    <label className="flex items-center gap-2 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            className="w-4 h-4 rounded border-gray-300 text-blue-600 cursor-pointer"
                            checked={allSelected}
                            ref={el => { if (el) el.indeterminate = someSelected; }}
                            onChange={toggleSelectAll}
                        />
                        <span className="text-sm text-gray-600">
                            {selectedIds.size > 0 ? `${selectedIds.size} selected` : 'Select all'}
                        </span>
                    </label>
                    {selectedIds.size > 0 && activeFilter === 'unused' && (
                        <button
                            type="button"
                            disabled={isDeleting}
                            onClick={handleMoveToTrash}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-md hover:bg-amber-100 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer transition-colors"
                        >
                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Move {selectedIds.size} to Trash
                        </button>
                    )}
                    {selectedIds.size > 0 && activeFilter === 'trash' && (
                        <>
                            <button
                                type="button"
                                disabled={isDeleting}
                                onClick={handleRestoreFromTrash}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-md hover:bg-emerald-100 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer transition-colors"
                            >
                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 15L3 9m0 0l6-6m-6 6h12a6 6 0 010 12h-3" />
                                </svg>
                                Restore {selectedIds.size} Image{selectedIds.size !== 1 ? 's' : ''}
                            </button>
                            <button
                                type="button"
                                disabled={isDeleting}
                                onClick={handleBulkDelete}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer transition-colors"
                            >
                                {isDeleting ? (
                                    <>
                                        <svg className="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                        Deleting...
                                    </>
                                ) : (
                                    <>
                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Delete {selectedIds.size} permanently
                                    </>
                                )}
                            </button>
                        </>
                    )}
                </div>
            )}

            {/* Results */}
            <div className="bg-white rounded-b-lg border border-t-0 border-gray-200 p-4 relative">
                {/* Delete overlay */}
                {isDeleting && (
                    <div className="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-white/80 backdrop-blur-[2px] rounded-b-lg">
                        <svg className="w-8 h-8 animate-spin text-red-500" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        <span className="text-sm font-medium text-gray-600">Deleting images…</span>
                    </div>
                )}
                {isLoading && activeFilter !== 'trash' ? (
                    <div className="flex items-center justify-center py-16">
                        <div className="flex flex-col items-center gap-3 text-gray-400">
                            <svg className="w-8 h-8 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            <span className="text-sm">Loading…</span>
                        </div>
                    </div>
                ) : usages.length === 0 ? (
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
                                    onClick={startScan}
                                    disabled={isScanning}
                                >
                                    Scan All Posts
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
                                                    : 'Click "Scan All Posts" first to detect image usage.')
                                                : (scanProgress.processed > 0
                                                    ? 'No images found in use. Your media library may contain orphaned files!'
                                                    : 'Click "Scan All Posts" to detect where images are used on your site.')}
                                </p>
                            </>
                        )}
                    </div>
                ) : (
                    <div className="space-y-3">
                        {usages.map((usage) => {
                            const isExpanded = expandedId === usage.attachment_id;
                            const posts: any[] = usage.posts || [];
                            const isSelected = selectedIds.has(usage.attachment_id);

                            return (
                                <div
                                    key={usage.attachment_id}
                                    className={`bg-white rounded-lg border overflow-hidden hover:shadow-md transition-shadow ${
                                        isSelected ? 'border-blue-400 ring-1 ring-blue-300' : 'border-gray-200'
                                    }`}
                                >
                                    {/* Main row */}
                                    <div
                                        className={`flex items-center gap-4 p-4 ${posts.length > 0 ? 'cursor-pointer' : ''}`}
                                        onClick={() => posts.length > 0 && setExpandedId(isExpanded ? null : usage.attachment_id)}
                                    >
                                        {/* Checkbox for unused and trash tabs */}
                                        {(activeFilter === 'unused' || activeFilter === 'trash') && (
                                            <div
                                                className="shrink-0"
                                                onClick={(e) => { e.stopPropagation(); toggleSelect(usage.attachment_id); }}
                                            >
                                                <input
                                                    type="checkbox"
                                                    className="w-4 h-4 rounded border-gray-300 text-blue-600 cursor-pointer"
                                                    checked={isSelected}
                                                    onChange={() => toggleSelect(usage.attachment_id)}
                                                    onClick={(e) => e.stopPropagation()}
                                                />
                                            </div>
                                        )}

                                        {activeFilter === 'used' && (
                                            posts.length > 0 ? (
                                                <svg
                                                    className={`w-4 h-4 shrink-0 text-gray-400 transition-transform ${isExpanded ? 'rotate-90' : ''}`}
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                >
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                                </svg>
                                            ) : (
                                                <div className="w-4 h-4 shrink-0" />
                                            )
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

                                        <div className="shrink-0 flex flex-col items-start gap-3 text-xs">
                                            <span className="inline-flex items-center px-2 py-1 font-medium text-gray-700 bg-gray-100 rounded">
                                                ID: #{usage?.attachment_id}
                                            </span>
                                            {activeFilter !== 'trash' && (
                                                <>
                                                    {usage.usage_count > 0 ? (
                                                        <div className={`flex items-center`}>
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
                                                        </div>
                                                    ) : (
                                                        <span className="inline-flex items-center px-2 py-1 font-medium text-red-700 bg-red-50 rounded">
                                                            No uses found
                                                        </span>
                                                    )}
                                                </>
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
                                onPageChange={(page) => navigate(`/usedWhere/${activeFilter}/page/${page}`)}
                            />
                        )}
                    </div>
                )}
            </div>

            {/* Move to Trash confirmation modal */}
            <Modal
                isOpen={showTrashModal}
                onClose={() => setShowTrashModal(false)}
                title={
                    <div className="flex items-center gap-2">
                        <div className="flex items-center justify-center w-8 h-8 rounded-full bg-amber-100 shrink-0">
                            <svg className="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </div>
                        <h3 className="text-lg font-semibold text-gray-900 m-0!">Move to Trash</h3>
                    </div>
                }
                maxWidth="max-w-[480px]"
                closeOnBackdrop={false}
                footer={
                    <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200">
                        <button
                            type="button"
                            className="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors"
                            onClick={() => setShowTrashModal(false)}
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            className="px-5 py-2 text-sm font-medium text-white bg-amber-600 rounded-md hover:bg-amber-700 cursor-pointer transition-colors"
                            onClick={confirmMoveToTrash}
                        >
                            Move {selectedIds.size} Image{selectedIds.size !== 1 ? 's' : ''} to Trash
                        </button>
                    </div>
                }
            >
                <div className="px-6 py-5">
                    <p className="text-base text-gray-700 mt-0!">
                        Move <strong>{selectedIds.size} image{selectedIds.size !== 1 ? 's' : ''}</strong> to Trash?
                    </p>
                    <p className="text-base text-gray-500 mt-2 mb-0!">
                        After moving images to Trash and clearing the cache, you should review your entire website. If any images appear missing or broken, restore them from the Trash. Once everything looks fine, you can permanently delete the images from the Trash tab.
                    </p>
                </div>
            </Modal>

            {/* Bulk delete confirmation modal */}
            <Modal
                isOpen={showDeleteModal}
                onClose={() => setShowDeleteModal(false)}
                title={
                    <div className="flex items-center gap-2">
                        <div className="flex items-center justify-center w-8 h-8 rounded-full bg-red-100 shrink-0">
                            <svg className="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                            </svg>
                        </div>
                        <h3 className="text-lg font-semibold text-gray-900 m-0!">
                            {activeFilter === 'trash' ? 'Delete Images Permanently' : 'Delete Unused Images'}
                        </h3>
                    </div>
                }
                maxWidth="max-w-[520px]"
                closeOnBackdrop={false}
                footer={
                    <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200">
                        <button
                            type="button"
                            className="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors"
                            onClick={() => setShowDeleteModal(false)}
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            className="px-5 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 cursor-pointer transition-colors"
                            onClick={confirmBulkDelete}
                        >
                            Yes, Delete {selectedIds.size} Image{selectedIds.size !== 1 ? 's' : ''}
                        </button>
                    </div>
                }
            >
                <div className="px-6 py-5 space-y-4">
                    <p className="text-sm text-gray-700 mt-0!">
                        You are about to permanently delete <strong>{selectedIds.size} image{selectedIds.size !== 1 ? 's' : ''}</strong> from your media library.
                    </p>

                    {activeFilter !== 'trash' && (
                        <div className="bg-amber-50 border border-amber-200 rounded-md px-4 py-3">
                            <p className="text-sm font-semibold text-amber-800 m-0! mb-1">
                                ⚠ Before deleting, please manually verify these images are truly unused.
                            </p>
                            <p className="text-xs text-amber-700 m-0!">
                                Our scan detects usage in posts, pages, and common custom fields — but some themes or plugins may reference images in ways that cannot be automatically detected.
                            </p>
                        </div>
                    )}

                    <div className="bg-red-50 border border-red-200 rounded-md px-4 py-3">
                        <p className="text-xs text-red-700 m-0!">
                            <strong>Disclaimer:</strong> Deleted files cannot be recovered. If you delete an image that is still in use somewhere on your site, it will result in broken images. We are not responsible for any loss of data or broken content resulting from this action. Proceed at your own risk.
                        </p>
                    </div>
                </div>
            </Modal>
        </div>
    );
}
