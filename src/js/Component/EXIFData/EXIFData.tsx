import { useEffect, useState, useCallback, useRef } from "react";
import { useParams, useNavigate } from "react-router-dom";
import {
    exifGetList,
    exifReadSingle,
    exifSyncSingle,
    exifGetMissing,
    exifBulkSyncStart,
    exifBulkSyncBatch,
    exifBulkSyncCancel,
    notifications,
} from "@/js/Utils/Data";
import Pagination from "@/js/Component/Common/Pagination";
import SearchInput from "@/js/Component/Common/SearchInput";
import ProLabel from "@/js/Component/Badges/ProLabel";

// ─── Types ────────────────────────────────────────────────────────────────────

type FilterTab = 'all' | 'has_exif' | 'no_exif' | 'missing';

interface ExifData {
    date_original: string;
    date_digitized: string;
    camera_make: string;
    camera_model: string;
    source: 'exif' | 'wp_meta' | 'filesystem' | '';
}

interface ExifItem {
    attachment_id: number;
    title: string;
    url: string;
    wp_date: string;
    mime_type: string;
    file_type: string; // 'image' | 'video' | 'audio' | 'application' | 'other'
    exif: ExifData | null;
    has_exif: boolean;
}

interface SyncModalState {
    open: boolean;
    item: ExifItem | null;
    syncType: 'post_date' | 'post_modified' | 'both';
    loading: boolean;
    done: boolean;
    message: string;
    newWpDate: string;
}

// ─── Bulk Sync types ──────────────────────────────────────────────────────────

type BulkSyncFilter = 'all' | 'missing_exif' | 'date_mismatch';
type BulkSyncStatus = 'idle' | 'running' | 'cancelled' | 'done';

interface BulkSyncState {
    status: BulkSyncStatus;
    total: number;
    processed: number;
    synced: number;
    failed: number;
    skipped: number;
    message: string;
    complete: boolean;
}

interface BulkSyncOptions {
    filter: BulkSyncFilter;
    syncType: 'post_date' | 'post_modified' | 'both';
    dateFrom: string;
    dateTo: string;
}

// ─── Tab config ───────────────────────────────────────────────────────────────

const tabs: { key: FilterTab; label: string }[] = [
    { key: 'all', label: 'All Media' },
    { key: 'has_exif', label: 'Has Date' },
    { key: 'no_exif', label: 'Not Scanned' },
    { key: 'missing', label: 'Missing Date' },
];

// ─── Helpers ──────────────────────────────────────────────────────────────────

function formatEXIFData(raw: string): string {
    if (!raw) return '';
    const converted = raw.replace(/^(\d{4}):(\d{2}):(\d{2})/, '$1-$2-$3');
    const d = new Date(converted);
    if (isNaN(d.getTime())) return raw;
    return d.toLocaleString();
}

function getSourceLabel(source: string): string {
    if (source === 'exif') return 'EXIF';
    if (source === 'wp_meta') return 'image meta';
    if (source === 'filesystem') return 'file system';
    return '';
}

function getDateLabel(source: string): string {
    if (source === 'exif') return 'Date Taken';
    if (source === 'wp_meta') return 'Date Created';
    return 'File Date';
}

/** Icon for non-image file types */
function FileTypeIcon({ fileType }: { fileType: string }) {
    if (fileType === 'video') {
        return (
            <svg className="w-7 h-7 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 10l4.553-2.276A1 1 0 0121 8.677V15.32a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z" />
            </svg>
        );
    }
    if (fileType === 'audio') {
        return (
            <svg className="w-7 h-7 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
            </svg>
        );
    }
    if (fileType === 'application') {
        return (
            <svg className="w-7 h-7 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
        );
    }
    return (
        <svg className="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
        </svg>
    );
}

// ─── Main Component ───────────────────────────────────────────────────────────

