
import React, {useState, useEffect } from "react";

import { Layout } from 'antd';

import { TheAppContext } from '../Utils/TheContext';

import ProcessTableData from "./ListTable/ProcessTableData";

import {
    getTerms,
    getDates,
    getOptions,
    updateOptins,
} from "../Utils/Data";

const { Sider, Content } = Layout;

import MainHeader from "./MainHeader";
import Settings from "./Settings";


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
        getTheOptins();
    }, [isUpdated, selectedMenu ] );

    useEffect(() => {
        getDateList();
        getTermsList();
    }, []  );

    console.log( tsmltParams.settings );

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
            <Layout className="tttme-App" style={{
                padding: '10px',
                background: '#fff',
                borderRadius: '5px',
                boxShadow: '0 4px 40px rgb(0 0 0 / 5%)',
                height: 'calc( 100vh - 110px )',
            }}>
                <Sider style={{
                    borderRadius: '5px',
                }}>
                    <MainHeader/>
                </Sider>
                <Layout className="layout" style={{
                    padding: '10px',
                    overflowY: 'auto'
                }} >
                    { 'mediatable' === selectedMenu && <ProcessTableData/> }
                    { 'settings' === selectedMenu && Object.keys(optionsData).length ? <Settings/> : null }
                </Layout>
            </Layout>
        </TheAppContext.Provider>
    );
}

export default App