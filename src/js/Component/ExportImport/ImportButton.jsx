import React from "react";

import { Layout, Button, Spin, Space, Typography} from 'antd';

const { Title, Text } = Typography;

const { Content } = Layout;

import {
    ImportOutlined
} from '@ant-design/icons';

import {useStateValue} from "../../Utils/StateProvider";

import ImportInfo from "./ImportInfo";

import * as Types from "../../Utils/actionType";

import UploadCsv from "./UploadCsv";

import MainHeader from "../MainHeader";

const buttonStyle = {
    width: '200px',
    height: '70px',
    fontSize: '25px',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: '5px'
}

function ImportButton() {

    const [stateValue, dispatch] = useStateValue();

    const isImport = stateValue.exportImport.isImport;

    const handleImport = async ( type ) => {

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
        let exportImport = {
            ...stateValue.exportImport,
            isImport: 'import' === type,
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

    // console.log('stateValue.exportImport', stateValue.exportImport );

    return (
        <>
            <MainHeader/>
            <Layout className="layout">
            <Content style={{
                padding: '100px',
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
                           CSV File Accepted Column Header <Text strong>( ID, slug, url, rename_to, title, caption, description, alt_text, custom_meta:_custom_meta_key, custom_meta:_meta_key_2, custom_meta:_meta_key_3  )</Text>
                        </Title>

                        { isImport &&
                            <>
                            { stateValue.exportImport.runImporter ? <ImportInfo/> : '' }
                            <Space wrap
                                style={ {
                                    justifyContent: 'center'
                                } }
                            >
                                <UploadCsv/>
                            </Space>
                            </>
                        }
                        { ! isImport ?
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
                                    onClick={ () => handleImport( 'import' ) }
                                >
                                    <ImportOutlined/> CSV Import
                                </Button>
                            </Content> : null
                        }
                    </Layout>
            </Content>
            </Layout>
       </> )
}
export default ImportButton;