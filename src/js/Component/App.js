
import React, {useState, useEffect } from "react";

import { Pagination, Table, Input, Modal, Checkbox, Select, Layout, Menu, Button, Space } from 'antd';

import { TheContext } from '../Utils/TheContext';

import ProcessTableData from "./ListTable/ProcessTableData";

import MainHeader from "./MainHeader";

import {
    getTerms,
    getDates,
    getOptions,
} from "../Utils/Data";

const {
    Header,
    Content
} = Layout;

function App() {

    const [dateList, setDateList] = useState( [] );

    const [termsList, setTermsList] = useState( [] );

    const [optionsData, setOptionsData] = useState( [] );

    const [ selectedMenu, setSelectedMenu] = useState( 'mediatbale' );

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
        <TheContext.Provider value={ {
            dateList,
            termsList,
            optionsData,
            setOptionsData,
            setSelectedMenu
        } }>
            <Layout className="tttme-App">
                <MainHeader/>
                <Content>
                    { 'mediatbale' === selectedMenu && <ProcessTableData/> }
                    { 'settings' === selectedMenu && `Hello` }
                </Content>
            </Layout>
        </TheContext.Provider>

    );
}
export default App