/*
 * Import local dependencies
 */

import Axios from 'axios';
import {notification} from 'antd';

const apibaseUrl = `${tsmltParams.restApiUrl}TinySolutions/mlt/v1/media`;

/*
 * Create a Api object with Axios and
 * configure it for the WordPRess Rest Api.
 */
const Api = Axios.create({
    baseURL: apibaseUrl,
    headers: {
        'X-WP-Nonce': tsmltParams.rest_nonce
    }
});
/**
 *
 * @param isTrue
 * @param text
 */
export const notifications = ( isTrue, text ) => {
    const message = {
        message: text, //response.data.message,
        placement: 'topRight',
        style: {
            marginTop: '10px',
        },
    }
    if (isTrue) {
        notification.success(message);
    } else {
        notification.error(message);
    }
}
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
export const upDateSingleMedia = async (prams) => {
    const response = await Api.post(`/update`, prams);
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
export const rescanDirList = async ( prams ) => {
    const response = await Api.post(`/rescanDirList`, prams );
    await notifications( 200 === response.status && response.data.updated, response.data.message );
    return response;
}

/**
 *
 * @param prams
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const ignoreDirForScan = async ( prams ) => {
    const response = await Api.post(`/ignoreDirForScan`, prams );
    await notifications( 200 === response.status && response.data.updated, response.data.message );
    return response;
}

/**
 *
 * @param prams
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const rubbishSingleDeleteAction = async (prams) => {
    const response = await Api.post(`/rubbish/single/delete/action`, prams);
    notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
}
/**
 *
 * @param prams
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const rubbishSingleIgnoreAction = async (prams) => {
    const response = await Api.post(`/rubbish/single/ignore/action`, prams);
    notifications(200 === response.status && response.data.updated, response.data.message);
    return response;
}
/**
 *
 * @param prams
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const activateLicense = async ( prams ) => {
    const response = await Api.post(`/licensesActivate`, prams );
    await notifications( 200 === response.status && response.data.updated, response.data.message );
    return response;
}
/**
 *
 * @param prams
 * @returns {Promise<axios.AxiosResponse<any>>}
 */
export const updateExtensionOptions = async ( prams ) => {
    const response = await Api.post(`/updateExtensionOptions`, prams );
    notifications( 200 === response.status && response.data.updated, response.data.message );
    return response;
}