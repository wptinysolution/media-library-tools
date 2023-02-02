/*
 * Import local dependencies
 */

import Axios from 'axios';
import { notification } from 'antd';

const apibaseUrl = `${tttemeParams.restApiUrl}TheTinyTools/ME/v1/media`;

const additonal_data = {
    'current_user' : tttemeParams.current_user,
}

export const getMedia = async ( url = '', prams = {} ) => {
    const result = await Axios.get( `${apibaseUrl}${url}`, { params: { ...additonal_data, ...prams } } );
    return JSON.parse( result.data );
}

export const upDateSingleMedia = async ( dta ) => {
    const response = await Axios.post(`${apibaseUrl}/update`, { ...additonal_data, ...dta });
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

export const bulkUpdateMedia = async ( dta ) => {
    return await Axios.post(`${apibaseUrl}/bulk/update`, { ...additonal_data, ...dta });
}
