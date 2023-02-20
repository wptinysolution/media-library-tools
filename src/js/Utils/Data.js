/*
 * Import local dependencies
 */

import Axios from 'axios';
import { notification } from 'antd';

const apibaseUrl = `${tttemeParams.restApiUrl}TheTinyTools/ME/v1/media`;

/*
 * Create a Api object with Axios and
 * configure it for the WordPRess Rest Api.
 *
 * The 'mynamespace' object is injected into the page
 * using the WordPress wp_localize_script function.
 */
const Api = Axios.create({
    baseURL: apibaseUrl,
    headers: {
        'X-WP-Nonce': tttemeParams.rest_nonce
    }
});

const additonal_data = {
    'current_user' : tttemeParams.current_user,
}

export const getMedia = async ( url = '', prams = {} ) => {
    const result = await Api.get( `${url}`, { params: { ...additonal_data, ...prams } } );
    return JSON.parse( result.data );
}

export const upDateSingleMedia = async ( prams ) => {
    const response = await Api.post(`/update`, { ...additonal_data, ...prams });
    // for info - blue box
    if( 200 === response.status && response.data.updated ){
        notification.success({
            message: response.data.message,
            placement: 'bottomRight',
        });
    }
    if( ! response.data.updated ){
        notification.error({
            message: response.data.message,
            placement: 'bottomRight',
        });
    }

    return response;
}

export const bulkUpdateMedia = async ( prams ) => {
    return await Api.post(`/bulk/update`, { ...additonal_data, ...prams });
}

export const submitBulkMediaAction = async ( prams ) => {
    return await Api.post(`/bulk/trash`, { ...additonal_data, ...prams });
}

export const getDates = async () => {
    return await Api.get(`/filter/getdates`);
}
