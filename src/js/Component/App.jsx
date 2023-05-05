
import React, {useState, useEffect } from "react";

import { Layout } from 'antd';

import { TheAppContext } from '../Utils/TheContext';

import {
    getMedia,
    getOptions,
    updateOptins,
    upDateSingleMedia,
} from "../Utils/Data";

const { Sider } = Layout;

import MainHeader from "./MainHeader";

import Settings from "./Settings";

import {useStateValue} from "../Utils/StateProvider";

import * as Types from "../Utils/actionType";

import ProcessTableData from "./ListTable/ProcessTableData";

import RenamerTableData from "./Renamer/RenamerTableData";

function App() {

    const [stateValue, dispatch] = useStateValue();

    const [isUpdated, setIsUpdated] = useState(false );

    const getTheOptins = async () => {
        const response = await getOptions();
        const preparedData =  JSON.parse( response.data );
        await dispatch({
            type: Types.UPDATE_DATA_OPTIONS,
            options: {
                ...preparedData,
                isLoading: false,
            }
        });
    }

    const getTheMedia = async () => {
        const response = await getMedia('', stateValue.mediaData.postQuery );
        await dispatch({
            type: Types.GET_MEDIA_LIST,
            mediaData: {
                ...stateValue.mediaData,
                isLoading: false,
                ...response
            },
        })
    }


    const handleUpdateOption = async ( event ) => {
       const response = await updateOptins( stateValue.options );
        200 === parseInt( response.status ) && setIsUpdated( ! isUpdated );
    }

    const handleRenameFocusout = async ( ) => {
        const  currentItemEdited = stateValue.rename;
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
        getTheMedia();
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
                    { 'mediarename' === stateValue.selectedMenu && <RenamerTableData/> }
                    {/*{ 'imageotindatabase' === selectedMenu && <ProcessRenamerTableData/> }*/}
                    { 'settings' === stateValue.selectedMenu && Object.keys(stateValue.options).length ? <Settings/> : null }
                </Layout>
            </Layout>
        </TheAppContext.Provider>
    );
}

export default App