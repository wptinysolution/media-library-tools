import axios from "axios";

const apibaseUrl = `${tttemeParams.restApiUrl}/TheTinyTools/ME/v1/media/`;
const additonal_data = {
    'current_user' : tttemeParams.current_user
}
export const getData = async ( url = '' ) => {
    const result = await axios.get(apibaseUrl + url, { params: { ...additonal_data } });
    let data = JSON.parse( result.data );
    return data ;
}

export const upDateSingleData = async ( dta ) => {
    const result = await axios.post(`${apibaseUrl}update`, { ...additonal_data, ...dta });
    console.log( result );
    return result;

}
