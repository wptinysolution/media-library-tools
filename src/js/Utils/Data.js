import axios from "axios";

const apibaseUrl = `${tttemeParams.restApiUrl}/TheTinyTools/ME/v1/media`;
const additonal_data = {
    'current_user' : tttemeParams.current_user,
    '__tttme_wpnonce' : tttemeParams.__tttme_wpnonce
}
export const getData = async ( url = '' ) => {
    console.log( additonal_data )
    const result = await axios.get(apibaseUrl + url, { params: { ...additonal_data } });
    return JSON.parse( result.data );
}

export const upDateSingleData = async ( dta ) => {
    return await axios.post(`${apibaseUrl}/update`, { ...additonal_data, ...dta });
}
