import React from 'react';
import { CSVLink } from "react-csv";
import {useStateValue} from "../../Utils/StateProvider";
import {Button} from "antd";
import * as Types from "../../Utils/actionType";
import {getMedia, singleUpDateApi} from "../../Utils/Data";

const buttonStyle = {
    width: '200px',
    height: '70px',
    fontSize: '25px',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: '5px'
}

function DownloadCSV() {

    const [stateValue, dispatch] = useStateValue();

    /*
    const getMediaRecursively = async ( prams ) => {
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...stateValue.bulkSubmitData,
                progressBar: Math.floor( 100 * ( stateValue.bulkSubmitData.progressTotal - prams.ids.length ) / stateValue.bulkSubmitData.progressTotal ),
            },
        });
        if ( prams.ids.length === 0) {
            // Base case: All renaming operations are completed
            return;
        }
        const id = prams.ids[0];
        const newName = 'bulkRenameByPostTitle' === stateValue.bulkSubmitData.type ? 'bulkRenameByPostTitle' : prams.data.file_name ;
        // Simulate the renaming operation using an asynchronous function (e.g., API call)
        const response = await singleUpDateApi( { newname: newName, ID: id });
        // Recur with the rest of the IDs in the list
        if( prams.ids.length && response.status ){
            await getMediaRecursively( { ...prams, ids: prams.ids.slice(1) } );
        }
        return response;
    }

    const handleBulkModalOk = async () => {

        const response = await getMediaRecursively( stateValue.bulkSubmitData );
        if( 200 === response?.status ){

        }
    };

    */



    const headers = [
        { label: "First Name", key: "firstname" },
        { label: "Last Name", key: "lastname" },
        { label: "Email", key: "email" }
    ];

    const data = [
        { firstname: "Ahmed", lastname: "Tomi", email: "ah@smthing.co.com" },
        { firstname: "Raed", lastname: "Labes", email: "rl@smthing.co.com" },
        { firstname: "Yezzi", lastname: "Min l3b", email: "ymin@cocococo.com" }
    ];

    return (
        <CSVLink data={data} headers={headers}>
            <Button
                style={
                    {
                        ...buttonStyle,
                        marginLeft: 'auto',
                        marginRight: 'auto',
                    }
                }
                type="primary"
                size={`large`}
            >
                Download CSV
            </Button>
        </CSVLink>
    );
}

export default DownloadCSV;