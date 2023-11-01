import React from "react";

import { Divider, Modal, Popconfirm, Progress, Layout, Button, Spin, Space, Typography} from 'antd';

const { Title, Text } = Typography;

const { Content } = Layout;

import {
    ExportOutlined,
    ImportOutlined
} from '@ant-design/icons';


import {useStateValue} from "../../Utils/StateProvider";
import ExportImportInfo from "./ExportImportInfo";
import * as Types from "../../Utils/actionType";
import DownloadCSV from "./DownloadCSV";

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
        });
    }

    const confirm = (e) => {
        handleExportImport( 'export' );
    };
    const cancel = (e) => {
        console.log(e);
    };

    return (
        <Layout className="layout">
            <Content style={{
                padding: '150px',
                background: 'rgb(255 255 255 / 35%)',
                borderRadius: '5px',
                boxShadow: 'rgb(0 0 0 / 1%) 0px 0 20px',
                display: 'flex',
                alignItems: 'center'
            }}>
                { isExportImport &&
                    <Layout>
                        <ExportImportInfo/>
                        <DownloadCSV/>
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
                            onClick={ () => handleExportImport( 'reset' ) }
                        >
                            Cancel
                        </Button>
                    </Layout>
                }

                { ! isExportImport &&
                    <Content
                        className={`csv-export-import-btn-wrapper`}
                        style={ {
                            display: 'flex',
                            justifyContent: 'center',
                            gap: '15px'
                        } }
                        >
                        <Popconfirm
                            placement="topLeft"
                            title={'Export Now?'}
                            description={'Are you sure to Export media file?'}
                            okText="Yes"
                            cancelText="No"
                            onConfirm={ confirm }
                            onCancel={cancel}
                        >
                        <Button
                            type="primary"
                            size={`large`}
                            style={ buttonStyle }
                        >
                            <ExportOutlined/> Export { stateValue.exportImport.isExport && <span style={ { marginLeft: '8px' } }> <Spin size="small" /> </span> }
                        </Button>
                        </Popconfirm>
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