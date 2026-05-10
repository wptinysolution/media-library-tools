import { useEffect, useState, useCallback, useRef } from "react";
import { useParams, useNavigate } from "react-router-dom";
import toast from "react-hot-toast";
import { startUsedWhereScan, cancelUsedWhereScan, acknowledgeUsedWhereScan, getUsedWhereResults, getUsedWhereStatus, clearUsedWhereScan, usedWhereBulkDelete, usedWhereTrash, usedWhereUntrash, getUsedWhereTrashed } from "@/js/Utils/Data";
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

    const [scanProgress, setScanProgress] = useState({ processed: 0, total: 0 });
    const [usages, setUsages] = useState<any[]>([]);
    const [totalUsages, setTotalUsages] = useState(0);
    const [currentPage, setCurrentPage] = useState(currentPageFromUrl);
    const [isLoading, setIsLoading] = useState(false);
    const [expandedId, setExpandedId] = useState<number | null>(null);
    const [searchInput, setSearchInput] = useState('');
    const [searchQuery, setSearchQuery] = useState('');
    const [perPage, setPerPage] = useState(() => {
        const saved = localStorage.getItem('mlt_used_where_per_page');
        return saved ? parseInt(saved, 10) || 10 : 10;
    });
    const debounceRef = useRef<ReturnType<typeof setTimeout>>(undefined);

    // Bulk delete state (unused and trash tabs).
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
    const [isDeleting, setIsDeleting] = useState(false);
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [showTrashModal, setShowTrashModal] = useState(false);
    // Reassurance modal shown right after the user kicks off a scan, telling
    // them the scan runs in the background and they can leave the tab.
    const [showStartModal, setShowStartModal] = useState(false);

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

    const loadResults = useCallback(async (page = 1, filter: FilterTab = activeFilter, search: string = searchQuery, limit: number = perPage) => {
        setIsLoading(true);
        setSelectedIds(new Set());
        try {
            if (filter === 'trash') {
                // Load trash items from WordPress DB with pagination and search
                const result = await getUsedWhereTrashed({
                    limit,
                    offset: (page - 1) * limit,
                    search,
                }) as any;
                setUsages(result.items || []);
                setTotalUsages(result.total || 0);
            } else {
                // Load used/unused items
                const result = await getUsedWhereResults({
                    limit,
                    offset: (page - 1) * limit,
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
    }, [activeFilter, searchQuery, perPage]);

    const handleTabChange = (filter: FilterTab) => {
        setExpandedId(null);
        setSelectedIds(new Set());
        if (filter === activeFilter) {
            // Same tab clicked again: URL won't change, so trigger a reload manually.
            // Don't clear usages first — keeps current results visible until reload finishes.
            if (filter === 'unused' && scanProgress.processed === 0) {
                return;
            }
            loadResults(1, filter, searchQuery);
            navigate(`/usedWhere/${filter}`);
            return;
        }
        setUsages([]);
        setTotalUsages(0);
        navigate(`/usedWhere/${filter}`);
    };

    // Cron-driven scan flow. The browser is a passive observer: it kicks off
    // the scan, then polls server status every few seconds. The actual work
    // happens in WP-Cron tick handlers, so closing the tab does not stop the
    // scan and reopening it picks up wherever the server has progressed to.
    const [scanState, setScanState] = useState<string>('idle'); // idle | queued | running | complete | cancelled | error
    const pollTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    // Surfaces an inline success/error card on first visit after a scan
    // terminates (complete / cancelled / error). Cleared when the user
    // dismisses the card or starts a new scan.
    const [completionNotice, setCompletionNotice] = useState<{
        kind: 'complete' | 'cancelled' | 'error';
        scanned: number;
        total: number;
        error?: string;
    } | null>(null);

    const isScanning = scanState === 'queued' || scanState === 'running';

    // Show the "scan finished" toast + inline card if the server says we
    // haven't been notified about this terminal state yet, then immediately
    // acknowledge so it doesn't fire again. Idempotent.
    const announceTerminalStateOnce = useCallback(async (status: any) => {
        if (!status || status.notified !== false) {
            return;
        }
        const state = String(status.state ?? '');
        const scanned = Number(status.scanned ?? 0);
        const total = Number(status.total ?? 0);
        const error = String(status.last_error ?? '');

        if (state === 'complete') {
            toast.success(
                total > 0
                    ? `Scan complete · checked ${scanned.toLocaleString()} of ${total.toLocaleString()} posts`
                    : 'Scan complete',
                { duration: 6000 }
            );
            setCompletionNotice({ kind: 'complete', scanned, total });
        } else if (state === 'cancelled') {
            toast('Scan was cancelled. Existing data is preserved.', { duration: 5000 });
            setCompletionNotice({ kind: 'cancelled', scanned, total });
        } else if (state === 'error') {
            toast.error(`Scan failed${error ? `: ${error}` : ''}`, { duration: 8000 });
            setCompletionNotice({ kind: 'error', scanned, total, error });
        } else {
            return; // not a terminal state we surface
        }

        try {
            await acknowledgeUsedWhereScan();
        } catch (e) {
            // Acknowledge is best-effort; if it fails, the worst case is we
            // re-show the toast on next mount, which is mildly annoying but
            // not broken.
            console.warn('Failed to acknowledge scan completion:', e);
        }
    }, []);

    // Stop any pending poll. Idempotent — safe to call when nothing is queued.
    const stopPolling = useCallback(() => {
        if (pollTimerRef.current) {
            clearTimeout(pollTimerRef.current);
            pollTimerRef.current = null;
        }
    }, []);

    // Single poll tick. Refreshes status, updates progress, and either
    // schedules the next tick or finalises (loads results, clears the timer).
    const pollOnce = useCallback(async () => {
        try {
            const status = await getUsedWhereStatus() as any;
            const state = String(status?.state ?? 'idle');
            setScanState(state);
            setScanProgress({
                processed: Number(status?.scanned ?? 0),
                total: Number(status?.total ?? 0),
            });

            if (state === 'queued' || state === 'running') {
                // Adaptive cadence: faster while running, slower while queued
                // (the first tick is waiting on cron and may take a few seconds).
                const delay = state === 'running' ? 2000 : 4000;
                pollTimerRef.current = setTimeout(pollOnce, delay);
                return;
            }

            // Terminal state — stop polling and surface the toast + inline
            // card if the user hasn't been notified yet. Results auto-reload
            // because the filter-effect re-fires when isScanning flips to
            // false (the effect's `loadResults` call is the single source of
            // truth for fetching the list — no race with a manual fetch).
            stopPolling();
            await announceTerminalStateOnce(status);
        } catch (error) {
            console.error('Error polling scan status:', error);
            // Back off on error rather than tight-loop. Next poll in 10s.
            pollTimerRef.current = setTimeout(pollOnce, 10000);
        }
    }, [stopPolling, announceTerminalStateOnce]);

    const startScan = async () => {
        // Optimistic UI: show queued state immediately so the user sees a
        // response to their click before the first poll fires.
        setScanState('queued');
        setScanProgress({ processed: 0, total: 0 });
        setUsages([]);
        setTotalUsages(0);
        setCurrentPage(1);
        setSelectedIds(new Set());
        setExpandedId(null);
        setCompletionNotice(null);

        // Surface the "you can leave this tab" reassurance modal once.
        // Users who've ticked "don't show again" skip it.
        if (localStorage.getItem('mlt_used_where_hide_start_modal') !== '1') {
            setShowStartModal(true);
        }

        try {
            const status = await startUsedWhereScan() as any;
            setScanState(String(status?.state ?? 'queued'));
            setScanProgress({
                processed: Number(status?.scanned ?? 0),
                total: Number(status?.total ?? 0),
            });
            stopPolling();
            pollTimerRef.current = setTimeout(pollOnce, 1500);
        } catch (error) {
            console.error('Error starting scan:', error);
            setScanState('error');
        }
    };

    const cancelScan = async () => {
        if (!confirm('Cancel the in-progress scan? Existing data is preserved — you can start a fresh scan later.')) {
            return;
        }
        try {
            const status = await cancelUsedWhereScan() as any;
            setScanState(String(status?.state ?? 'cancelled'));
            stopPolling();
        } catch (error) {
            console.error('Error cancelling scan:', error);
        }
    };

    // Cleanup poll timer on unmount.
    useEffect(() => {
        return () => stopPolling();
    }, [stopPolling]);

    const handleClear = async () => {
        if (!confirm('Are you sure you want to clear all scan results?')) {
            return;
        }
        try {
            stopPolling();
            await clearUsedWhereScan();
            setScanState('idle');
            setUsages([]);
            setTotalUsages(0);
            setScanProgress({ processed: 0, total: 0 });
            setCurrentPage(1);
            setSelectedIds(new Set());
            setSearchInput('');
            setSearchQuery('');
            setCompletionNotice(null);
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

    // On mount: read server-side scan state. The cron tick handler is the
    // source of truth — if it says a scan is running, attach the poller.
    // Reload, browser crash, role change, all converge to the same behaviour:
    // the UI reflects whatever the server reports.
    useEffect(() => {
        (async () => {
            try {
                const status = await getUsedWhereStatus() as any;
                const state = String(status?.state ?? 'idle');
                setScanState(state);
                setScanProgress({
                    processed: Number(status?.scanned ?? 0),
                    total: Number(status?.total ?? 0),
                });
                if (state === 'queued' || state === 'running') {
                    pollTimerRef.current = setTimeout(pollOnce, 1500);
                } else {
                    // Terminal state on first mount — fire the "scan finished"
                    // toast + inline card if the server hasn't seen us yet.
                    await announceTerminalStateOnce(status);
                }
            } catch (error) {
                console.error('Error loading status:', error);
            }
        })();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Load results when filter, page, or search changes.
    // Used tab: always loads (post-save and frontend-visit tracking work without full scan).
    // Unused tab: requires a *completed* scan — loading mid-scan would show
    //   not-yet-checked images as "unused", risking accidental deletion of
    //   images the scan hadn't reached yet.
    // Trash tab: always loads from DB (trash data is persistent).
    useEffect(() => {
        if (activeFilter === 'unused' && (scanProgress.processed === 0 || isScanning)) {
            // Clear stale results from a previous completed scan so the user
            // doesn't see old data while the new scan is still running.
            setUsages([]);
            setTotalUsages(0);
            return;
        }
        loadResults(currentPageFromUrl, activeFilter, searchQuery);
    }, [activeFilter, currentPageFromUrl, searchQuery, loadResults, scanProgress.processed, isScanning]);

    const scanPercent = scanProgress.total > 0
        ? Math.round((scanProgress.processed / scanProgress.total) * 100)
        : 0;

    const isPreScan = scanProgress.processed === 0 && !isScanning && activeFilter !== 'used';

    const totalPages = Math.ceil(totalUsages / perPage);
    const allSelected = usages.length > 0 && selectedIds.size === usages.length;
    const someSelected = selectedIds.size > 0 && !allSelected;

    return (
        <div className="mx-auto px-6 py-8 min-h-screen bg-gray-50">
            {/* Header */}
            <div className="mb-8">
                <div className="flex items-center gap-3 mb-2">
                    <h1 className="text-2xl font-semibold text-gray-900 m-0!">Media Usage Tracker</h1>
                    <span className="inline-flex items-center px-2.5 py-0.5 text-xs font-medium text-amber-800 bg-amber-100 rounded-full">
                        Experimental Feature
                    </span>
                </div>
                <p>
                    <span className="inline-flex items-center px-2.5 py-0.5 text-xs font-medium text-amber-800 bg-amber-100 rounded-full">
                        Experimental feature—please don’t delete any images before your final check.
                    </span>
                </p>
                <p className="text-sm text-gray-500">Track where media file are used across your website — posts, pages, custom post types, and more.</p>
            </div>

            {/* Actions bar */}
            <div className="flex items-center gap-3 px-4 py-3 border-b border-gray-200 bg-white rounded-t-lg">
                {isScanning ? (
                    <button
                        type="button"
                        className="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 cursor-pointer transition-colors"
                        onClick={cancelScan}
                    >
                        Cancel Scan
                    </button>
                ) : (
                    <button
                        type="button"
                        className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer transition-colors"
                        onClick={startScan}
                    >
                        {scanProgress.processed > 0 ? 'Re-scan' : 'Scan Media Usage '}
                    </button>
                )}
                <button
                    type="button"
                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors"
                    onClick={handleClear}
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
                        onChange={(e) => {
                            const newPerPage = parseInt(e.target.value, 10);
                            localStorage.setItem('mlt_used_where_per_page', String(newPerPage));
                            setPerPage(newPerPage);
                            setCurrentPage(1);
                            navigate(`/usedWhere/${activeFilter}`);
                        }}
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
                        onChange={handleSearchChange}
                        onClear={handleSearchClear}
                    />
                </div>
            </div>

            {/* Scan-completed notice — surfaced on first visit after a scan
                terminates while the user was away. Inline card complements
                the toast (which auto-dismisses), giving a durable signal
                that pre-existing data on the page is from a fresh run. */}
            {completionNotice && !isScanning && (
                <div className={`flex items-start gap-3 px-4 py-3 border-b ${
                    completionNotice.kind === 'complete'
                        ? 'bg-emerald-50 border-emerald-200'
                        : completionNotice.kind === 'cancelled'
                            ? 'bg-amber-50 border-amber-200'
                            : 'bg-red-50 border-red-200'
                }`}>
                    <div className={`shrink-0 mt-0.5 ${
                        completionNotice.kind === 'complete'
                            ? 'text-emerald-600'
                            : completionNotice.kind === 'cancelled'
                                ? 'text-amber-600'
                                : 'text-red-600'
                    }`}>
                        {completionNotice.kind === 'complete' ? (
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                        ) : completionNotice.kind === 'cancelled' ? (
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        ) : (
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                            </svg>
                        )}
                    </div>
                    <div className="flex-1 min-w-0">
                        <p className={`text-sm font-semibold m-0! leading-snug ${
                            completionNotice.kind === 'complete'
                                ? 'text-emerald-900'
                                : completionNotice.kind === 'cancelled'
                                    ? 'text-amber-900'
                                    : 'text-red-900'
                        }`}>
                            {completionNotice.kind === 'complete' && 'Scan complete'}
                            {completionNotice.kind === 'cancelled' && 'Scan was cancelled'}
                            {completionNotice.kind === 'error' && 'Scan failed'}
                        </p>
                        <p className={`text-xs mt-1 mb-0! leading-relaxed ${
                            completionNotice.kind === 'complete'
                                ? 'text-emerald-800'
                                : completionNotice.kind === 'cancelled'
                                    ? 'text-amber-800'
                                    : 'text-red-800'
                        }`}>
                            {completionNotice.kind === 'complete' && (
                                completionNotice.total > 0
                                    ? `Checked ${completionNotice.scanned.toLocaleString()} of ${completionNotice.total.toLocaleString()} posts. Results below are up to date.`
                                    : 'Results below are up to date.'
                            )}
                            {completionNotice.kind === 'cancelled' && 'Existing usage data is preserved. Start a new scan when you\'re ready.'}
                            {completionNotice.kind === 'error' && (completionNotice.error || 'Try clearing results and starting a new scan.')}
                        </p>
                    </div>
                    <button
                        type="button"
                        className="shrink-0 p-1 text-gray-400 hover:text-gray-600 cursor-pointer transition-colors"
                        onClick={() => setCompletionNotice(null)}
                        aria-label="Dismiss"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            )}

            {/* Scan progress */}
            {isScanning && (
                <div className="px-4 py-4 bg-white border-b border-gray-200">
                    <p className="text-sm text-gray-600 mb-2 mt-0!">
                        {scanState === 'queued'
                            ? 'Queued — waiting for the first batch to start...'
                            : `Scanning ${scanProgress.processed} / ${scanProgress.total} posts for image usage...`}
                    </p>
                    <ProgressBar
                        percent={scanPercent}
                        state={scanState === 'queued' ? 'queued' : 'active'}
                        label={scanState === 'queued' ? 'queued' : `${scanProgress.processed.toLocaleString()} / ${scanProgress.total.toLocaleString()} posts`}
                    />
                    <div className="mt-4 flex items-start gap-3 px-4 py-3 bg-gradient-to-r from-blue-50 to-indigo-50 border-l-4 border-blue-500 rounded-md shadow-sm">
                        <div className="relative shrink-0 mt-0.5">
                            <span className="absolute inset-0 rounded-full bg-blue-500 opacity-30 animate-ping" />
                            <svg className="relative w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <div className="flex-1 min-w-0">
                            <p className="text-sm font-semibold text-blue-900 m-0! leading-snug">
                                Running in the background — safe to close this tab.
                            </p>
                            <p className="text-xs text-blue-800 mt-1 mb-0! leading-relaxed">
                                You can close this tab or even your browser. The scan keeps progressing on the server and will pick up automatically when you come back.
                            </p>
                        </div>
                    </div>
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
                        {isScanning && activeFilter === 'unused' ? (
                            <>
                                <svg className="w-12 h-12 mx-auto text-blue-300 mb-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                                <p className="text-gray-700 text-sm font-medium mb-1">Scan in progress — Unused list will appear when it finishes.</p>
                                <p className="text-xs text-gray-500 mb-0!">
                                    Showing partial results now could mark images as unused before the scan has checked them. The progress bar above tracks how far we've gotten.
                                </p>
                            </>
                        ) : isPreScan && activeFilter !== 'trash' ? (
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

                                        <div className="mlt-meta-area shrink-0 flex flex-col items-start gap-3 text-xs">
                                            <span className="inline-flex items-center px-2 py-1 font-medium text-gray-700 bg-gray-100 rounded">
                                                ID: #{usage?.attachment_id}
                                            </span>
                                            {activeFilter !== 'trash' && (
                                                <>
                                                    {(() => {
                                                        // "Found in N ways" — distinct detection types that
                                                        // recorded this image. Always agrees with the type badges
                                                        // shown next to it (one badge = one way), so the headline
                                                        // never confuses the user about what the number means.
                                                        const waysFound = Object.keys(usage.usage_by_type || {}).length;
                                                        if (waysFound === 0) {
                                                            return (
                                                                <span className="inline-flex items-center px-2 py-1 font-medium text-red-700 bg-red-50 rounded">
                                                                    No uses found
                                                                </span>
                                                            );
                                                        }
                                                        // Every usage_type is a yes/no detection signal — the
                                                        // record_usage buffer dedupes by (attachment, post, type)
                                                        // so the per-type count is essentially always 1 per post.
                                                        // Hide the per-type badges in the summary row (too much
                                                        // visual noise) and reveal them as a CSS-only tooltip when
                                                        // the user hovers the "Found in N ways" pill.
                                                        const detectionTypes = Object.keys(usage.usage_by_type || {});
                                                        return (
                                                            <div className="flex items-center gap-1">
                                                                <span
                                                                    className="group relative inline-flex items-center px-2 py-1 font-medium text-gray-700 bg-gray-100 rounded cursor-help"
                                                                    onClick={(e) => e.stopPropagation()}
                                                                    tabIndex={0}
                                                                >
                                                                    Found in {waysFound} way{waysFound !== 1 ? 's' : ''}
                                                                    {/* Tooltip: appears on hover or keyboard focus.
                                                                        Positioned above the trigger so it never
                                                                        gets clipped by the row's overflow. */}
                                                                    <span
                                                                        role="tooltip"
                                                                        className="invisible group-hover:visible group-focus:visible opacity-0 group-hover:opacity-100 group-focus:opacity-100 transition-opacity duration-150 absolute bottom-full left-1/2 -translate-x-1/2 mb-2 z-20 px-3 py-2 min-w-[180px] bg-gray-900 text-white rounded-md shadow-lg pointer-events-none"
                                                                    >
                                                                        <span className="block text-[10px] font-semibold uppercase tracking-wide text-gray-300 mb-1">
                                                                            Detected via
                                                                        </span>
                                                                        <ul className="m-0 p-0 list-none space-y-0.5">
                                                                            {detectionTypes.map((type) => (
                                                                                <li key={type} className="flex items-center gap-1.5 text-xs">
                                                                                    <svg className="w-3 h-3 shrink-0 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" />
                                                                                    </svg>
                                                                                    <span className="font-medium">{type}</span>
                                                                                </li>
                                                                            ))}
                                                                        </ul>
                                                                        {/* Tooltip arrow */}
                                                                        <span className="absolute top-full left-1/2 -translate-x-1/2 -mt-px border-4 border-transparent border-t-gray-900" aria-hidden="true" />
                                                                    </span>
                                                                </span>
                                                                <span className="inline-flex items-center px-2 py-1 font-medium text-blue-700 bg-blue-50 rounded">
                                                                    {usage.used_in_posts} post{usage.used_in_posts !== 1 ? 's' : ''}
                                                                </span>
                                                            </div>
                                                        );
                                                    })()}
                                                </>
                                            )}
                                        </div>
                                    </div>

                                    {isExpanded && posts.length > 0 && (() => {
                                        // Group by post_id to avoid duplicate rows.
                                        const grouped = posts.reduce((acc: Record<number, any>, post: any) => {
                                            const id = post.post_id;
                                            if (!acc[id]) {
                                                acc[id] = { ...post, usage_types: [post.usage_type] };
                                            } else {
                                                acc[id].usage_types.push(post.usage_type);
                                            }
                                            return acc;
                                        }, {});
                                        const uniquePosts = Object.values(grouped) as any[];

                                        return (
                                            <div className="border-t border-gray-100 bg-gray-50 px-4 py-3">
                                                <div className="space-y-1.5">
                                                    {uniquePosts.map((post: any, idx: number) => (
                                                        <div key={idx} className="flex items-center gap-3 text-sm py-2 px-3 bg-white rounded border border-gray-100">
                                                            {post.post_link ? (
                                                                <a
                                                                    href={post.post_link}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    className="flex-1 min-w-0 truncate font-medium text-blue-600 hover:text-blue-700 hover:underline"
                                                                    onClick={(e) => e.stopPropagation()}
                                                                >
                                                                    {post.post_title || `(ID: ${post.post_id})`}
                                                                </a>
                                                            ) : (
                                                                <span className="flex-1 min-w-0 truncate font-medium text-gray-800">
                                                                    {post.post_title || `(ID: ${post.post_id})`}
                                                                </span>
                                                            )}
                                                            <span className="shrink-0 inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium text-purple-700 bg-purple-50 rounded">
                                                                {post.post_type}
                                                            </span>
                                                            {post.usage_types.map((type: string, i: number) => (
                                                                <span key={i} className="shrink-0 inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium text-amber-700 bg-amber-50 rounded">
                                                                    {type}
                                                                </span>
                                                            ))}
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        );
                                    })()}
                                </div>
                            );
                        })}

                        {totalPages > 1 && (
                            <Pagination
                                currentPage={currentPage}
                                totalPages={totalPages}
                                totalPosts={totalUsages}
                                postsPerPage={perPage}
                                onPageChange={(page) => navigate(`/usedWhere/${activeFilter}/page/${page}`)}
                            />
                        )}
                    </div>
                )}
            </div>

            {/* Scan-started reassurance modal — appears once after the user
                clicks Start. The scan is cron-driven, so this tells users
                they don't have to babysit the page. Has a "don't show again"
                preference persisted in localStorage. */}
            <Modal
                isOpen={showStartModal}
                onClose={() => setShowStartModal(false)}
                title={
                    <div className="flex items-center gap-2">
                        <div className="flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 shrink-0">
                            <svg className="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <h3 className="text-lg font-semibold text-gray-900 m-0!">Scan started</h3>
                    </div>
                }
                maxWidth="max-w-[520px]"
                closeOnBackdrop={true}
                footer={
                    <div className="flex items-center justify-between gap-3 px-6 py-4 border-t border-gray-200">
                        <label className="flex items-center gap-2 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                className="w-4 h-4 rounded border-gray-300 text-blue-600 cursor-pointer"
                                onChange={(e) => {
                                    if (e.target.checked) {
                                        localStorage.setItem('mlt_used_where_hide_start_modal', '1');
                                    } else {
                                        localStorage.removeItem('mlt_used_where_hide_start_modal');
                                    }
                                }}
                            />
                            <span className="text-xs text-gray-600">Don't show this again</span>
                        </label>
                        <button
                            type="button"
                            className="px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer transition-colors"
                            onClick={() => setShowStartModal(false)}
                        >
                            Got it
                        </button>
                    </div>
                }
            >
                <div className="px-6 py-5 space-y-4">
                    <div className="flex items-start gap-3 px-4 py-3 bg-blue-50 border-l-4 border-blue-500 rounded-md">
                        <svg className="w-5 h-5 mt-0.5 shrink-0 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                        </svg>
                        <p className="text-sm font-semibold text-blue-900 m-0! leading-snug">
                            The scan is now running in the background.
                        </p>
                    </div>

                    <div>
                        <p className="text-sm text-gray-700 mt-0! mb-2">You can:</p>
                        <ul className="text-sm text-gray-700 space-y-1.5 list-disc pl-5 m-0!">
                            <li>Close this tab or your entire browser.</li>
                            <li>Switch to a different page in WordPress admin.</li>
                            <li>Come back later — progress will resume automatically.</li>
                        </ul>
                    </div>

                    <div className="px-4 py-3 bg-gray-50 border border-gray-200 rounded-md">
                        <p className="text-xs text-gray-600 m-0! leading-relaxed">
                            <strong className="text-gray-900">Tip:</strong> The scan runs on your server's schedule (WordPress Cron). On low-traffic sites, you may need an occasional visit to your site to keep the scan progressing.
                        </p>
                    </div>
                </div>
            </Modal>

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
