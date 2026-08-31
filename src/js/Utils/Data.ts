import Axios from 'axios';
import type { AxiosResponse } from 'axios';
import { createElement } from 'react';
import toast from 'react-hot-toast';

/**
 * Detect a stale-nonce failure: WordPress returns "-1" (or "0") with HTTP 200
 * when check_ajax_referer rejects the request. Capability failures use
 * wp_send_json_error with a real 4xx and don't match this shape.
 */
const isStaleNonceResponse = (response: AxiosResponse): boolean => {
    const body = response?.data;
    return body === -1 || body === '-1' || body === 0 || body === '0';
};

/**
 * One-shot nonce refresh. Calls the capability-gated tsmlt_refresh_nonce
 * endpoint and updates the global tsmltParams.tsmlt_wpnonce in place so all
 * subsequent ajaxPost calls pick up the new value automatically.
 *
 * Returns true on success, false if the user is genuinely no longer authorised
 * (logged out, role downgraded, etc.) — caller should surface a session-expired
 * error in that case rather than retry forever.
 */
const refreshNonce = async (): Promise<boolean> => {
    try {
        const body = new URLSearchParams({ action: 'tsmlt_refresh_nonce' });
        const res = await Axios.post(tsmltParams.ajaxUrl, body);
        const fresh = res?.data?.data?.nonce;
        if (typeof fresh === 'string' && fresh.length > 0) {
            (window as any).tsmltParams.tsmlt_wpnonce = fresh;
            return true;
        }
    } catch {
        // 403 from refresh endpoint = genuine auth break, fall through to false.
    }
    return false;
};

/**
 * Post to WordPress admin-ajax.php.
 *
 * Sends `action`, `nonce`, and `params` (JSON string) as URL-encoded POST body.
 * Unwraps the `{success, data}` envelope added by wp_send_json_success so that
 * callers receive data in the same shape as the previous REST API responses.
 *
 * Transparently recovers from stale nonces: if the first attempt comes back
 * with a "-1" / "0" body (WP's nonce-rejection signal), this helper mints a
 * fresh nonce via tsmlt_refresh_nonce and retries the original call exactly
 * once. Capability failures (real 4xx) are not retried.
 */
export const ajaxPost = async (action: string, params: unknown = {}): Promise<AxiosResponse> => {
    const send = () => {
        const body = new URLSearchParams({
            action,
            nonce: tsmltParams.tsmlt_wpnonce,
            params: JSON.stringify(params),
        });
        return Axios.post(tsmltParams.ajaxUrl, body);
    };

    let response = await send();

    if (isStaleNonceResponse(response) && action !== 'tsmlt_refresh_nonce') {
        const refreshed = await refreshNonce();
        if (refreshed) {
            response = await send();
        }
    }

    // Unwrap wp_send_json_success / wp_send_json_error envelope.
    if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        return { ...response, data: response.data.data };
    }
    return response;
};

export const notifications = (isTrue: boolean, text?: string): void => {
    if (isTrue) {
        toast.success(text || "Success", {
            iconTheme: {
                primary: "#fff",
                secondary: "#4CAF50",
            },
        });
    } else {
        // Render errors with an explicit close button so long messages that
        // outlive their auto-dismiss (or that the user wants gone) can be
        // dismissed manually.
        toast.error(
            (t) =>
                createElement(
                    'div',
                    { style: { display: 'flex', alignItems: 'flex-start', gap: '8px' } },
                    createElement('span', { style: { flex: 1 } }, text || 'Error'),
                    createElement(
                        'button',
                        {
                            type: 'button',
                            onClick: () => toast.dismiss(t.id),
                            'aria-label': 'Dismiss',
                            style: {
                                flexShrink: 0,
                                cursor: 'pointer',
                                background: 'transparent',
                                border: 'none',
                                color: '#fff',
                                fontSize: '16px',
                                lineHeight: 1,
                                padding: '0 2px',
                                marginTop: '-1px',
                                opacity: 0.85,
                            },
                        },
                        '✕'
                    )
                ),
            { duration: 8000 }
        );
    }
};

export const safeParseJSON = <T = unknown>(data: unknown): T | [] => {
    if (!data) return [];
    try {
        let cleaned: unknown = typeof data === 'string'
            ? data.replace(/^`|`$/g, '')
            : data;

        if (typeof cleaned === 'string') {
            cleaned = JSON.parse(cleaned);
        }
        if (typeof cleaned === 'string') {
            cleaned = JSON.parse(cleaned);
        }
        return cleaned as T;
    } catch (error) {
        console.error('JSON parse failed:', error);
        return [];
    }
};

