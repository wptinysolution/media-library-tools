import axios from "axios";

const apibaseUrl = `${tttemeParams.restApiUrl}/TheTinyTools/ME/v1/media`;
const additonal_data = {
    'current_user' : tttemeParams.current_user
}
export const getData = async ( url = '' ) => {
    const result = await axios.get(apibaseUrl + url, { params: { ...additonal_data } });
    return JSON.parse( result.data );
}

export const upDateSingleData = async ( dta ) => {
    return await axios.post(`${apibaseUrl}/update`, { ...additonal_data, ...dta });
}