export default function EXIFData() {
    const { filter: filterParam, page: pageParam } = useParams<{ filter?: string; page?: string }>();
    const navigate = useNavigate();

    const activeFilter: FilterTab = (['all', 'has_exif', 'no_exif', 'missing'].includes(filterParam || ''))
        ? (filterParam as FilterTab)
        : 'all';
    const currentPageFromUrl = parseInt(pageParam || '1', 10);

    const [items, setItems] = useState<ExifItem[]>([]);
    const [total, setTotal] = useState(0);
    const [currentPage, setCurrentPage] = useState(currentPageFromUrl);
    const [isLoading, setIsLoading] = useState(false);
    const [searchInput, setSearchInput] = useState('');
    const [searchQuery, setSearchQuery] = useState('');
    const [readingId, setReadingId] = useState<number | null>(null);
    const [syncModal, setSyncModal] = useState<SyncModalState>({
        open: false,
        item: null,
        syncType: 'post_date',
        loading: false,
        done: false,
        message: '',
        newWpDate: '',
    });

    const debounceRef = useRef<ReturnType<typeof setTimeout>>(undefined);
    const batchRunning = useRef(false);
    const PER_PAGE = 20;

    // Pro: Bulk Sync state.
    const hasPro = typeof window !== 'undefined' && !!(window as unknown as Record<string, unknown>)['tsmltParams'] &&
        !!((window as unknown as Record<string, unknown>)['tsmltParams'] as Record<string, unknown>)['hasExtended'];
    const [bulkOpts, setBulkOpts] = useState<BulkSyncOptions>({
        filter: 'all',
        syncType: 'post_date',
        dateFrom: '',
        dateTo: '',
    });
    const [bulkSync, setBulkSync] = useState<BulkSyncState>({
        status: 'idle',
        total: 0,
        processed: 0,
        synced: 0,
        failed: 0,
        skipped: 0,
        message: '',
        complete: true,
    });

    // ── Load results ──────────────────────────────────────────────────────────

    const loadResults = useCallback(async (
        page = 1,
        filter: FilterTab = activeFilter,
        search: string = searchQuery,
    ) => {
        setIsLoading(true);
        try {
            let result: Record<string, unknown>;
            if (filter === 'missing') {
                result = await exifGetMissing({ paged: page, per_page: PER_PAGE }) as Record<string, unknown>;
            } else {
                result = await exifGetList({ paged: page, per_page: PER_PAGE, search, filter }) as Record<string, unknown>;
            }
            setItems((result.items as ExifItem[]) || []);
            setTotal((result.total as number) || 0);
            setCurrentPage(page);
        } catch (err) {
            console.error('EXIF list error:', err);
        } finally {
            setIsLoading(false);
        }
    }, [activeFilter, searchQuery]);

    // ── Search debounce ───────────────────────────────────────────────────────

    const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const val = e.target.value;
        setSearchInput(val);
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            setSearchQuery(val);
            navigate(`/EXIFData/${activeFilter}`);
        }, 500);
    };

    const handleSearchClear = () => {
        setSearchInput('');
        setSearchQuery('');
        navigate(`/EXIFData/${activeFilter}`);
    };

    // ── Tab change ────────────────────────────────────────────────────────────

    const handleTabChange = (filter: FilterTab) => {
        navigate(`/EXIFData/${filter}`);
    };

    // ── Read date for single file ─────────────────────────────────────────────

    const handleReadSingle = async (item: ExifItem) => {
        setReadingId(item.attachment_id);
        try {
            const result = await exifReadSingle({ attachment_id: item.attachment_id }) as Record<string, unknown>;
            if (result.updated) {
                const hasDate = result.has_date as boolean;
                notifications(true, hasDate ? 'Date metadata read successfully.' : 'No date metadata found in this file.');
                await loadResults(currentPage, activeFilter, searchQuery);
            } else {
                notifications(false, 'Failed to read file date.');
            }
        } catch {
            notifications(false, 'Failed to read file date.');
        } finally {
            setReadingId(null);
        }
    };

    // ── Sync modal ────────────────────────────────────────────────────────────

    const openSyncModal = (item: ExifItem) => {
        setSyncModal({ open: true, item, syncType: 'post_date', loading: false, done: false, message: '', newWpDate: '' });
    };

    const closeSyncModal = () => {
        setSyncModal((prev) => ({ ...prev, open: false, done: false, message: '', newWpDate: '' }));
    };

    const handleSync = async () => {
        if (!syncModal.item) return;
        setSyncModal((prev) => ({ ...prev, loading: true, done: false, message: '', newWpDate: '' }));
        try {
            const result = await exifSyncSingle({
                attachment_id: syncModal.item.attachment_id,
                sync_type: syncModal.syncType,
            }) as Record<string, unknown>;
            if (result.updated) {
                setSyncModal((prev) => ({
                    ...prev,
                    loading: false,
                    done: true,
                    message: (result.message as string) || 'Synced successfully.',
                    newWpDate: (result.new_wp_date as string) || '',
                }));
                await loadResults(currentPage, activeFilter, searchQuery);
            } else {
                setSyncModal((prev) => ({
                    ...prev,
                    loading: false,
                    message: (result.message as string) || 'Sync failed.',
                }));
            }
        } catch {
            setSyncModal((prev) => ({ ...prev, loading: false, message: 'Request failed.' }));
        }
    };

    // ── Bulk Sync handlers ────────────────────────────────────────────────────

    const runBatchLoop = useCallback(async () => {
        if (batchRunning.current) return;
        batchRunning.current = true;
        try {
            let done = false;
            while (!done) {
                const res = await exifBulkSyncBatch() as unknown as BulkSyncState;
                setBulkSync({ ...res });
                done = !!res.complete;
                if (done) {
                    await loadResults(1, activeFilter, searchQuery);
                }
            }
        } catch {
            setBulkSync((prev) => ({ ...prev, status: 'idle', complete: true, message: 'Request failed.' }));
        } finally {
            batchRunning.current = false;
        }
    }, [activeFilter, searchQuery, loadResults]);

    const handleBulkSyncStart = async () => {
        setBulkSync({ status: 'running', total: 0, processed: 0, synced: 0, failed: 0, skipped: 0, message: 'Starting…', complete: false });
        try {
            const res = await exifBulkSyncStart({
                filter: bulkOpts.filter,
                sync_type: bulkOpts.syncType,
                date_from: bulkOpts.dateFrom,
                date_to: bulkOpts.dateTo,
            }) as { started: boolean; total?: number; message?: string };
            if (!res.started) {
                setBulkSync({ status: 'idle', total: 0, processed: 0, synced: 0, failed: 0, skipped: 0, message: res.message || 'No files found.', complete: true });
                return;
            }
            setBulkSync((prev) => ({ ...prev, total: res.total ?? 0, message: res.message || '' }));
            runBatchLoop();
        } catch {
            setBulkSync({ status: 'idle', total: 0, processed: 0, synced: 0, failed: 0, skipped: 0, message: 'Request failed.', complete: true });
        }
    };

    const handleBulkSyncCancel = async () => {
        batchRunning.current = false;
        try {
            const res = await exifBulkSyncCancel() as unknown as BulkSyncState;
            setBulkSync({ ...res });
        } catch {
            setBulkSync((prev) => ({ ...prev, status: 'cancelled', complete: true }));
        }
    };

    // ── Effects ───────────────────────────────────────────────────────────────

    useEffect(() => {
        loadResults(currentPageFromUrl, activeFilter, searchQuery);
    }, [activeFilter, currentPageFromUrl, searchQuery]);

    const totalPages = Math.ceil(total / PER_PAGE);

    // ─────────────────────────────────────────────────────────────────────────

    return (
        <div className="mx-auto px-6 py-8 min-h-screen bg-gray-50">

            {/* Header */}
            <div className="mb-8">
                <div className="flex items-center gap-3 mb-2">
                    <h1 className="text-2xl font-semibold text-gray-900 m-0!">Manage EXIF / File Data</h1>
                    <span className="inline-flex items-center px-2.5 py-0.5 text-xs font-medium text-amber-800 bg-amber-100 rounded-full">
                        Free Feature
                    </span>
                </div>
                <p className="text-sm text-gray-500">
                    Read date metadata from any media file — images (EXIF), video, audio, PDF, documents — and sync to WordPress post dates.
                </p>
            </div>

            {/* Pro: Bulk Sync Panel */}
            {hasPro && (
                <BulkSyncPanel
                    opts={bulkOpts}
                    onChangeOpts={setBulkOpts}
                    syncState={bulkSync}
                    onStart={handleBulkSyncStart}
                    onCancel={handleBulkSyncCancel}
                />
            )}

            {/* Actions bar */}
            <div className="flex items-center gap-3 px-4 py-3 border-b border-gray-200 bg-white rounded-t-lg">
                {activeFilter !== 'missing' && (
                    <div className="ml-auto">
                        <SearchInput
                            placeholder="Search media..."
                            value={searchInput}
                            onChange={handleSearchChange}
                            onClear={handleSearchClear}
                        />
                    </div>
                )}
            </div>

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
                                {total}
                            </span>
                        )}
                    </button>
                ))}
            </div>

            {/* Legend */}
            <div className="flex flex-wrap gap-3 px-4 py-2.5 bg-white border-b border-gray-200 text-xs text-gray-500">
                <span className="inline-flex items-center gap-1.5"><span className="w-2 h-2 rounded-full bg-emerald-400 inline-block"></span>EXIF — full camera metadata</span>
                <span className="inline-flex items-center gap-1.5"><span className="w-2 h-2 rounded-full bg-blue-400 inline-block"></span>Image meta — partial metadata</span>
                <span className="inline-flex items-center gap-1.5"><span className="w-2 h-2 rounded-full bg-amber-400 inline-block"></span>File system — file modification date</span>
            </div>

            {/* Results */}
            <div className="bg-white rounded-b-lg border border-t-0 border-gray-200 p-4">
                {isLoading ? (
                    <div className="text-center py-12 text-gray-500">Loading...</div>
                ) : items.length === 0 ? (
                    <div className="text-center py-12">
                        <svg className="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p className="text-gray-500 text-sm">
                            {activeFilter === 'missing'
                                ? 'No files found with missing date metadata.'
                                : 'No media files found.'}
                        </p>
                    </div>
                ) : (
                    <div className="space-y-2">
                        {items.map((item) => (
                            <ExifRow
                                key={item.attachment_id}
                                item={item}
                                readingId={readingId}
                                onRead={handleReadSingle}
                                onSync={openSyncModal}
                            />
                        ))}

                        {totalPages > 1 && (
                            <Pagination
                                currentPage={currentPage}
                                totalPages={totalPages}
                                totalPosts={total}
                                postsPerPage={PER_PAGE}
                                onPageChange={(page) => loadResults(page)}
                            />
                        )}
                    </div>
                )}
            </div>

            {/* Sync Modal */}
            {syncModal.open && syncModal.item && (
                <SyncModal
                    item={syncModal.item}
                    syncType={syncModal.syncType}
                    loading={syncModal.loading}
                    done={syncModal.done}
                    message={syncModal.message}
                    newWpDate={syncModal.newWpDate}
                    onChangeSyncType={(t) => setSyncModal((prev) => ({ ...prev, syncType: t }))}
                    onSync={handleSync}
                    onClose={closeSyncModal}
                />
            )}
        </div>
    );
}

