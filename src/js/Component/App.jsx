
import React, {useState, useEffect } from "react";

import { Layout } from 'antd';

import { TheAppContext } from '../Utils/TheContext';

import ProcessTableData from "./ListTable/ProcessTableData";

import ProcessRenamerTableData from "./Renamer/ProcessRenamerTableData";

import {
    getOptions,
    updateOptins, upDateSingleMedia,
} from "../Utils/Data";

const { Sider, Content } = Layout;

import MainHeader from "./MainHeader";
import Settings from "./Settings";
import {useStateValue} from "../Utils/StateProvider";

import * as Types from "../Utils/actionType";


function App() {

    const [stateValue, dispatch] = useStateValue();

    //const [ selectedMenu, setSelectedMenu] = useState(  );

    const [isUpdated, setIsUpdated] = useState(false );

    const getTheOptins = async () => {
        const response = await getOptions();
        const preparedData =  JSON.parse( response.data );
        dispatch({
            type: Types.UPDATE_DATA_OPTIONS,
            options : preparedData
        });
    }

    const handleUpdateOption = async ( event ) => {
       const response = await updateOptins( stateValue.options );
        200 === parseInt( response.status ) && setIsUpdated( ! isUpdated );
    }

    const handleRenameFocusout = async ( ) => {
        const  currentItemEdited = stateValue.rename;
        //console.log( stateValue )
        let edited =  stateValue.rename.postsdata.originalname && stateValue.rename.postsdata.originalname.localeCompare( stateValue.rename.newname );
        if( edited ){
            const response = await upDateSingleMedia( currentItemEdited );
            200 === parseInt( response.status ) && setIsUpdated( ! isUpdated );
        }
    }

    const handleSave = () => {
        switch ( stateValue.saveType ) {
            case Types.UPDATE_DATA_OPTIONS:
                    handleUpdateOption();
                break;
            case Types.UPDATE_RENAMER_MEDIA:
                    handleRenameFocusout();
                break;
            default:
        }
    }

    useEffect(() => {
        handleSave();
    }, [ stateValue.saveType ] );

    useEffect(() => {
        getTheOptins();
    }, [ isUpdated ] );


    return (
        <TheAppContext.Provider value={ {
            isUpdated,
            setIsUpdated
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
                    { 'mediatable' === stateValue.selectedMenu && <ProcessTableData/> }
                    { 'mediarename' === stateValue.selectedMenu && <ProcessRenamerTableData/> }
                    {/*{ 'imageotindatabase' === selectedMenu && <ProcessRenamerTableData/> }*/}
                    { 'settings' === stateValue.selectedMenu && Object.keys(stateValue.options).length ? <Settings/> : null }
                </Layout>
            </Layout>
        </TheAppContext.Provider>
    );
}

export default App