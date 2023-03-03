
import React, {useState, useEffect } from "react";

import { Layout } from 'antd';

import MainHeader from "./MainHeader";

import { TheAppContext } from '../Utils/TheContext';

import ProcessTableData from "./ListTable/ProcessTableData";

import {
    getTerms,
    getDates,
    getOptions,
    updateOptins,
} from "../Utils/Data";

const { Content } = Layout;

function App() {

    const [dateList, setDateList] = useState( [] );

    const [termsList, setTermsList] = useState( [] );

    const [optionsData, setOptionsData] = useState( [] );

    const [ selectedMenu, setSelectedMenu] = useState( 'mediatable' );

    const [isUpdated, setIsUpdated] = useState(false );

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

    const handleUpdateOption = async ( event ) => {
        const response = await updateOptins( optionsData );
        200 === parseInt( response.status ) && setIsUpdated( ! isUpdated );
    }

    useEffect(() => {
        getDateList();
        getTermsList();
        getTheOptins();
    }, []  );

    return (
        <TheAppContext.Provider value={ {
            dateList,
            termsList,
            optionsData,
            setOptionsData,
            handleUpdateOption,
            isUpdated,
            setIsUpdated,
            selectedMenu,
            setSelectedMenu
        } }>
            <Layout className="tttme-App">
                <MainHeader/>
                <Content>
                    { 'mediatable' === selectedMenu && <ProcessTableData/> }
                    { 'settings' === selectedMenu && `Hello` }
                </Content>
            </Layout>
        </TheAppContext.Provider>
    );
}

export default App