// ─── ExifRow ──────────────────────────────────────────────────────────────────

interface ExifRowProps {
    item: ExifItem;
    readingId: number | null;
    onRead: (item: ExifItem) => void;
    onSync: (item: ExifItem) => void;
}

function ExifRow({ item, readingId, onRead, onSync }: ExifRowProps) {
    const isReading = readingId === item.attachment_id;
    const exif = item.exif;
    const source = exif?.source || '';
    const dateOriginal = exif?.date_original ? formatEXIFData(exif.date_original) : '';
    const dateDigitized = exif?.date_digitized ? formatEXIFData(exif.date_digitized) : '';
    const camera = [exif?.camera_make, exif?.camera_model].filter(Boolean).join(' ');
    const isImage = item.file_type === 'image';

    // Source badge colour
    const sourceBadge = source === 'exif'
        ? 'text-emerald-700 bg-emerald-50'
        : source === 'wp_meta'
            ? 'text-blue-700 bg-blue-50'
            : source === 'filesystem'
                ? 'text-amber-700 bg-amber-50'
                : '';

    return (
        <div className="flex items-center gap-4 p-4 bg-white rounded-lg border border-gray-200 hover:shadow-sm transition-shadow">
            {/* Thumbnail / icon */}
            <div className="shrink-0 w-14 h-14 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center">
                {isImage && item.url ? (
                    <img src={item.url} alt={item.title} className="w-full h-full object-cover" />
                ) : (
                    <FileTypeIcon fileType={item.file_type} />
                )}
            </div>

            {/* Info */}
            <div className="flex-1 min-w-0">
                <h3 className="text-sm font-semibold text-gray-900 truncate mt-0! mb-1">
                    {item.title || `(ID: ${item.attachment_id})`}
                </h3>
                <div className="flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-gray-500">
                    {/* MIME type chip */}
                    <span className="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium text-gray-500 bg-gray-100 rounded">
                        {item.mime_type || item.file_type}
                    </span>
                    {dateOriginal && (
                        <span>
                            <span className="font-medium text-gray-700">{getDateLabel(source)}:</span> {dateOriginal}
                            {source && <span className="ml-1 text-gray-400">({getSourceLabel(source)})</span>}
                        </span>
                    )}
                    {dateDigitized && (
                        <span><span className="font-medium text-gray-700">Digitized:</span> {dateDigitized}</span>
                    )}
                    {camera && (
                        <span><span className="font-medium text-gray-700">Camera:</span> {camera}</span>
                    )}
                    <span><span className="font-medium text-gray-700">WP Date:</span> {item.wp_date}</span>
                </div>
            </div>

            {/* Status badge */}
            <div className="shrink-0 flex flex-col items-end gap-1">
                {item.has_exif ? (
                    <span className={`inline-flex items-center px-2 py-1 text-xs font-medium rounded ${sourceBadge || 'text-emerald-700 bg-emerald-50'}`}>
                        {source ? getSourceLabel(source) : 'Has Date'}
                    </span>
                ) : exif !== null && typeof exif === 'object' && Object.keys(exif).length > 0 ? (
                    <span className="inline-flex items-center px-2 py-1 text-xs font-medium text-amber-700 bg-amber-50 rounded">
                        No Date
                    </span>
                ) : (
                    <span className="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-500 bg-gray-100 rounded">
                        Not Scanned
                    </span>
                )}
            </div>

            {/* Actions */}
            <div className="shrink-0 flex items-center gap-2">
                <button
                    type="button"
                    disabled={isReading}
                    onClick={() => onRead(item)}
                    className="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors disabled:opacity-50"
                >
                    {isReading ? 'Reading...' : 'Read Date'}
                </button>
                {item.has_exif && (
                    <button
                        type="button"
                        onClick={() => onSync(item)}
                        className="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer transition-colors"
                    >
                        Sync Date
                    </button>
                )}
            </div>
        </div>
    );
}