export const getMedia = async (prams: object = {}): Promise<Record<string, unknown>> => {
    const result = await ajaxPost('tsmlt_get_media', prams);
    return JSON.parse(result.data);
};

export const singleUpDateApi = async (prams: unknown): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_update_single_media', prams);
};

export const upDateSingleMedia = async (prams: unknown): Promise<AxiosResponse> => {
    const response = await singleUpDateApi(prams);
    notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
};

export const submitBulkMediaAction = async (prams: unknown): Promise<AxiosResponse> => {
    const response = await ajaxPost('tsmlt_bulk_submit', prams);
    notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
};

export const updateOptins = async (prams: unknown): Promise<AxiosResponse> => {
    const response = await ajaxPost('tsmlt_update_option', prams);
    notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
};

export const getDates = async (): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_get_dates');
};

export const getTerms = async (): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_get_terms');
};

export const getOptions = async (): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_get_options');
};

export const getDirList = async (): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_get_dir_list');
};

export const startRubbishScan = async (): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_start_rubbish_scan');
};

export const getRubbishFile = async (prams: object = {}): Promise<Record<string, unknown>> => {
    const result = await ajaxPost('tsmlt_get_rubbish_file', prams);
    return JSON.parse(result.data);
};

export const rescanDirApi = async (prams: unknown): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_rescan_dir', prams);
};

export const rescanDir = async (prams: unknown): Promise<AxiosResponse> => {
    const response = await rescanDirApi(prams);
    await notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
};

export const singleDeleteApi = async (prams: unknown): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_rubbish_single_delete', prams);
};

export const rubbishBulkDeleteApi = async (prams: unknown): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_rubbish_bulk_delete', prams);
};

export const rubbishSingleDeleteAction = async (prams: unknown): Promise<AxiosResponse> => {
    const response = await singleDeleteApi(prams);
    notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
};

export const singleIgnoreApi = async (prams: unknown): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_rubbish_single_ignore', prams);
};

export const rubbishSingleIgnoreAction = async (prams: unknown): Promise<AxiosResponse> => {
    const response = await singleIgnoreApi(prams);
    notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
};

export const singleShowApi = async (prams: unknown): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_rubbish_single_show', prams);
};

export const rubbishSingleShowAction = async (prams: unknown): Promise<AxiosResponse> => {
    const response = await singleShowApi(prams);
    notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
};

export const singleRestoreApi = async (prams: unknown): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_rubbish_single_restore', prams);
};

export const rubbishSingleRestoreAction = async (prams: unknown): Promise<AxiosResponse> => {
    const response = await singleRestoreApi(prams);
    notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
};

export const getRubbishFileType = async (): Promise<{ fileTypes: string[] }> => {
    const result = await ajaxPost('tsmlt_get_rubbish_filetype');
    return JSON.parse(result.data);
};

export const mediaCount = async (): Promise<unknown> => {
    const result = await ajaxPost('tsmlt_media_count');
    return result.data;
};

export const clearSchedule = async (): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_clear_schedule');
};

export const actionClearSchedule = async (): Promise<AxiosResponse> => {
    const response = await clearSchedule();
    notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
};

export const getPluginList = async (): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_get_plugin_list');
};

export const importOneByOne = async (prams: unknown): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_import_attachment', prams);
};

export const getRegisteredImageSizes = async (): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_get_registered_image_sizes');
};

export const truncateUnlistedFile = async (): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_truncate_unlisted_file');
};

export const getEmptyDirectories = async (): Promise<{ directories: string[] }> => {
    const result = await ajaxPost('tsmlt_get_empty_directories');
    return result.data as { directories: string[] };
};

export const deleteEmptyDirectory = async (directory: string): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_delete_empty_directory', { directory });
};

// Duplicate detection.
export const duplicateScanBatch = async (prams: object = {}): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_duplicate_scan_batch', prams);
};

export const getDuplicateResults = async (prams: object = {}): Promise<Record<string, unknown>> => {
    const result = await ajaxPost('tsmlt_duplicate_get_results', prams);
    return result.data as Record<string, unknown>;
};

export const getDuplicateStatus = async (): Promise<Record<string, unknown>> => {
    const result = await ajaxPost('tsmlt_duplicate_get_status');
    return result.data as Record<string, unknown>;
};

export const clearDuplicateScan = async (): Promise<AxiosResponse> => {
    const response = await ajaxPost('tsmlt_duplicate_clear');
    notifications(200 === response.status && (response.data as { updated: boolean }).updated, (response.data as { message: string }).message);
    return response;
};

