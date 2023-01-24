import axios from "axios";

const apibaseUrl = `${tttemeParams.restApiUrl}/TheTinyTools/ME/v1/media/`;
const additonal_data = {
    'current_user' : tttemeParams.current_user
}
export const getData = async ( url ) => {
    const result = await axios.get(apibaseUrl + url, { params: { ...additonal_data } });
    let data = JSON.parse( result.data );
    // console.log( data )
    return data ;
}

