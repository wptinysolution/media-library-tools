import React, { useEffect, useState } from 'react';

import {useStateValue} from "../../Utils/StateProvider";

import { Divider, Modal, Popconfirm, Progress, Layout, Button, Spin, Space, Typography} from 'antd';

const { Title, Text } = Typography;

const { Content } = Layout;



import { useCSVDownloader } from 'react-papaparse';
import {ExportOutlined, ImportOutlined} from "@ant-design/icons";

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

    return (
        <>
            { stateValue.exportImport.mediaFiles.length &&

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

                        >
                            Cancel
                        </Button>

                </Content>
            }
        </>
    );
}

export default ResumeButton;