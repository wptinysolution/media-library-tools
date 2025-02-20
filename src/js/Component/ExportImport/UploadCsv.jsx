import React, { useState } from 'react';

import {useStateValue} from "../../Utils/StateProvider";

import {Button, Checkbox, Divider, Typography, Upload} from "antd";

import {ImportOutlined, UploadOutlined} from "@ant-design/icons";

import { usePapaParse } from 'react-papaparse';

import * as Types from "../../Utils/actionType";

const { Title, Text, Paragraph } = Typography;

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
                const { source_url } = response;
                setFilename( name );
                readRemoteFile( source_url, {
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
            { stateValue.exportImport.fileCount && ! stateValue.exportImport.runImporter ?
                <>
                    <Checkbox
                        checked={stateValue.exportImport.settings.importUpdateContent}
                        onChange={ ( event ) => dispatch({
                        type: Types.EXPORT_IMPORT,
                        exportImport: {
                            ...stateValue.exportImport,
                            settings : {
                                ...stateValue.exportImport.settings,
                                importUpdateContent: event.target.checked ? 'update' : false
                            }
                        },
                    }) }>
                        Existing media file that match by <Text strong>ID</Text>  or <Text strong>slug</Text>  will be updated. </Checkbox>
                    <br/> Media that do not exist will be skipped and missing column data will remain unchanged.
                    <Divider style={{ margin: '10px'} } />
                    {
                        stateValue.exportImport.settings.importUpdateContent ? <>
                            <Checkbox
                                checked={stateValue.exportImport.settings.importRename}
                                onChange={ ( event ) => dispatch({
                                    type: Types.EXPORT_IMPORT,
                                    exportImport: {
                                        ...stateValue.exportImport,
                                        settings : {
                                            ...stateValue.exportImport.settings,
                                            importRename: event.target.checked ? 'importRename' : false
                                        }
                                    },
                                }) }>
                                 Rename using the value located in the <Text strong>( rename_to )</Text> column.</Checkbox>
                                <br/>Note: Rename media file that match by <Text strong>ID</Text>  or <Text strong>slug</Text> And Any missing column data will be left unchanged.
                                <Title level={5} style={{
                                    border: '1px solid #f0f0f0',
                                    padding: '10px 15px',
                                    margin: '0 0 10px 0px',
                                    fontSize:'13px',
                                    color: 'red',
                                    textAlign: 'center'
                                }} >
                                    We suggest you before renaming at first you should practice in your staging site.
                                </Title>
                            <Divider style={{ margin: '10px'} } />

                        </> : ''
                    }

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
                    <Divider style={{ margin: '5px 0'} } />
                    <Paragraph style={{ textAlign: 'center'} }>
                        { filename && <Text>
                            { filename }
                        </Text>}
                    </Paragraph>
                </> : ''
            }

            { ! stateValue.exportImport.fileCount ?
                <Upload
                    {...uploadProps}
                    multiple={false}
                    accept=".csv"
                >
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