// ─── SyncModal ────────────────────────────────────────────────────────────────

interface SyncModalProps {
    item: ExifItem;
    syncType: 'post_date' | 'post_modified' | 'both';
    loading: boolean;
    done: boolean;
    message: string;
    newWpDate: string;
    onChangeSyncType: (t: 'post_date' | 'post_modified' | 'both') => void;
    onSync: () => void;
    onClose: () => void;
}

// ─── BulkSyncPanel ────────────────────────────────────────────────────────────

interface BulkSyncPanelProps {
    opts: BulkSyncOptions;
    onChangeOpts: (opts: BulkSyncOptions) => void;
    syncState: BulkSyncState;
    onStart: () => void;
    onCancel: () => void;
}

function BulkSyncPanel({ opts, onChangeOpts, syncState, onStart, onCancel }: BulkSyncPanelProps) {
    const isRunning = syncState.status === 'running';
    const isDone    = syncState.status === 'done';
    const isCancelled = syncState.status === 'cancelled';
    const hasResult = isDone || isCancelled;

    const progress = syncState.total > 0
        ? Math.round((syncState.processed / syncState.total) * 100)
        : 0;

    const filterOptions: { value: BulkSyncFilter; label: string; desc: string }[] = [
        { value: 'all',           label: 'All media files',       desc: 'Sync every file that has a stored date.' },
        { value: 'missing_exif',  label: 'Missing date only',     desc: 'Only files that have not been scanned yet.' },
        { value: 'date_mismatch', label: 'Date mismatch only',    desc: 'Files where the EXIF Data differs from the WP upload date.' },
    ];

    const syncTypeOptions: { value: BulkSyncOptions['syncType']; label: string }[] = [
        { value: 'post_date',     label: 'Upload Date (post_date)' },
        { value: 'post_modified', label: 'Last Modified (post_modified)' },
        { value: 'both',          label: 'Both' },
    ];

    return (
        <div className="mb-6 bg-white rounded-lg border border-blue-200 overflow-hidden">
            {/* Panel header */}
            <div className="flex items-center gap-3 px-5 py-4 border-b border-blue-100 bg-blue-50">
                <svg className="w-5 h-5 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8}
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <div className="flex-1 min-w-0">
                    <h3 className="text-sm font-semibold text-blue-900 m-0!">Bulk Sync Engine</h3>
                    <p className="text-xs text-blue-500 m-0!">
                        Sync EXIF / file dates to WordPress post dates for all media in one go.
                    </p>
                </div>
                <ProLabel />
            </div>

            <div className="px-5 py-4">
                {/* Config form — hidden while running */}
                {!isRunning && (
                    <div className="space-y-4">
                        {/* Filter */}
                        <div>
                            <p className="text-sm font-medium text-gray-700 mb-2">Filter files to process:</p>
                            <div className="flex flex-wrap gap-x-6 gap-y-2">
                                {filterOptions.map((opt) => (
                                    <label key={opt.value} className="flex items-start gap-2 cursor-pointer">
                                        <input
                                            type="radio"
                                            name="bulk_filter"
                                            value={opt.value}
                                            checked={opts.filter === opt.value}
                                            onChange={() => onChangeOpts({ ...opts, filter: opt.value })}
                                            className="mt-0.5 cursor-pointer"
                                            disabled={isRunning}
                                        />
                                        <div>
                                            <span className="text-sm text-gray-800">{opt.label}</span>
                                            <p className="text-xs text-gray-400 m-0!">{opt.desc}</p>
                                        </div>
                                    </label>
                                ))}
                            </div>
                        </div>

                        {/* Date range */}
                        <div>
                            <p className="text-sm font-medium text-gray-700 mb-2">
                                Limit to date range <span className="text-gray-400 font-normal">(optional — by WP upload date)</span>:
                            </p>
                            <div className="flex flex-wrap items-center gap-3">
                                <div className="flex items-center gap-2">
                                    <label className="text-xs text-gray-600 shrink-0">From:</label>
                                    <input
                                        type="date"
                                        value={opts.dateFrom}
                                        onChange={(e) => onChangeOpts({ ...opts, dateFrom: e.target.value })}
                                        disabled={isRunning}
                                        className="border border-gray-300 rounded px-2 py-1 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-400 disabled:opacity-50"
                                    />
                                </div>
                                <div className="flex items-center gap-2">
                                    <label className="text-xs text-gray-600 shrink-0">To:</label>
                                    <input
                                        type="date"
                                        value={opts.dateTo}
                                        onChange={(e) => onChangeOpts({ ...opts, dateTo: e.target.value })}
                                        disabled={isRunning}
                                        className="border border-gray-300 rounded px-2 py-1 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-400 disabled:opacity-50"
                                    />
                                </div>
                                {(opts.dateFrom || opts.dateTo) && (
                                    <button
                                        type="button"
                                        onClick={() => onChangeOpts({ ...opts, dateFrom: '', dateTo: '' })}
                                        className="text-xs text-gray-400 hover:text-gray-600 cursor-pointer"
                                    >
                                        Clear
                                    </button>
                                )}
                            </div>
                        </div>

                        {/* Sync type */}
                        <div>
                            <p className="text-sm font-medium text-gray-700 mb-2">Which WordPress date to update:</p>
                            <div className="flex flex-wrap gap-x-6 gap-y-1">
                                {syncTypeOptions.map((opt) => (
                                    <label key={opt.value} className="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                                        <input
                                            type="radio"
                                            name="bulk_sync_type"
                                            value={opt.value}
                                            checked={opts.syncType === opt.value}
                                            onChange={() => onChangeOpts({ ...opts, syncType: opt.value })}
                                            className="cursor-pointer"
                                            disabled={isRunning}
                                        />
                                        {opt.label}
                                    </label>
                                ))}
                            </div>
                        </div>
                    </div>
                )}

                {/* Progress area */}
                {(isRunning || hasResult) && (
                    <div className={`${!isRunning && !hasResult ? '' : 'mt-4'} space-y-3`}>
                        {/* Live count */}
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-gray-700 font-medium">
                                {isRunning ? `Processing… ${syncState.processed} of ${syncState.total}` : syncState.message}
                            </span>
                            {syncState.total > 0 && (
                                <span className="text-gray-500 text-xs">{progress}%</span>
                            )}
                        </div>

                        {/* Progress bar */}
                        {syncState.total > 0 && (
                            <div className="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                                <div
                                    className={`h-2.5 rounded-full transition-all duration-300 ${
                                        isCancelled ? 'bg-amber-400' : isDone ? 'bg-emerald-500' : 'bg-blue-500'
                                    }`}
                                    style={{ width: `${progress}%` }}
                                />
                            </div>
                        )}

                        {/* Result summary */}
                        {hasResult && syncState.total > 0 && (
                            <div className="flex flex-wrap gap-4 text-xs pt-1">
                                <span className="inline-flex items-center gap-1.5 text-emerald-700">
                                    <span className="w-2 h-2 rounded-full bg-emerald-400 inline-block"></span>
                                    Synced: <strong>{syncState.synced}</strong>
                                </span>
                                <span className="inline-flex items-center gap-1.5 text-gray-500">
                                    <span className="w-2 h-2 rounded-full bg-gray-300 inline-block"></span>
                                    Skipped: <strong>{syncState.skipped}</strong>
                                </span>
                                <span className="inline-flex items-center gap-1.5 text-red-600">
                                    <span className="w-2 h-2 rounded-full bg-red-400 inline-block"></span>
                                    Failed: <strong>{syncState.failed}</strong>
                                </span>
                            </div>
                        )}
                    </div>
                )}

                {/* Action buttons */}
                <div className="flex items-center gap-3 mt-5">
                    {!isRunning ? (
                        <button
                            type="button"
                            onClick={onStart}
                            className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer transition-colors"
                        >
                            {hasResult ? 'Run Again' : 'Start Bulk Sync'}
                        </button>
                    ) : (
                        <button
                            type="button"
                            onClick={onCancel}
                            className="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 cursor-pointer transition-colors"
                        >
                            Cancel
                        </button>
                    )}
                    {isRunning && (
                        <span className="text-xs text-gray-400 animate-pulse">Running in background…</span>
                    )}
                </div>
            </div>
        </div>
    );
}

