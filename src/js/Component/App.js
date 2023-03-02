
import React, {useState, useEffect } from "react";

import { Pagination, Table, Input, Modal, Checkbox, Select, Layout, Menu, Button, Space } from 'antd';

import { TheContext } from '../Utils/TheContext';

import ProcessTableData from "./ListTable/ProcessTableData";

import {
    getTerms,
    getDates,
    getOptions,
} from "../Utils/Data";

const headerStyle = {
    height: 64,
    paddingInline: 0,
    lineHeight: '64px',
    backgroundColor: '#fff',
};

const { Header } = Layout;

function App() {

    const [dateList, setDateList] = useState( [] );

    const [termsList, setTermsList] = useState( [] );

    const [optionsData, setOptionsData] = useState( [] );

    const getDateList = async () => {
        const response = await getDates();
        const preparedData =  JSON.parse( response.data );
        setDateList( preparedData );
    }

    const getTermsList = async () => {
        const response = await getTerms();
        const preparedData =  JSON.parse( response.data );
        setTermsList( preparedData );
    }

    const getTheOptins = async () => {
        const response = await getOptions();
        const preparedData =  JSON.parse( response.data );
        setOptionsData( preparedData );
    }

    useEffect(() => {
        getDateList();
        getTermsList();
        getTheOptins();
    }, []  );

    return (
        <TheContext.Provider value={ { dateList, termsList, optionsData } }>
            <Layout className="tttme-App">
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
                    <ProcessTableData/>
                </Content>
            </Layout>

        </TheContext.Provider>

    );
}
export default App