export interface DuplicateScanProgress {
    status: 'idle' | 'running' | 'done' | 'cancelled';
    offset: number;
    total: number;
    started_at: number;
    updated_at: number;
    tick_scheduled: boolean;
}

export const duplicateScanStart = async (): Promise<DuplicateScanProgress> => {
    const result = await ajaxPost('tsmlt_duplicate_scan_start');
    return result.data as DuplicateScanProgress;
};

export const duplicateScanCancel = async (): Promise<DuplicateScanProgress> => {
    const result = await ajaxPost('tsmlt_duplicate_scan_cancel');
    return result.data as DuplicateScanProgress;
};

export const duplicateScanGetProgress = async (): Promise<DuplicateScanProgress> => {
    const result = await ajaxPost('tsmlt_duplicate_scan_get_progress');
    return result.data as DuplicateScanProgress;
};

export const mergeDuplicates = async (prams: object = {}): Promise<AxiosResponse> => {
    const response = await ajaxPost('tsmlt_duplicate_merge', prams);
    notifications(200 === response.status && (response.data as { updated: boolean }).updated, (response.data as { message: string }).message);
    return response;
};

// Used-Where image usage tracker.
export const usedWhereScanBatch = async (prams: object = {}): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_used_where_scan_batch', prams);
};

// Cron-driven scan: kick off the background scan. The first tick fires almost
// immediately via spawn_cron(); subsequent ticks self-reschedule until done.
export const startUsedWhereScan = async (): Promise<Record<string, unknown>> => {
    const result = await ajaxPost('tsmlt_used_where_scan_start');
    return result.data as Record<string, unknown>;
};

// Cancel an in-flight cron-driven scan. Existing usage data is preserved.
export const cancelUsedWhereScan = async (): Promise<Record<string, unknown>> => {
    const result = await ajaxPost('tsmlt_used_where_scan_cancel');
    return result.data as Record<string, unknown>;
};

// Mark the latest terminal scan as acknowledged so the "scan finished"
// toast doesn't fire again on subsequent page loads. Server-side flag.
export const acknowledgeUsedWhereScan = async (): Promise<Record<string, unknown>> => {
    const result = await ajaxPost('tsmlt_used_where_scan_acknowledge');
    return result.data as Record<string, unknown>;
};

export const getUsedWhereResults = async (prams: object = {}): Promise<Record<string, unknown>> => {
    const result = await ajaxPost('tsmlt_used_where_get_results', prams);
    return result.data as Record<string, unknown>;
};

export const getUsedWhereStatus = async (): Promise<Record<string, unknown>> => {
    const result = await ajaxPost('tsmlt_used_where_get_status');
    return result.data as Record<string, unknown>;
};

export const clearUsedWhereScan = async (): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_used_where_clear');
};

export const usedWhereBulkDelete = async (ids: number[]): Promise<Record<string, unknown>> => {
    const result = await ajaxPost('tsmlt_used_where_bulk_delete', { ids });
    return result.data as Record<string, unknown>;
};

export const usedWhereTrash = async (ids: number[]): Promise<Record<string, unknown>> => {
    const result = await ajaxPost('tsmlt_used_where_trash', { ids });
    return result.data as Record<string, unknown>;
};

export const usedWhereUntrash = async (ids: number[]): Promise<Record<string, unknown>> => {
    const result = await ajaxPost('tsmlt_used_where_untrash', { ids });
    return result.data as Record<string, unknown>;
};

export const getUsedWhereTrashed = async (prams: object = {}): Promise<Record<string, unknown>> => {
    const result = await ajaxPost('tsmlt_used_where_get_trashed', prams);
    return result.data as Record<string, unknown>;
};

// Regenerate Thumbnails.
export const regenerateBatch = async (prams: object = {}): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_regenerate_batch', prams);
};

export const regenerateGetStatus = async (): Promise<{ total: number; image_sizes: unknown[] }> => {
    const result = await ajaxPost('tsmlt_regenerate_get_status');
    return result.data as { total: number; image_sizes: unknown[] };
};

export interface RegenerateProgress {
    status: 'idle' | 'running' | 'done' | 'cancelled';
    offset: number;
    total: number;
    started_at: number;
    updated_at: number;
    errors_count: number;
    success_count: number;
    deleted_total: number;
    recent_errors: { id: number; file: string; error: string }[];
    recent_done: { id: number; file: string; deleted_sizes: string[] }[];
    tick_scheduled: boolean;
}

export const regenerateStart = async (): Promise<RegenerateProgress> => {
    const result = await ajaxPost('tsmlt_regenerate_start');
    return result.data as RegenerateProgress;
};

