import React, { useEffect, useState } from 'react';
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

/**
 *
 * @returns {JSX.Element}
 * @constructor
 */
function DownloadCSV() {

    const [stateValue, dispatch] = useStateValue();

    const [headers, setHeaders] = useState([]);

    const [mediaFiles, setMediaFiles] = useState([]);

    const generateCSVStructure = async () => {
        const media = stateValue.exportImport.mediaFiles;
        if( ! media.length ){
            return;
        }
        // Extract keys from the first item in the array
        const keys = Object.keys(media[0]).map( ( item ) => (
            { label: item, key: item }
        ) );
        setHeaders( (prevState) => keys );
        setMediaFiles( (prevState) => media );
    };

    useEffect(() => {
        generateCSVStructure();
    }, []);

    return (
        <>
            { stateValue.exportImport.mediaFiles.length &&
                <CSVLink data={mediaFiles} headers={headers}>
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
            }
        </>
    );
}

export default DownloadCSV;