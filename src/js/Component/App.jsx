import React, { useEffect } from "react";

import { Layout } from 'antd';

import {
    getTerms,
    getDates,
    getMedia,
    getOptions,
    updateOptins,
    upDateSingleMedia,
    submitBulkMediaAction
} from "../Utils/Data";

const { Sider } = Layout;

import Settings from "./Settings";

import MainHeader from "./MainHeader";

import * as Types from "../Utils/actionType";

import Datatable from "./ListTable/Datatable";

import { useStateValue } from "../Utils/StateProvider";

import { defaultBulkSubmitData } from "../Utils/UtilData";

import RenamerTableData from "./Renamer/RenamerTableData";

import RabbisFile from "./RabbisFile/RabbisFile";

function App() {

    const [ stateValue, dispatch ] = useStateValue();

    const getTheOptins = async () => {
        const response = await getOptions();
        const preparedData =  await JSON.parse( response.data );
        await dispatch({
            type: Types.UPDATE_OPTIONS,
            options: {
                ...preparedData,
                isLoading: false,
            }
        });
        console.log( 'getOptions' );
    }

    const getDateAndTermsList = async () => {
        const responseDate = await getDates();
        const preparedDate =  await JSON.parse( responseDate.data );
        const responseTerms = await getTerms();
        const preparedTerms =  await JSON.parse( responseTerms.data );
        await dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                dateList : preparedDate,
                termsList : preparedTerms,
                isLoading : false,
            },
        })
        console.log( 'getDates' );
        console.log( 'getTerms' );
    }

    const getTheMedia = async () => {
        const response = await getMedia('', stateValue.mediaData.postQuery );
        await dispatch({
            type: Types.GET_MEDIA_LIST,
            mediaData: {
                ...stateValue.mediaData,
                ...response,
                isLoading: false
            },
        });
        console.log( 'getMedia' );
    }

    const handleUpdateOption = async () => {
       const response = await updateOptins( stateValue.options );
       if( 200 === parseInt( response.status ) ){
           await getTheOptins();
           await dispatch({
               type: Types.GET_MEDIA_LIST,
               mediaData: {
                   ...stateValue.mediaData,
                   postQuery: {
                       ...stateValue.mediaData.postQuery,
                       media_per_page: stateValue.options.media_per_page,
                   },
               },
           });
       }
       console.log( 'handleUpdateOption' );
    }

    const fileRenamerUpdateSingleMedia = async () => {
        const  currentItemEdited = stateValue.rename;
        let edited =  stateValue.rename.postsdata.originalname && stateValue.rename.postsdata.originalname.localeCompare( stateValue.rename.newname );
        if( edited ){
            const response = await upDateSingleMedia( currentItemEdited );
            if( 200 === parseInt( response.status ) ) {
                await getTheMedia()
            }
            console.log( 'upDateSingleMedia' );
        }
    }

    const singleMediaUpdateContent = async ( event ) => {
        const response = await upDateSingleMedia( stateValue.singleMedia );
        if( 200 === parseInt( response.status ) ) {
            await getTheMedia()
        }
        console.log( 'upDateSingleMedia' );
    }

    const handleBulkModalDataSave = async () => {
        const response = await submitBulkMediaAction( stateValue.bulkSubmitData );
        if( 200 === parseInt( response.status ) && response.data.updated ){
            await dispatch({
                type: Types.GET_MEDIA_LIST,
                mediaData: {
                    ...stateValue.mediaData,
                    postQuery: {
                        ...stateValue.mediaData.postQuery,
                        isUpdate: ! stateValue.mediaData.postQuery.isUpdate,
                    },
                },
            });
            await dispatch({
                ...stateValue,
                type: Types.BULK_SUBMIT,
                saveType: null,
                bulkSubmitData: {
                    ...defaultBulkSubmitData,
                    type: stateValue.bulkSubmitData.type,
                },
            });
            console.log( stateValue )
        }
        console.log( 'submitBulkMediaAction' );
    };

    const handleSave = () => {
        switch ( stateValue.saveType ) {
            case Types.UPDATE_OPTIONS:
                    handleUpdateOption();
                break;
            case Types.UPDATE_RENAMER_MEDIA:
                    fileRenamerUpdateSingleMedia();
                break;
            case Types.UPDATE_SINGLE_MEDIA:
                    singleMediaUpdateContent();
                break;
            case Types.BULK_SUBMIT:
                    handleBulkModalDataSave();
                break;
            default:
        }
    }

    useEffect(() => {
        handleSave();
    }, [ stateValue.saveType ] );
    
    useEffect(() => {
        getTheOptins();
        getDateAndTermsList();
    }, [] );

    useEffect(() => {
        getTheMedia();
    }, [ stateValue.mediaData.postQuery ] );

    return (
            <Layout className="tttme-App" style={{
                padding: '10px',
                background: '#fff',
                borderRadius: '5px',
                boxShadow: '0 4px 40px rgb(0 0 0 / 5%)',
                height: 'calc( 100vh - 110px )',
            }}>
                <Sider style={{ borderRadius: '5px' }}>
                    <MainHeader/>
                </Sider>
                <Layout className="layout" style={{ padding: '10px', overflowY: 'auto' }} >
                    { 'settings' === stateValue.generalData.selectedMenu && <Settings/>  }
                    { 'mediatable' === stateValue.generalData.selectedMenu && <Datatable /> }
                    { 'mediarename' === stateValue.generalData.selectedMenu && <RenamerTableData/> }
                    { 'rubbishfile' === stateValue.generalData.selectedMenu && <RabbisFile/> }
                </Layout>
            </Layout>
    );
}

export default App