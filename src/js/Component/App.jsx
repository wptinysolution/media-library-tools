
import React, {useState, useEffect } from "react";

import { Layout } from 'antd';

import { TheAppContext } from '../Utils/TheContext';

import {
    UPDATE_DATA_OPTIONS,
    UPDATE_SINGLE_MEDIA
} from '../Utils/actionType';

import ProcessTableData from "./ListTable/ProcessTableData";

import ProcessRenamerTableData from "./Renamer/ProcessRenamerTableData";

import {
    getTerms,
    getDates,
    getOptions,
    updateOptins,
} from "../Utils/Data";

const { Sider, Content } = Layout;

import MainHeader from "./MainHeader";
import Settings from "./Settings";
import {useStateValue} from "../Utils/StateProvider";
import * as Type from "../Utils/actionType";
import * as Types from "../Utils/actionType";


function App() {

    const [stateValue, dispatch] = useStateValue();

    const [dateList, setDateList] = useState( [] );

    const [termsList, setTermsList] = useState( [] );

    const [ selectedMenu, setSelectedMenu] = useState( localStorage.getItem("current_menu") || 'mediatable' );

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
        dispatch({
            type: Type.UPDATE_DATA_OPTIONS,
            saveType: Types.UPDATE_DATA_OPTIONS,
            options : preparedData
        });
    }

    const handleUpdateOption = async ( event ) => {
       const response = await updateOptins( stateValue.options );
        if( 200 === parseInt( response.status ) ){
            getTheOptins();
        }
    }

    const handleSave = () => {
        switch ( stateValue.saveType ) {
            case UPDATE_DATA_OPTIONS:
                    handleUpdateOption();
                break;
            default:
        }
    }

    useEffect(() => {
        getTheOptins();
    }, [] );

    useEffect(() => {
        getDateList();
        getTermsList();
    }, []  );
    
    return (
        <TheAppContext.Provider value={ {
            dateList,
            termsList,
            isUpdated,
            setIsUpdated,
            selectedMenu,
            setSelectedMenu,
            handleSave
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
                    {/*{ 'mediatable' === selectedMenu && <ProcessTableData/> }*/}
                    {/*{ 'mediarename' === selectedMenu && <ProcessRenamerTableData/> }*/}
                    {/*{ 'imageotindatabase' === selectedMenu && <ProcessRenamerTableData/> }*/}
                    { 'settings' === selectedMenu && Object.keys(stateValue.options).length ? <Settings/> : null }
                </Layout>
            </Layout>
        </TheAppContext.Provider>
    );
}

export default App