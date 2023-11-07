import React, { useEffect, useState } from 'react';

import {useStateValue} from "../../Utils/StateProvider";

import {Button, Typography, Upload} from "antd";

import {ImportOutlined, UploadOutlined} from "@ant-design/icons";

import {fileUpload, getAttachmentPageByPage} from "../../Utils/Data";

import { usePapaParse } from 'react-papaparse';

import * as Types from "../../Utils/actionType";

const { Text } = Typography;

const buttonStyle = {
    width: '280px',
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
function UploadCsv() {

    const [stateValue, dispatch] = useStateValue();

    const { readRemoteFile } = usePapaParse();

    const [filename, setFilename ] = useState('' );

    const uploadProps = {
        name: 'file',
        action: `${tsmltParams.restApiUrl}wp/v2/media`, // Replace with your API endpoint
        headers: {
            'X-WP-Nonce': tsmltParams.rest_nonce // You can add any custom headers here
        },
        onChange(info) {
            if (info.file.status === 'done') {
                // Get the file URL from the response (assuming your API provides it)
                const { response, name } = info.file;
                const { guid } = response;
                const { raw } = guid;
                setFilename( name );
                readRemoteFile( raw, {
                    header: true, // Treat the first row as header
                    dynamicTyping: true, // Automatically parse numeric values
                    complete: (results) => {
                        dispatch({
                            type: Types.EXPORT_IMPORT,
                            exportImport: {
                                ...stateValue.exportImport,
                                mediaFiles: results.data,
                                fileCount: results.data.length,
                                percent: 0,
                                totalPage: results.data.length,
                            },
                        });
                    },
                });
            }
        },
    };

    return (
        <>
            { stateValue.exportImport.fileCount ?
                <>
                    <Button
                        icon={<ImportOutlined />}
                        type="primary"
                        style={
                            {
                                ...buttonStyle,
                                marginLeft: 'auto',
                                marginRight: 'auto',
                            }
                        }
                        onClick={ () => dispatch({
                            type: Types.EXPORT_IMPORT,
                            exportImport: {
                                ...stateValue.exportImport,
                                runImporter: true
                            },
                        })  }
                    >
                        Run the importer
                    </Button>
                    { filename && <Text>
                        { filename }
                    </Text>}
                </> : ''
            }

            { ! stateValue.exportImport.fileCount ?
                <Upload {...uploadProps} >
                    <Button
                        icon={<UploadOutlined/>}
                        style={
                            {
                                ...buttonStyle,
                                marginLeft: 'auto',
                                marginRight: 'auto',
                            }
                        }
                    >
                        Upload CSV File
                    </Button>
                </Upload> : ''
            }
        </>
    );
}

export default UploadCsv;