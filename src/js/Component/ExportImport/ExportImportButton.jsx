import React, { useState , useEffect } from "react";

import { Divider, Modal, Popconfirm, Progress, Layout, Button, Spin, Space, Typography} from 'antd';

const { Title, Text } = Typography;

const { Content } = Layout;

import {
    ImportOutlined
} from '@ant-design/icons';

import {useStateValue} from "../../Utils/StateProvider";

import ImportInfo from "./ImportInfo";
import * as Types from "../../Utils/actionType";

import UploadCsv from "./UploadCsv";

const buttonStyle = {
    width: '200px',
    height: '70px',
    fontSize: '25px',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: '5px'
}

function ExportImportButton() {

    const [stateValue, dispatch] = useStateValue();

    const isExportImport = stateValue.exportImport.isImport;

    const handleExportImport = async ( type ) => {

        if ( ! tsmltParams.hasExtended ){
            dispatch({
                type: Types.GENERAL_DATA,
                generalData: {
                    ...stateValue.generalData,
                    openProModal: true,
                },
            });
            return;
        }

        const isImport = 'import' === type;

        let exportImport = {
            ...stateValue.exportImport,
            isImport,
            runImporter: false,
            runExporter: false,
            mediaFiles: [],
            fileCount : 0,
            percent : 0,
            totalPage: 0
        }

        await dispatch({
            type: Types.EXPORT_IMPORT,
            exportImport: exportImport,
        });
    }

    return (
        <Layout className="layout">

            <Content style={{
                padding: '150px',
                borderRadius: '5px',
                boxShadow: 'rgb(0 0 0 / 1%) 0px 0 20px',
                display: 'flex',
                alignItems: 'center'
            }}>
                    <Layout
                        style={ {
                            padding: '50px',
                        } }
                    >

                        <Title level={5} style={{
                            border: '1px solid #f0f0f0',
                            padding: '10px 15px',
                            margin: '0 0 10px 0px',
                            fontSize:'13px',
                            color: 'red',
                            textAlign: 'center'
                        }}>
                            If file import fails, Try importing in small batches at a time. Its depend in your server capacity.
                            <br/>
                           CSV File Accepted Column Header <Text strong>( ID, slug, url, rename_to, title, caption, description, alt_text, custom_meta:_custom_meta_key, custom_meta:_meta_key_2, custom_meta:_meta_key_2  )</Text>
                        </Title>

                        { isExportImport &&
                            <>
                            {
                                stateValue.exportImport.isImport && stateValue.exportImport.runImporter ? <ImportInfo/> : ''
                            }

                            <Space wrap
                                style={ {
                                    justifyContent: 'center'
                                } }
                            >
                                { stateValue.exportImport.isImport && <UploadCsv/> }
                                { 100 <= stateValue.exportImport.percent &&
                                    <Button
                                        style={
                                            {
                                                ...buttonStyle,
                                                marginLeft: 'auto',
                                                marginRight: 'auto',
                                            }
                                        }
                                        size={`large`}
                                        onClick={ () => handleExportImport( 'reset' ) }
                                    >
                                        Cancel
                                    </Button>
                                }

                            </Space>
                            </>
                        }
                        { ! isExportImport ?
                            <Content className={`csv-export-import-btn-wrapper`}
                                     style={ {
                                         display: 'flex',
                                         justifyContent: 'center',
                                         gap: '15px'
                                     } }
                            >
                                <Button
                                    type="primary"
                                    size={`large`}
                                    style={ buttonStyle }
                                    onClick={ () => handleExportImport( 'import' ) }
                                >
                                    <ImportOutlined/> CSV Import { stateValue.exportImport.isImport && <span style={ { marginLeft: '8px' } }> <Spin size="small" /> </span> }
                                </Button>
                            </Content> :
                           <>
                           </>
                        }
                    </Layout>



            </Content>
        </Layout>
        )
}
export default ExportImportButton;