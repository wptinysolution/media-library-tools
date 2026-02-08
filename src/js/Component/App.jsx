import React, { useEffect } from "react";
import { HashRouter, Navigate, Route, Routes } from "react-router-dom";
import { Toaster } from 'react-hot-toast';

import {
    getTerms,
    getDates,
    getMedia,
    getOptions,
    updateOptins,
    upDateSingleMedia,
    submitBulkMediaAction
} from "@/js/Utils/Data";

import ProModal from "@/js/Component/ProModal";
import ImportButton from "@/js/Component/ExportImport/ImportButton";
import Settings from "@/js/Component/Settings";
import NeedSupport from "@/js/Component/NeedSupport";
import * as Types from "@/js/Utils/actionType";
import Datatable from "@/js/Component/ListTable/Datatable";
import { useStateValue } from "@/js/Utils/StateProvider";
import { defaultBulkSubmitData } from "@/js/Utils/UtilData";
import RenamerTableData from "@/js/Component/Renamer/RenamerTableData";
import RubbishFile from "@/js/Component/Rubbish/RubbishFile";
import PluginList from "@/js/Component/PluginList";
import ImageSize from "@/js/Component/ImageSize/ImageSize";
import ExportImportRoot from "@/js/Component/ExportImport/ExportImportRoot";
import ExportButton from "@/js/Component/ExportImport/ExportButton";
import MediaDownload from "@/js/Component/MediaDownload/MediaDownload";

function App() {
    const [stateValue, dispatch] = useStateValue();

    const getTheOptins = async () => {
        const response = await getOptions();
        const preparedData = await JSON.parse(response.data);
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
        const preparedDate = await JSON.parse(responseDate.data);
        const responseTerms = await getTerms();
        const preparedTerms = await JSON.parse(responseTerms.data);
        await dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                dateList: preparedDate,
                termsList: preparedTerms,
                isLoading: false,
            },
        })
    }

    const getTheMedia = async () => {
        const response = await getMedia(stateValue.mediaData.postQuery);
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
            bulkSubmitData: {
                ...stateValue.bulkSubmitData,
                bulkChecked: false,
                ids: []
            }
        });
    }

    const handleUpdateOption = async () => {
        const response = await updateOptins(stateValue.options);
        if (200 === parseInt(response.status)) {
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
        const currentItemEdited = stateValue.rename;
        let edited = stateValue.rename.postsdata.originalname && stateValue.rename.postsdata.originalname.localeCompare(stateValue.rename.newname);
        if (edited) {
            const response = await upDateSingleMedia(currentItemEdited);
            if (200 === parseInt(response.status)) {
                await getTheMedia()
            }
        }
    }

    const singleMediaUpdateContent = async (event) => {
        const response = await upDateSingleMedia(stateValue.singleMedia);
        if (200 === parseInt(response.status)) {
            // await getTheMedia()
        }
    }

    const handleBulkModalDataSave = async () => {
        await dispatch({
            type: Types.GET_MEDIA_LIST,
            mediaData: {
                ...stateValue.mediaData,
                isLoading: true
            },
        });
        const response = await submitBulkMediaAction(stateValue.bulkSubmitData);
        if (200 === parseInt(response.status) && response.data.updated) {
            await dispatch({
                type: Types.GET_MEDIA_LIST,
                mediaData: {
                    ...stateValue.mediaData,
                    isLoading: false,
                    postQuery: {
                        ...stateValue.mediaData.postQuery,
                        isUpdate: !stateValue.mediaData.postQuery.isUpdate,
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
            console.log('stateValue', stateValue)
        }
        console.log('submitBulkMediaAction');
    };

    const handleSave = () => {
        switch (stateValue.saveType) {
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
    }, [stateValue.saveType]);

    useEffect(() => {
        getTheOptins();
        getDateAndTermsList();
    }, []);

    useEffect(() => {
        getTheMedia();
    }, [stateValue.mediaData.postQuery]);

    return (
        <div className="p-2.5 bg-white rounded-lg shadow-[0_4px_40px_rgba(0,0,0,0.05)] min-h-screen">
            <HashRouter>
                <Routes>
                    <Route path="/" element={<Settings />} />
                    <Route path="/mediaTable" element={<Datatable />} />
                    <Route path="/mediaRename" element={<RenamerTableData />} />
                    <Route path="/exportImport" element={<ExportImportRoot />} />
                    <Route path="/import" element={<ImportButton />} />
                    <Route path="/export" element={<ExportButton />} />
                    <Route path="/imageSize" element={<ImageSize />} />
                    <Route path="/mediaDownload" element={<MediaDownload />} />
                    <Route path="/rubbishFile" element={<RubbishFile />} />
                    <Route path="/plugins" element={<PluginList />} />
                    <Route path="/support" element={<NeedSupport />} />
                    <Route path="*" element={<Navigate to="/" replace />} />
                </Routes>
            </HashRouter>
            <ProModal />
            <Toaster
                position="bottom-center"
                containerStyle={{
                    top: '50%',
                    left: '50%',
                    transform: 'translate(-50%, -50%)',
                    position: 'fixed',
                }}
                toastOptions={{
                    style: { fontSize: '1rem', color: '#fff', backgroundColor: 'var(--color-blue-600)' },
                    duration: 3000,
                }}
            />
        </div>
    );
}

export default App;