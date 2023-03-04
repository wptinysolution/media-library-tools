/*
 * Import local dependencies
 */

import Axios from 'axios';
import { notification } from 'antd';

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

export const notifications = ( isTrue, text ) => {
    const message = {
        message: text, //response.data.message,
        placement: 'bottomRight',
    }
    if( isTrue ){
        notification.success( message );
    } else {
        notification.error(message );
    }
}

export const getMedia = async ( url = '', prams = {} ) => {
    const result = await Api.get( `${url}`, { params: prams } );
    return JSON.parse( result.data );
}

export const upDateSingleMedia = async ( prams ) => {
    const response = await Api.post(`/update`, prams );
    // for info - blue box
    notifications( 200 === response.status && response.data.updated, response.data.message );
    return response;
}

export const submitBulkMediaAction = async ( prams ) => {
    const response = await Api.post(`/bulk/submit`, prams );
    notifications( 200 === response.status && response.data.updated, response.data.message );
    return response;
}

export const updateOptins = async (  prams ) => {
    const response = await Api.post(`/updateoptins`, prams );
    notifications( 200 === response.status && response.data.updated, response.data.message );
    return response;
}

export const getDates = async () => {
    return await Api.get(`/filter/getdates`);
}

export const getTerms = async () => {
    return await Api.get(`/getterms`);
}

export const getOptions = async () => {
    return await Api.get(`/getoptions`);
}

