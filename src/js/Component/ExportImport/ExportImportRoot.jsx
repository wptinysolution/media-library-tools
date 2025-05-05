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
import {Link, Route} from "react-router-dom";
import ImportButton from "./ImportButton";

const buttonStyle = {
    width: '200px',
    height: '70px',
    fontSize: '25px',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: '5px',
    border:'1px solid #1677ff'
}

function ExportImportRoot() {

    const [stateValue, dispatch] = useStateValue();

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
                            display: 'flex',
                            flexDirection:'row',
                            flexWrap: 'wrap',
                            gap: '15px',
                            justifyContent: 'center'
                        } }
                    >
                        <Button
                            type="link"
                            size={`large`}
                            style={ buttonStyle }
                            href={ '#/export' }
                        >
                            Export
                        </Button>
                        <Button
                            type="link"
                            size={`large`}
                            style={ buttonStyle }
                            href={ '#/import' }
                        >
                            Import
                        </Button>
                    </Layout>
                </Content>
            </Layout>
        </> )
}
export default ExportImportRoot;