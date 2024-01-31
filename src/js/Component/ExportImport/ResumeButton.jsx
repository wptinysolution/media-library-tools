import React, { useEffect, useState } from 'react';

import {useStateValue} from "../../Utils/StateProvider";

import { Divider, Modal, Popconfirm, Progress, Layout, Button, Spin, Space, Typography} from 'antd';

const { Title, Text } = Typography;

const { Content } = Layout;

import { useCSVDownloader } from 'react-papaparse';
import {ExportOutlined, ImportOutlined} from "@ant-design/icons";
import * as Types from "../../Utils/actionType";

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
function ResumeButton() {

    const [stateValue, dispatch] = useStateValue();
    console.log( 'Resume' , stateValue.exportImport )
    return (
        <>
            
                <Content className={`csv-export-resum-btn-wrapper`}
                     style={ {
                         display: 'flex',
                         justifyContent: 'center',
                         gap: '15px'
                     } }
                    >

                        <Button
                            style={
                                {
                                    ...buttonStyle,
                                    marginLeft: 'auto',
                                    marginRight: '0',
                                }
                            }
                            size={`large`}
                            onClick={ () => {
                                dispatch({
                                    type: Types.EXPORT_IMPORT,
                                    exportImport: {
                                        ...stateValue.exportImport,
                                        runImporter: stateValue.exportImport.isImport,
                                        runExporter: stateValue.exportImport.isExport,
                                    }
                                });
                            } }
                        >
                            Resume
                        </Button>

                        <Button
                            style={
                                {
                                    ...buttonStyle,
                                    marginLeft: '0',
                                    marginRight: 'auto',
                                }
                            }
                            size={`large`}
                            onClick={ () => {
                                localStorage.removeItem( "mlt_exported_history" );
                                localStorage.removeItem( "mlt_import_remaining_history" );
                                location.reload();
                            } }
                        >
                            Cancel
                        </Button>

                </Content>

        </>
    );
}

export default ResumeButton;