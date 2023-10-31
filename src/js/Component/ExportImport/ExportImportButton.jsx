import React from "react";

import { Divider, Modal, List, Progress, Layout, Button, Spin, Space, Typography} from 'antd';

const { Title, Text } = Typography;

const { Content } = Layout;

import {
    ExportOutlined,
    ImportOutlined
} from '@ant-design/icons';


import {useStateValue} from "../../Utils/StateProvider";
import ExportImportInfo from "./ExportImportInfo";
import * as Types from "../../Utils/actionType";
import {EXPORT_IMPORT} from "../../Utils/actionType";

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

    const isExportImport = stateValue.exportImport.isExport || stateValue.exportImport.isImport;

    const handleExportImport = ( type ) => {
        dispatch({
            type: Types.EXPORT_IMPORT,
            exportImport: {
                ...stateValue.exportImport,
                isExport : 'export' === type && 'import' !== type,
                isImport : 'import' === type && 'export' !== type,
            },
        })
    }

    return (
        <Layout className="layout">
            <Content style={{
                padding: '150px',
                background: 'rgb(255 255 255 / 35%)',
                borderRadius: '5px',
                boxShadow: 'rgb(0 0 0 / 1%) 0px 0 20px',
            }}>
                { isExportImport && <ExportImportInfo/> }
                { ! isExportImport &&
                    <Content
                        className={`csv-export-import-btn-wrapper`}
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
                            <ExportOutlined/> Export { stateValue.exportImport.isExport && <span style={ { marginLeft: '8px' } }> <Spin size="small" /> </span> }
                        </Button>
                        <Button
                            type="primary"
                            size={`large`}
                            style={ buttonStyle }
                            onClick={ () => handleExportImport( 'import' ) }
                        >
                            <ImportOutlined/> Import { stateValue.exportImport.isImport && <span style={ { marginLeft: '8px' } }> <Spin size="small" /> </span> }
                        </Button>
                    </Content>
                }
            </Content>
        </Layout>
        )
}
export default ExportImportButton;