// ─── SyncModal ────────────────────────────────────────────────────────────────

function SyncModal({ item, syncType, loading, done, message, newWpDate, onChangeSyncType, onSync, onClose }: SyncModalProps) {
    const exif = item.exif;
    const source = exif?.source || '';
    const dateOriginal = exif?.date_original ? formatEXIFData(exif.date_original) : '';
    const dateDigitized = exif?.date_digitized ? formatEXIFData(exif.date_digitized) : '';

    // The date that will be written — prefer original, fall back to digitized.
    const dateToSync = dateOriginal || dateDigitized;

    const syncOptions: { value: 'post_date' | 'post_modified' | 'both'; label: string; desc: string }[] = [
        {
            value: 'post_date',
            label: 'Upload Date',
            desc: 'Updates post_date — the "Uploaded On" date shown in the WordPress Media Library.',
        },
        {
            value: 'post_modified',
            label: 'Last Modified Date',
            desc: 'Updates post_modified — the last-edited timestamp of this attachment record.',
        },
        {
            value: 'both',
            label: 'Both',
            desc: 'Updates both the upload date and the last-modified timestamp.',
        },
    ];

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div className="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 overflow-hidden">
                {/* Header */}
                <div className="flex items-center justify-between px-5 py-4 border-b border-gray-200">
                    <h2 className="text-base font-semibold text-gray-900 m-0!">Sync Date to WordPress</h2>
                    <button
                        type="button"
                        onClick={onClose}
                        className="text-gray-400 hover:text-gray-600 cursor-pointer"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Body */}
                <div className="px-5 py-4 space-y-4">
                    {/* File info */}
                    <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                        <div className="w-12 h-12 rounded overflow-hidden bg-gray-200 flex items-center justify-center shrink-0">
                            {item.file_type === 'image' && item.url ? (
                                <img src={item.url} alt={item.title} className="w-full h-full object-cover" />
                            ) : (
                                <FileTypeIcon fileType={item.file_type} />
                            )}
                        </div>
                        <div className="min-w-0">
                            <p className="text-sm font-medium text-gray-900 truncate m-0!">{item.title}</p>
                            <p className="text-xs text-gray-500 m-0!">{item.mime_type} · ID: {item.attachment_id}</p>
                        </div>
                    </div>

                    {/* Before / after preview */}
                    <div className="rounded-lg border border-gray-200 overflow-hidden text-sm">
                        <div className="grid grid-cols-2 divide-x divide-gray-200">
                            <div className="px-4 py-3 bg-gray-50">
                                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1 m-0!">Current WP Date</p>
                                <p className="font-medium text-gray-800 m-0!">{item.wp_date || '—'}</p>
                            </div>
                            <div className="px-4 py-3 bg-blue-50">
                                <p className="text-xs font-semibold text-blue-500 uppercase tracking-wide mb-1 m-0!">
                                    {done ? 'New WP Date' : 'Will be set to'}
                                </p>
                                <p className="font-medium text-blue-800 m-0!">{done ? newWpDate : (dateToSync || '—')}</p>
                                {source && !done && (
                                    <p className="text-[10px] text-blue-400 m-0! mt-0.5">source: {getSourceLabel(source)}</p>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* EXIF detail (only if there are two date fields — JPEG/TIFF) */}
                    {dateDigitized && dateOriginal && (
                        <div className="space-y-1 text-xs text-gray-500">
                            <div className="flex gap-2">
                                <span className="font-medium text-gray-600 w-32 shrink-0">Date Taken:</span>
                                <span>{dateOriginal}</span>
                            </div>
                            <div className="flex gap-2">
                                <span className="font-medium text-gray-600 w-32 shrink-0">Date Digitized:</span>
                                <span>{dateDigitized}</span>
                            </div>
                            <p className="text-gray-400 m-0!">
                                The preferred field is used (settable in EXIF Data Settings).
                            </p>
                        </div>
                    )}

                    {/* Sync target selection */}
                    {!done && (
                        <div>
                            <p className="text-sm font-medium text-gray-700 mb-2">Which WordPress date field to update:</p>
                            <div className="space-y-2">
                                {syncOptions.map((opt) => (
                                    <label key={opt.value} className="flex items-start gap-3 cursor-pointer">
                                        <input
                                            type="radio"
                                            name="sync_type"
                                            value={opt.value}
                                            checked={syncType === opt.value}
                                            onChange={() => onChangeSyncType(opt.value)}
                                            className="mt-0.5 cursor-pointer"
                                        />
                                        <div>
                                            <span className="text-sm font-medium text-gray-800">{opt.label}</span>
                                            <p className="text-xs text-gray-500 m-0!">{opt.desc}</p>
                                        </div>
                                    </label>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Result message */}
                    {message && (
                        <div className={`flex items-center gap-2 text-sm ${done ? 'text-emerald-600' : 'text-red-600'}`}>
                            {done ? (
                                <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                            ) : (
                                <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            )}
                            <span>{message}</span>
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="flex justify-end gap-3 px-5 py-4 border-t border-gray-200 bg-gray-50">
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
                    >
                        {done ? 'Close' : 'Cancel'}
                    </button>
                    {!done && (
                        <button
                            type="button"
                            disabled={loading}
                            onClick={onSync}
                            className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer disabled:opacity-50"
                        >
                            {loading ? 'Syncing...' : 'Sync Date'}
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}
