/*
 * Import local dependencies
 */
import Axios from 'axios';
import toast from 'react-hot-toast';

const apibaseUrl = `${tsmltParams.restApiUrl}TinySolutions/mlt/v1/media`;

/*
 * Create a Api object with Axios and
 * configure it for the WordPRess Rest Api.
 */
const Api = Axios.create({
    baseURL: apibaseUrl,
    headers: {
        "Content-Type": "application/json",
        'X-WP-Nonce': tsmltParams.rest_nonce
    }
});
/**
 *
 * @param isTrue
 * @param text
 */
export const notifications = (isTrue, text) => {
    if (isTrue) {
        toast.success( text || "Success", {
            iconTheme: {
                primary: "#fff",   // icon color
                secondary: "#4CAF50", // icon background
            },
        });
    } else {
        toast.error(text || 'Error' );
    }
};

/**
 * Safely parse malformed / double-encoded JSON (object or array)
 */
export const safeParseJSON = (data) => {
    if (!data) return [];
    try {
        let cleaned = typeof data === 'string'
            ? data.replace(/^`|`$/g, '')
            : data;

        // First parse
        if (typeof cleaned === 'string') {
            cleaned = JSON.parse(cleaned);
        }
        // Second parse (if still string)
        if (typeof cleaned === 'string') {
            cleaned = JSON.parse(cleaned);
        }
        return cleaned;
    } catch (error) {
        console.error('JSON parse failed:', error);
        return [];
    }
};

/**
 *
 * @param prams
 * @returns {Promise<any>}
 */
export const getMedia = async (prams = {}) => {
    const result = await Api.get(`/`, {params: prams});
    return JSON.parse(result.data);
}

/**
 *
 * @param prams
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const singleUpDateApi = async (prams) => {
    return await Api.post(`/update`, prams);
}

/**
 *
 * @param prams
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const upDateSingleMedia = async (prams) => {
    const response = await singleUpDateApi( prams );
    // for info - blue box
    notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
}
/**
 *
 * @param prams
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const submitBulkMediaAction = async (prams) => {
    const response = await Api.post(`/bulk/submit`, prams);
    notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
}
/**
 *
 * @param prams
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const updateOptins = async (prams) => {
    const response = await Api.post(`/updateoptins`, prams);
    notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
}
/**
 *
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const getDates = async () => {
    return await Api.get(`/filter/getdates`);
}
/**
 *
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const getTerms = async () => {
    return await Api.get(`/getterms`);
}
/**
 *
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const getOptions = async () => {
    return await Api.get(`/getoptions`);
}
/**
 *
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const getDirList = async () => {
    return await Api.get(`/getDirList`);
}
/**
 *
 * @param prams
 * @returns {Promise<any>}
 */
export const getRubbishFile = async ( prams = {} ) => {
    const result = await Api.get(`/getRubbishFile`, {params: prams} );
    return JSON.parse(result.data);
}

/**
 *
 * @param prams
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const rescanDirApi = async (prams) => {
    return await Api.post(`/rescanDir`, prams );
}
/**
 *
 * @param prams
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const rescanDir = async ( prams ) => {
    const response = await rescanDirApi( prams );
    await notifications( 200 === response.status && response.data.updated, response.data.message );
    return response;
}

/**
 *
 * @param prams
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const singleDeleteApi = async (prams) => {
    return await Api.post(`/rubbish/single/delete/action`, prams);
}

/**
 *
 * @param prams
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const rubbishBulkDeleteApi = async (prams) => {
    return await Api.post(`/rubbish/bulk/delete/action`, prams);
}


/**
 *
 * @param prams
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const rubbishSingleDeleteAction = async (prams) => {
    const response = await singleDeleteApi( prams);
    notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
}

/**
 *
 * @param prams
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const singleIgnoreApi = async (prams) => {
    return await Api.post(`/rubbish/single/ignore/action`, prams);
}

/**
 *
 * @param prams
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const rubbishSingleIgnoreAction = async (prams) => {
    const response = await singleIgnoreApi( prams);
    notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
}

/**
 *
 * @param prams
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const singleShowApi = async (prams) => {
    return await Api.post(`/rubbish/single/show/action`, prams);
}

/**
 *
 * @param prams
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const rubbishSingleShowAction = async (prams) => {
    const response = await singleShowApi( prams );
    notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
}

/**
 *
 * @param prams
 * @returns {Promise<any>}
 */
export const getRubbishFileType = async () => {
    const result = await Api.get(`/getRubbishFileType`);
    return JSON.parse(result.data);
}

/**
 *
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const mediaCount = async () => {
    const result = await Api.get(`/mediaCount`);
    return result.data;
}

/**
 *
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const clearSchedule = async () => {
    return await Api.get(`/clearSchedule`);
}

/**
 *
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const actionClearSchedule = async () => {
    const response = await clearSchedule();
    notifications( 200 === response.status && response.data.updated, response.data.message );
    return response;
}

export const getPluginList = async () => {
    return await Api.get(`/getPluginList`);
}

export const importOneByOne = async (prams) => {
    return await Api.post(`/import/attachment/one/by/one`, prams);
}

export const getRegisteredImageSizes = async () => {
    return await Api.get(`/getRegisteredImageSizes`);
}

export const truncateUnlistedFile = async () => {
    return await Api.post(`/truncateUnlistedFile` );
}