export const regenerateGetProgress = async (): Promise<RegenerateProgress> => {
    const result = await ajaxPost('tsmlt_regenerate_get_progress');
    return result.data as RegenerateProgress;
};

export const regenerateCancel = async (): Promise<RegenerateProgress> => {
    const result = await ajaxPost('tsmlt_regenerate_cancel');
    return result.data as RegenerateProgress;
};

// Compress Images.

export type CompressionMode = 'high_quality' | 'balanced' | 'maximum';

export type CompressionJobStatus =
    | 'idle'
    | 'running'
    | 'completed'
    | 'partial'
    | 'failed'
    | 'cancelled';

export interface CompressionSettings {
    mode: CompressionMode;
    quality: number;
    use_custom_quality: boolean;
    backup_originals: boolean;
    compress_generated_sizes: boolean;
    auto_compress_on_upload: boolean;
}

export interface CompressionAccess {
    is_pro: boolean;
    feature_available: boolean;
    /** Images allowed per job; 0 means unlimited. */
    job_limit: number;
    can_backup: boolean;
    can_restore: boolean;
    can_generated_sizes: boolean;
    can_custom_quality: boolean;
    can_auto_compress: boolean;
}

export interface CompressionSettingsPayload {
    settings: CompressionSettings;
    modes: { value: CompressionMode; label: string; description: string }[];
    access: CompressionAccess;
    engines: { id: string; label: string }[];
    mimeTypes: string[];
}

export interface CompressionResultItem {
    id: number;
    title: string;
    status: 'completed' | 'skipped' | 'failed';
    reason: string;
    before: number;
    after: number;
    before_readable: string;
    after_readable: string;
    saved_percent: number;
}

export interface CompressionProgress {
    job_id: string;
    status: CompressionJobStatus;
    total: number;
    processed: number;
    succeeded: number;
    skipped: number;
    failed: number;
    remaining: number;
    percent: number;
    current_id: number;
    saved_bytes: number;
    saved_readable: string;
    settings: Partial<CompressionSettings>;
    recent_results: CompressionResultItem[];
    recent_errors: { id: number; title: string; error: string }[];
    last_error: string;
    has_failed: boolean;
    tick_scheduled: boolean;
    /** Present on start when the Free-tier limit trimmed the selection. */
    limit_applied?: boolean;
    limit?: number;
}

export interface CompressionSizeDetail {
    name: string;
    before: number;
    after: number;
    before_readable: string;
    after_readable: string;
    status: string;
    reason: string;
}

export interface CompressionDetail {
    has_data: boolean;
    status: string;
    original_size?: number;
    current_size?: number;
    original_size_readable?: string;
    current_size_readable?: string;
    saved_bytes?: number;
    saved_bytes_readable?: string;
    saved_percent?: number;
    mode?: string;
    quality?: number;
    engine?: string;
    generated_sizes?: boolean;
    compressed_at?: string;
    backup_enabled?: boolean;
    restore_available: boolean;
    has_backup?: boolean;
    last_error?: string;
    sizes?: CompressionSizeDetail[];
}

export const compressionGetSettings = async (): Promise<CompressionSettingsPayload> => {
    const result = await ajaxPost('tsmlt_compression_get_settings');
    return result.data as CompressionSettingsPayload;
};

export const compressionSaveSettings = async (prams: object = {}): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_compression_save_settings', prams);
};

export const compressionStart = async (prams: object = {}): Promise<CompressionProgress> => {
    const result = await ajaxPost('tsmlt_compression_start', prams);
    return result.data as CompressionProgress;
};

export const compressionGetProgress = async (): Promise<CompressionProgress> => {
    const result = await ajaxPost('tsmlt_compression_get_progress');
    return result.data as CompressionProgress;
};

/**
 * Process one batch server-side and return the updated progress.
 *
 * The modal calls this in a loop so a job still finishes on installs where
 * WP-Cron is disabled or never fires. Safe alongside the cron ticks: each
 * batch claims its items off the queue before working on them.
 */
export const compressionProcessBatch = async (): Promise<CompressionProgress> => {
    const result = await ajaxPost('tsmlt_compression_process_batch');
    return result.data as CompressionProgress;
};

export const compressionCancel = async (): Promise<CompressionProgress> => {
    const result = await ajaxPost('tsmlt_compression_cancel');
    return result.data as CompressionProgress;
};

export const compressionRetry = async (): Promise<CompressionProgress> => {
    const result = await ajaxPost('tsmlt_compression_retry');
    return result.data as CompressionProgress;
};

