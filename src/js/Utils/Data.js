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

export const upDateSingleMedia = async ( prams ) => {
    const response = await Axios.post(`${apibaseUrl}/update`, { ...additonal_data, ...prams });
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
    return await Axios.post(`${apibaseUrl}/bulk/update`, { ...additonal_data, ...prams });
}

export const submitBulkMediaAction = async ( prams ) => {
    return await Axios.post(`${apibaseUrl}/bulk/trash`, { ...additonal_data, ...prams });
}

export const getDates = async ( ) => {
    return await Axios.post(`${apibaseUrl}/getdates`, { ...additonal_data });
}
