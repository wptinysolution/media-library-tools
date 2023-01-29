/*
 * Import local dependencies
 */

import Axios from 'axios';

const apibaseUrl = `${tttemeParams.restApiUrl}TheTinyTools/ME/v1/media`;

const additonal_data = {
    'current_user' : tttemeParams.current_user,
}
export const getMedia = async ( url = '' ) => {
    const result = await Axios.get( `${apibaseUrl}${url}`, { params: { ...additonal_data } } );
    return JSON.parse( result.data );
}

export const upDateSingleMedia = async ( dta ) => {
    return await Axios.post(`${apibaseUrl}/update`, { ...additonal_data, ...dta });
}

export const bulkUpdateMedia = async ( dta ) => {
    return await Axios.post(`${apibaseUrl}/bulk/update`, { ...additonal_data, ...dta });
}
