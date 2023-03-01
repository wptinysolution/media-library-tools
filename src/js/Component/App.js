
import React from "react";

import { Pagination, Table, Input, Modal, Checkbox, Select, Layout, Menu, Button, Space } from 'antd';

const { Header, Footer, Sider, Content } = Layout;

import DataTable from "./Datatable";
const headerStyle = {
    height: 64,
    paddingInline: 0,
    lineHeight: '64px',
    backgroundColor: '#fff',
};
function App() {
    return (
        <div className="tttme-App">
            <Layout className="layout">
                <Header className="header" style={headerStyle}>
                    <Menu
                        theme="dark"
                        mode="horizontal"
                        defaultSelectedKeys={['listTbale']}
                        items={[
                            {
                                key: 'listTbale',
                                label: 'List Tbale',
                            },

                            {
                                key: 'settings',
                                label: 'Settings',
                            },
                        ]}
                        onSelect={ ({ item, key, keyPath, selectedKeys, domEvent }) => {
                            console.log( key );
                        } }
                    />
                </Header>
                <Content>
                    <DataTable />
                </Content>
            </Layout>
        </div>
    );
}
export default App