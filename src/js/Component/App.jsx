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
import { useStore } from "@/js/Utils/store";
import { defaultBulkSubmitData } from "@/js/Utils/UtilData";
import RenamerTableData from "@/js/Component/Renamer/RenamerTableData";
import RubbishFile from "@/js/Component/Rubbish/RubbishFile";
import PluginList from "@/js/Component/PluginList";
import ImageSize from "@/js/Component/ImageSize/ImageSize";
import ExportImportRoot from "@/js/Component/ExportImport/ExportImportRoot";
import ExportButton from "@/js/Component/ExportImport/ExportButton";
import MediaDownload from "@/js/Component/MediaDownload/MediaDownload";

function App() {
    const {
        saveType, setSaveType,
        mediaData, setMediaData,
        options, setOptions,
        generalData, setGeneralData,
        singleMedia,
        rename,
        bulkSubmitData, setBulkSubmitData,
    } = useStore();

    const getTheOptins = async () => {
        const response = await getOptions();
        const preparedData = await JSON.parse(response.data);
        setOptions({ ...preparedData, isLoading: false });
    }

    const getDateAndTermsList = async () => {
        const responseDate = await getDates();
        const preparedDate = await JSON.parse(responseDate.data);
        const responseTerms = await getTerms();
        const preparedTerms = await JSON.parse(responseTerms.data);
        setGeneralData({ dateList: preparedDate, termsList: preparedTerms, isLoading: false });
    }

    const getTheMedia = async () => {
        const response = await getMedia(mediaData.postQuery);
        setMediaData({ ...response, isLoading: false });
        setBulkSubmitData({ bulkChecked: false, ids: [] });
    }

    const handleUpdateOption = async () => {
        const response = await updateOptins(options);
        if (200 === parseInt(response.status)) {
            await getTheOptins();
            setMediaData({
                postQuery: {
                    ...mediaData.postQuery,
                    media_per_page: options.media_per_page,
                },
            });
        }
    }

    const fileRenamerUpdateSingleMedia = async () => {
        const currentItemEdited = rename;
        let edited = rename.postsdata && rename.postsdata.originalname && rename.postsdata.originalname.localeCompare(rename.newname);
        if (edited) {
            const response = await upDateSingleMedia(currentItemEdited);
            if (200 === parseInt(response.status)) {
                await getTheMedia()
            }
        }
    }

    const singleMediaUpdateContent = async () => {
        await upDateSingleMedia(singleMedia);
    }

    const handleBulkModalDataSave = async () => {
        setMediaData({ isLoading: true });
        const response = await submitBulkMediaAction(bulkSubmitData);
        if (200 === parseInt(response.status) && response.data.updated) {
            setMediaData({
                isLoading: false,
                postQuery: {
                    ...mediaData.postQuery,
                    isUpdate: !mediaData.postQuery.isUpdate,
                },
            });
            setBulkSubmitData({ ...defaultBulkSubmitData, type: bulkSubmitData.type });
            setSaveType(null);
            console.log('bulkSubmitData', bulkSubmitData)
        }
        console.log('submitBulkMediaAction');
    };

    const handleSave = () => {
        switch (saveType) {
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
    }, [saveType]);

    useEffect(() => {
        getTheOptins();
        getDateAndTermsList();
    }, []);

    useEffect(() => {
        getTheMedia();
    }, [mediaData.postQuery]);

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
