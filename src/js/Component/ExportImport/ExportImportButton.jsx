import React, { useState , useEffect } from "react";

import { Divider, Modal, Popconfirm, Progress, Layout, Button, Spin, Space, Typography} from 'antd';

const { Title, Text } = Typography;

const { Content } = Layout;

import {
    ExportOutlined,
    ImportOutlined
} from '@ant-design/icons';

import {useStateValue} from "../../Utils/StateProvider";
// import ExportInfo from "./ExportInfo";
import ImportInfo from "./ImportInfo";
import * as Types from "../../Utils/actionType";
import DownloadCSV from "./DownloadCSV";
import {mediaCount} from "../../Utils/Data";
import UploadCsv from "./UploadCsv";
import ResumeButton from "./ResumeButton";

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

    const [ importRemaining, setImportRemaining] = useState([] );

    const isExportImport = stateValue.exportImport.isExport || stateValue.exportImport.isImport;

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

        const isExport = 'export' === type && 'import' !== type;
        const isImport = 'import' === type && 'export' !== type;

        let exportImport = {
            ...stateValue.exportImport,
            isExport,
            isImport,
        }

        if( isExport ){
            const theMediaCount = await mediaCount();
            exportImport = {
                ...exportImport,
                ...theMediaCount,
                runImporter: false,
                runExporter: true
            }
        }

        if( isImport ){
            exportImport = {
                ...exportImport,
                runImporter: true,
                runExporter: false,
                mediaFiles: [],
                fileCount : 0,
                percent : 0,
                totalPage: 0
            }
        }

        await dispatch({
            type: Types.EXPORT_IMPORT,
            exportImport: exportImport,
        });
    }

    const confirm = (e) => {
        handleExportImport( 'export' );
    };
    const cancel = (e) => {
        //console.log(e);
    };

    const isRemainingImport = () => {
        const import_remaining = localStorage.getItem( "mlt_import_remaining_history");
        const remaining = import_remaining ? JSON.parse( import_remaining ) : {};
        if(  100 > remaining.countPercent  ) {
           // console.log(remaining)
            // dispatch({
            //     type: Types.EXPORT_IMPORT,
            //     exportImport: {
            //         ...stateValue.exportImport,
            //         isExport: true,
            //         isImport: false,
            //         runImporter: false,
            //         totalPage: remaining?.totalPage ? remaining.totalPage : 0,
            //         mediaFiles: remaining?.exportedMediaFiles ? remaining.exportedMediaFiles : {},
            //         percent: remaining?.countPercent ? remaining.countPercent : 0
            //     }
            // });
        }
    }

    const isRemainingExport = () => {
        const export_remaining = localStorage.getItem( "mlt_exported_history");
        const remaining = export_remaining ? JSON.parse( export_remaining ) : {};
        if( remaining?.countPercent && 100 > remaining?.countPercent ) {
            dispatch({
                type: Types.EXPORT_IMPORT,
                exportImport: {
                    ...stateValue.exportImport,
                    isExport: true,
                    isImport: false,
                    runExporter: false,
                   // mediaFiles: remaining?.exportedMediaFiles ? remaining.exportedMediaFiles : [],
                    // pagesRemaining: remaining?.pagesRemaining ? remaining.pagesRemaining : 0,
                    // totalPage: remaining?.totalPage ? remaining.totalPage : 0,
                    percent: remaining?.countPercent ? remaining.countPercent : 0
                }
            });
            //console.log( 'Resume' , remaining )
        }
    }

    useEffect(() => {
        isRemainingImport();
        isRemainingExport();
    }, []);

    //console.log( 'isExportImport : ', isExportImport );

    return (
        <Layout className="layout">
            <Title level={5} style={{
                border: '1px solid #f0f0f0',
                padding: '10px 15px',
                margin: '0 0 10px 0px',
                fontSize:'13px',
                color: 'red',
                textAlign: 'center'
            }}>
                If file import fails, Try importing in small batches at a time. Its depend in your server capacity.
            </Title>

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
                        { isExportImport &&
                            <>
                            {
                                // stateValue.exportImport.isExport && stateValue.exportImport.runExporter && <ExportInfo/>

                                stateValue.exportImport.isImport && stateValue.exportImport.runImporter ? <ImportInfo/> : ''
                            }

                            <Space wrap
                                style={ {
                                    justifyContent: 'center'
                                } }
                            >
                                {/*{ 100 <= stateValue.exportImport.percent && stateValue.exportImport.isExport &&*/}
                                {/*    <DownloadCSV/>*/}
                                {/*}*/}
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
                                {/*<Popconfirm*/}
                                {/*    placement="topLeft"*/}
                                {/*    title={'Export Now?'}*/}
                                {/*    description={'Are you sure to Export media file?'}*/}
                                {/*    okText="Yes"*/}
                                {/*    cancelText="No"*/}
                                {/*    onConfirm={ confirm }*/}
                                {/*    onCancel={cancel}*/}
                                {/*>*/}
                                {/*    <Button*/}
                                {/*        type="primary"*/}
                                {/*        size={`large`}*/}
                                {/*        style={ buttonStyle }*/}
                                {/*    >*/}
                                {/*        <ExportOutlined/> CSV Export { stateValue.exportImport.isExport && <span style={ { marginLeft: '8px' } }> <Spin size="small" /> </span> }*/}
                                {/*    </Button>*/}
                                {/*</Popconfirm>*/}
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
                               {/*<ResumeButton/>*/}
                           </>
                        }
                    </Layout>



            </Content>
        </Layout>
        )
}
export default ExportImportButton;