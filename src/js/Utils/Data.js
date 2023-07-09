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

// const ApiPro = Axios.create({
//     baseURL: `${tsmltParams.restApiUrl}TinySolutions/mlt/v1/media`,
//     headers: {
//         'X-WP-Nonce': tsmltParams.rest_nonce
//     }
// });

export const notifications = ( isTrue, text ) => {
    const message = {
        message: text, //response.data.message,
        placement: 'topRight',
        style: {
            marginTop: '10px',
        },
    }
    if( isTrue ){
        notification.success( message );
    } else {
        notification.error(message );
    }
}

export const getMedia = async ( prams = {} ) => {
    const result = await Api.get( `/`, { params: prams } );
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

export const updateExtensionOptions = async ( prams ) => {
    const response = await Api.post(`/update/extension/options`, prams );
    notifications( 200 === response.status && response.data.updated, response.data.message );
    return response;
}

export const getExtensionOptions = async () => {
    return await Api.get(`/get/extension/options`);
}

// export const activateLicense = async ( license_key ) => {
//     const response = await Axios.get(`${tsmltParams.restApiUrl}lmfwc/v2/licenses/activate/${license_key}`);
//     //notifications( 200 === response.status && response.data.updated, response.data.message );
//     console.log( response )
//     return response;
// }
