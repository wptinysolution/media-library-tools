import React, { useEffect, useState } from 'react';

import {useStateValue} from "../../Utils/StateProvider";

import {Button} from "antd";

import { useCSVDownloader } from 'react-papaparse';

const buttonStyle = {
    gap: '5px',
    width: '200px',
    height: '70px',
    fontSize: '25px',
    // display: 'flex',
    cursor: 'pointer',
    // alignItems: 'center',
    // justifyContent: 'center'
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
        const media = stateValue.mediaData.posts;
        if( ! media.length ){
            return;
        }
        const selectedIds = stateValue.bulkSubmitData.ids;
        const filteredData = media.filter(item => selectedIds.includes(item.ID));
       // console.log( filteredData );
        const updatedData = filteredData.map(({ ID, url, post_title, post_name, post_excerpt, post_content, alt_text, custom_meta  }) => ({
            'ID': ID,
            'slug': post_name,
            'url': url,
            'title': post_title,
            'caption': post_excerpt,
            'description': post_content,
            'alt_text' : alt_text,
            ...custom_meta,
        }));

        console.log( updatedData ) ;

        setMediaFiles( (prevState) => updatedData );
    };

    useEffect(() => {
        generateCSVStructure();
    }, []);

    return (
        <>
            { mediaFiles.length ?
                <CSVDownloader
                    type={Type.Button}
                    bom={true}
                    className={`primary`}
                    config={{
                        delimiter: ',',
                    }}
                    data={mediaFiles} filename={ `export-csv-file-by-media-library-tools-${window.location.hostname}` }
                    style={
                        {
                            ...buttonStyle,
                            border: 0,
                            color: '#fff',
                            borderRadius: '8px',
                            background: '#1677ff',

                        }
                    }
                >
                    Download CSV
                </CSVDownloader> : ''
            }
        </>
    );
}

export default DownloadCSV;