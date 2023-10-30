import React from "react";

import { Divider, Modal, List, Progress, Layout, Button, Spin, Space, Typography} from 'antd';
const { Title, Text } = Typography;

const { Content } = Layout;

import {
    ExportOutlined,
    ImportOutlined
} from '@ant-design/icons';


import {useStateValue} from "../Utils/StateProvider";

const buttonStyle = {
    width: '200px',
    height: '70px',
    fontSize: '25px',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: '5px'
}

function ExportImport() {

    const [stateValue, dispatch] = useStateValue();

    return (
        <Layout className="layout">
            <Content style={{
                padding: '150px',
                background: 'rgb(255 255 255 / 35%)',
                borderRadius: '5px',
                boxShadow: 'rgb(0 0 0 / 1%) 0px 0 20px',
            }}>
                <Content style={ {
                    maxWidth: '700px',
                    marginLeft: 'auto',
                    marginRight: 'auto'
                } }>
                    <Title level={3}> Export File to a CSV file</Title>
                    <Text type="secondary" >
                        This tool allows you to generate and download a CSV file containing a list of all media file.
                    </Text>
                    <Divider />
                    <Progress
                        className={ `progressbar-height` }
                        style={ {
                            height: '30px'
                        } }
                        showInfo={true} percent='50'
                    />
                    <Divider />
                </Content>
                <Content
                    className={`csv-export-import-btn-wrapper`}
                    style={ {
                        display: 'flex',
                        justifyContent: 'center',
                        gap: '15px'
                    } }>
                    <Button type="primary" size={`large`}  style={ buttonStyle } >
                        <ExportOutlined/> Export <span style={ { marginLeft: '8px' } }> <Spin size="small" /> </span>
                    </Button>
                    <Button type="primary" size={`large`} style={ buttonStyle } >
                        <ImportOutlined/> Import <span style={ { marginLeft: '8px' } }> <Spin size="small" /> </span>
                    </Button>
                </Content>
            </Content>
        </Layout>
        )
}
export default ExportImport;