export const compressionReset = async (): Promise<CompressionProgress> => {
    const result = await ajaxPost('tsmlt_compression_reset');
    return result.data as CompressionProgress;
};

export const compressionCompressSingle = async (prams: object = {}): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_compression_compress_single', prams);
};

export const compressionRestoreSingle = async (prams: object = {}): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_compression_restore_single', prams);
};

export const compressionGetAttachment = async (prams: object = {}): Promise<{ attachment_id: number; compression: CompressionDetail }> => {
    const result = await ajaxPost('tsmlt_compression_get_attachment', prams);
    return result.data as { attachment_id: number; compression: CompressionDetail };
};

export const compressionGetBulk = async (prams: object = {}): Promise<{ items: Record<number, CompressionDetail> }> => {
    const result = await ajaxPost('tsmlt_compression_get_bulk', prams);
    return result.data as { items: Record<number, CompressionDetail> };
};

// EXIF Stripper functions (handled by Pro plugin).
export const exifStripBatch = async (prams: object = {}): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_exif_strip_batch', prams);
};

export const getExifResults = async (prams: object = {}): Promise<Record<string, unknown>> => {
    const result = await ajaxPost('tsmlt_exif_get_results', prams);
    return result.data as Record<string, unknown>;
};

export const getExifStatus = async (): Promise<Record<string, unknown>> => {
    const result = await ajaxPost('tsmlt_exif_get_status');
    return result.data as Record<string, unknown>;
};

export const getExifStripStatus = async (): Promise<Record<string, unknown>> => {
    const result = await ajaxPost('tsmlt_exif_strip_status');
    return result.data as Record<string, unknown>;
};

export const exifStripSingle = async (prams: object = {}): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_exif_strip_single', prams);
};

// EXIF Scanner functions.
export const exifScanBatch = async (prams: object = {}): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_exif_scan_batch', prams);
};

export const getExifScanStatus = async (): Promise<Record<string, unknown>> => {
    const result = await ajaxPost('tsmlt_exif_get_status');
    return result.data as Record<string, unknown>;
};

export const clearExifScan = async (): Promise<AxiosResponse> => {
    const response = await ajaxPost('tsmlt_exif_clear_scan');
    notifications(200 === response.status && (response.data as { updated: boolean }).updated, (response.data as { message: string }).message);
    return response;
};

export interface ExifScanProgress {
    status: 'idle' | 'running' | 'done' | 'cancelled';
    processed: number;
    total: number;
    with_exif: number;
    without_exif: number;
    started_at: number;
    updated_at: number;
    timestamp: string;
    tick_scheduled: boolean;
}

export const exifScanStart = async (): Promise<ExifScanProgress> => {
    const result = await ajaxPost('tsmlt_exif_scan_start');
    return result.data as ExifScanProgress;
};

export const exifScanCancel = async (): Promise<ExifScanProgress> => {
    const result = await ajaxPost('tsmlt_exif_scan_cancel');
    return result.data as ExifScanProgress;
};

export const exifScanGetProgress = async (): Promise<ExifScanProgress> => {
    const result = await ajaxPost('tsmlt_exif_scan_get_progress');
    return result.data as ExifScanProgress;
};

// EXIF Scanner batch processor (handles pagination and delays internally).
export const runExifScanBatch = async (onProgress?: (data: Record<string, unknown>) => void): Promise<void> => {
    let offset = 0;
    let complete = false;

    while (!complete) {
        const result = await exifScanBatch({
            offset,
            batch_size: 50,
        });

        const data = result.data as Record<string, unknown>;
        if (onProgress) {
            onProgress(data);
        }

        complete = (data.complete as boolean) || false;
        offset = (data.processed as number) || 0;

        // Small delay between batches to prevent overwhelming the server
        await new Promise((resolve) => setTimeout(resolve, 100));
    }
};

// EXIF Stripper functions.
export const stripExifSingle = async (prams: object = {}): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_strip_exif_single', prams);
};

export const checkStrippableExif = async (prams: object = {}): Promise<Record<string, unknown>> => {
    const result = await ajaxPost('tsmlt_check_strippable_exif', prams);
    return result.data as Record<string, unknown>;
};

// EXIF Editor functions.
export const getEditableExif = async (prams: object = {}): Promise<Record<string, unknown>> => {
    const result = await ajaxPost('tsmlt_exif_get_editable', prams);
    return result.data as Record<string, unknown>;
};

export const saveExif = async (prams: object = {}): Promise<Record<string, unknown>> => {
    const result = await ajaxPost('tsmlt_exif_save', prams);
    return result.data as Record<string, unknown>;
};

