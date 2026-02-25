import Axios from 'axios';
import type { AxiosResponse } from 'axios';
import toast from 'react-hot-toast';

const apibaseUrl = `${tsmltParams.restApiUrl}TinySolutions/mlt/v1/media`;

const Api = Axios.create({
    baseURL: apibaseUrl,
    headers: {
        "Content-Type": "application/json",
        'X-WP-Nonce': tsmltParams.rest_nonce,
    },
});

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
    const result = await Api.get(`/`, { params: prams });
    return JSON.parse(result.data);
};

export const singleUpDateApi = async (prams: unknown): Promise<AxiosResponse> => {
    return await Api.post(`/update`, prams);
};

export const upDateSingleMedia = async (prams: unknown): Promise<AxiosResponse> => {
    const response = await singleUpDateApi(prams);
    notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
};

export const submitBulkMediaAction = async (prams: unknown): Promise<AxiosResponse> => {
    const response = await Api.post(`/bulk/submit`, prams);
    notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
};

export const updateOptins = async (prams: unknown): Promise<AxiosResponse> => {
    const response = await Api.post(`/updateoptins`, prams);
    notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
};

export const getDates = async (): Promise<AxiosResponse> => {
    return await Api.get(`/filter/getdates`);
};

export const getTerms = async (): Promise<AxiosResponse> => {
    return await Api.get(`/getterms`);
};

export const getOptions = async (): Promise<AxiosResponse> => {
    return await Api.get(`/getoptions`);
};

export const getDirList = async (): Promise<AxiosResponse> => {
    return await Api.get(`/getDirList`);
};

export const getRubbishFile = async (prams: object = {}): Promise<Record<string, unknown>> => {
    const result = await Api.get(`/getRubbishFile`, { params: prams });
    return JSON.parse(result.data);
};

export const rescanDirApi = async (prams: unknown): Promise<AxiosResponse> => {
    return await Api.post(`/rescanDir`, prams);
};

export const rescanDir = async (prams: unknown): Promise<AxiosResponse> => {
    const response = await rescanDirApi(prams);
    await notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
};

export const singleDeleteApi = async (prams: unknown): Promise<AxiosResponse> => {
    return await Api.post(`/rubbish/single/delete/action`, prams);
};

export const rubbishBulkDeleteApi = async (prams: unknown): Promise<AxiosResponse> => {
    return await Api.post(`/rubbish/bulk/delete/action`, prams);
};

export const rubbishSingleDeleteAction = async (prams: unknown): Promise<AxiosResponse> => {
    const response = await singleDeleteApi(prams);
    notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
};

export const singleIgnoreApi = async (prams: unknown): Promise<AxiosResponse> => {
    return await Api.post(`/rubbish/single/ignore/action`, prams);
};

export const rubbishSingleIgnoreAction = async (prams: unknown): Promise<AxiosResponse> => {
    const response = await singleIgnoreApi(prams);
    notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
};

export const singleShowApi = async (prams: unknown): Promise<AxiosResponse> => {
    return await Api.post(`/rubbish/single/show/action`, prams);
};

export const rubbishSingleShowAction = async (prams: unknown): Promise<AxiosResponse> => {
    const response = await singleShowApi(prams);
    notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
};

export const getRubbishFileType = async (): Promise<{ fileTypes: string[] }> => {
    const result = await Api.get(`/getRubbishFileType`);
    return JSON.parse(result.data);
};

export const mediaCount = async (): Promise<unknown> => {
    const result = await Api.get(`/mediaCount`);
    return result.data;
};

export const clearSchedule = async (): Promise<AxiosResponse> => {
    return await Api.get(`/clearSchedule`);
};

export const actionClearSchedule = async (): Promise<AxiosResponse> => {
    const response = await clearSchedule();
    notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
};

export const getPluginList = async (): Promise<AxiosResponse> => {
    return await Api.get(`/getPluginList`);
};

export const importOneByOne = async (prams: unknown): Promise<AxiosResponse> => {
    return await Api.post(`/import/attachment/one/by/one`, prams);
};

export const getRegisteredImageSizes = async (): Promise<AxiosResponse> => {
    return await Api.get(`/getRegisteredImageSizes`);
};

export const truncateUnlistedFile = async (): Promise<AxiosResponse> => {
    return await Api.post(`/truncateUnlistedFile`);
};
