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

function ExportButton() {

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
                                    onClick={ () => handleExportImport( 'export' ) }
                                >
                                    <ImportOutlined/> CSV Export { stateValue.exportImport.isImport && <span style={ { marginLeft: '8px' } }> <Spin size="small" /> </span> }
                                </Button>
                            </Content>
                    </Layout>
                </Content>
            </Layout>
        </> )
}
export default ExportButton;