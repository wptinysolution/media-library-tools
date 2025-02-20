import React, { useEffect } from "react";

import {Layout} from 'antd';

import {
    getTerms,
    getDates,
    getMedia,
    getOptions,
    updateOptins,
    upDateSingleMedia,
    submitBulkMediaAction
} from "../Utils/Data";

import {HashRouter, Navigate, Route, Routes} from "react-router-dom";

import ProModal from "./ProModal";

import ExportImportButton from "./ExportImport/ExportImportButton";

import Settings from "./Settings";

import NeedSupport from "./NeedSupport";

import * as Types from "../Utils/actionType";

import Datatable from "./ListTable/Datatable";

import { useStateValue } from "../Utils/StateProvider";

import { defaultBulkSubmitData } from "../Utils/UtilData";

import RenamerTableData from "./Renamer/RenamerTableData";

import RubbishFile from "./Rubbish/RubbishFile";

import PluginList from "./PluginList";
import ImageSize from "./ImageSize/ImageSize";

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
    }

    const getTheMedia = async () => {
        const response = await getMedia( stateValue.mediaData.postQuery );
        await dispatch({
            type: Types.GET_MEDIA_LIST,
            mediaData: {
                ...stateValue.mediaData,
                ...response,
                isLoading: false
            },
        });
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData:{
                ...stateValue.bulkSubmitData,
                bulkChecked : false,
                ids: []
            }
        });
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
    }

    const fileRenamerUpdateSingleMedia = async () => {
        const  currentItemEdited = stateValue.rename;
        let edited =  stateValue.rename.postsdata.originalname && stateValue.rename.postsdata.originalname.localeCompare( stateValue.rename.newname );
        if( edited ){
            const response = await upDateSingleMedia( currentItemEdited );
            if( 200 === parseInt( response.status ) ) {
                await getTheMedia()
            }
        }
    }

    const singleMediaUpdateContent = async ( event ) => {
        const response = await upDateSingleMedia( stateValue.singleMedia );
        if( 200 === parseInt( response.status ) ) {
           // await getTheMedia()
        }

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
            console.log( 'stateValue', stateValue )
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
                minHeight: '100vh',
                // height: 'calc( 100vh - 50px )',
            }}>
                <HashRouter>
                    <Routes>
                        <Route path="/" element={<Settings/>}/>
                        <Route path="/mediaTable" element={<Datatable/>}/>
                        <Route path="/mediaRename" element={<RenamerTableData/>}/>
                        <Route path="/exportImport" element={<ExportImportButton/>}/>
                        <Route path="/imageSize" element={<ImageSize/>}/>
                        <Route path="/rubbishFile" element={<RubbishFile/>}/>
                        <Route path="/plugins" element={<PluginList/>}/>
                        <Route path="/support" element={<NeedSupport/>}/>
                        <Route path="*" element={<Navigate to="/" replace/>}/>
                    </Routes>
                </HashRouter>
                <ProModal/>
            </Layout>
    );
}

export default App