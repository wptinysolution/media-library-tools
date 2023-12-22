import React, { useEffect, useState } from 'react';

import {useStateValue} from "../../Utils/StateProvider";

import {Button} from "antd";

import { useCSVDownloader } from 'react-papaparse';

const buttonStyle = {
    gap: '5px',
    width: '200px',
    height: '70px',
    fontSize: '25px',
    display: 'flex',
    cursor: 'pointer',
    alignItems: 'center',
    justifyContent: 'center'
}

/**
 *
 * @returns {JSX.Element}
 * @constructor
 */
function DownloadCSV() {

    const { CSVDownloader, Type } = useCSVDownloader();

    const [stateValue, dispatch] = useStateValue();

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
        setMediaFiles( (prevState) => media );
    };

    useEffect(() => {
        generateCSVStructure();
    }, []);

    return (
        <>
            { stateValue.exportImport.mediaFiles.length &&
                <CSVDownloader
                    type={Type.Button}
                    bom={true}
                    className={`primary`}
                    config={{
                        delimiter: ',',
                    }}
                    data={mediaFiles} filename={ `export-csv-file-by-media-library-tools` }
                    style={
                        {
                            ...buttonStyle,
                            border: 0,
                            color: '#fff',
                            marginLeft: 'auto',
                            marginRight: 'auto',
                            borderRadius: '8px',
                            background: '#1677ff',

                        }
                    }
                >
                    Download CSV
                </CSVDownloader>
            }
        </>
    );
}

export default DownloadCSV;