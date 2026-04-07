import Axios from 'axios';
import type { AxiosResponse } from 'axios';
import toast from 'react-hot-toast';

/**
 * Post to WordPress admin-ajax.php.
 *
 * Sends `action`, `nonce`, and `params` (JSON string) as URL-encoded POST body.
 * Unwraps the `{success, data}` envelope added by wp_send_json_success so that
 * callers receive data in the same shape as the previous REST API responses.
 */
const ajaxPost = async (action: string, params: unknown = {}): Promise<AxiosResponse> => {
    const body = new URLSearchParams({
        action,
        nonce: tsmltParams.tsmlt_wpnonce,
        params: JSON.stringify(params),
    });
    const response = await Axios.post(tsmltParams.ajaxUrl, body);
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
        toast.error(text || 'Error');
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

export const mergeDuplicates = async (prams: object = {}): Promise<AxiosResponse> => {
    const response = await ajaxPost('tsmlt_duplicate_merge', prams);
    notifications(200 === response.status && (response.data as { updated: boolean }).updated, (response.data as { message: string }).message);
    return response;
};

// Used-Where image usage tracker.
export const usedWhereScanBatch = async (prams: object = {}): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_used_where_scan_batch', prams);
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

// Regenerate Thumbnails.
export const regenerateBatch = async (prams: object = {}): Promise<AxiosResponse> => {
    return await ajaxPost('tsmlt_regenerate_batch', prams);
};

export const regenerateGetStatus = async (): Promise<{ total: number }> => {
    const result = await ajaxPost('tsmlt_regenerate_get_status');
    return result.data as { total: number };
};

// EXIF Date management.
export const exifGetList = async (params: object = {}): Promise<Record<string, unknown>> => {
    const result = await ajaxPost('tsmlt_exif_get_list', params);
    return result.data as Record<string, unknown>;
};

export const exifReadSingle = async (params: object): Promise<Record<string, unknown>> => {
    const result = await ajaxPost('tsmlt_exif_read_single', params);
    return result.data as Record<string, unknown>;
};

export const exifSyncSingle = async (params: object): Promise<Record<string, unknown>> => {
    const result = await ajaxPost('tsmlt_exif_sync_single', params);
    return result.data as Record<string, unknown>;
};

export const exifGetMissing = async (params: object = {}): Promise<Record<string, unknown>> => {
    const result = await ajaxPost('tsmlt_exif_get_missing', params);
    return result.data as Record<string, unknown>;
};

