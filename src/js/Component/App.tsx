import { useEffect } from "react";
import { useWpAdminBarHeight, useWpMenuWidth } from "@/js/Utils/Hooks";
import { HashRouter, Navigate, Route, Routes } from "react-router-dom";
import { Toaster } from 'react-hot-toast';

import {
    getTerms,
    getDates,
    getMedia,
    getOptions,
    updateOptins,
    upDateSingleMedia,
    submitBulkMediaAction,
} from "@/js/Utils/Data";
import * as Types from "@/js/Utils/actionType";
import MainHeader from "@/js/Component/MainHeader";
import TopBar from "@/js/Component/TopBar";
import ProModal from "@/js/Component/ProModal";
import ImportButton from "@/js/Component/ExportImport/ImportButton";
import Settings from "@/js/Component/Settings";
import NeedSupport from "@/js/Component/NeedSupport";
import Datatable from "@/js/Component/ListTable/Datatable";
import { useStore } from "@/js/Utils/store";
import { defaultBulkSubmitData } from "@/js/Utils/UtilData";
import RenamerTableData from "@/js/Component/Renamer/RenamerTableData";
import RubbishFile from "@/js/Component/Rubbish/RubbishFile";
import PluginList from "@/js/Component/PluginList";
import ImageSize from "@/js/Component/ImageSize/ImageSize";
import ExportButton from "@/js/Component/ExportImport/ExportButton";
import MediaDownload from "@/js/Component/MediaDownload/MediaDownload";
import DuplicatePage from "@/js/Component/Duplicate/DuplicatePage";
import UsedWherePage from "@/js/Component/UsedWhere/UsedWherePage";
import ExifStripperPage from "@/js/Component/ExifStripper/ExifStripperPage";
import RegenerateInit from "@/js/Component/Regenerate/RegenerateInit";
import ProUpgradeBanner from "@/js/Component/ProUpgradeBanner";

function App() {
    const {
        saveType, setSaveType,
        mediaData, setMediaData,
        options, setOptions,
        setGeneralData,
        generalData,
        singleMedia,
        rename,
        bulkSubmitData, setBulkSubmitData,
    } = useStore();

    const getTheOptins = async () => {
        const response = await getOptions() as { data: string };
        const preparedData = JSON.parse(response.data);
        setOptions({
            ...preparedData,
            isLoading: false,
            media_per_page: parseInt(localStorage.getItem('mlt_media_per_page') || '20', 10),
            rubbish_per_page: parseInt(localStorage.getItem('mlt_rubbish_per_page') || '20', 10),
        });
    };

    const getDateAndTermsList = async () => {
        const responseDate = await getDates() as { data: string };
        const preparedDate = JSON.parse(responseDate.data) as Array<{ value: string; label: string }>;
        const responseTerms = await getTerms() as { data: string };
        const preparedTerms = JSON.parse(responseTerms.data) as Array<{ value: string; label: string }>;
        setGeneralData({ dateList: preparedDate, termsList: preparedTerms, isLoading: false });
    };

    const getTheMedia = async () => {
        setMediaData({ isLoading: true });
        const response = await getMedia(mediaData.postQuery);
        setMediaData({ ...response, isLoading: false });
        setBulkSubmitData({ bulkChecked: false, ids: [] });
    };

    const handleUpdateOption = async () => {
        const response = await updateOptins(options) as { status: number | string };
        if (200 === parseInt(String(response.status))) {
            await getTheOptins();
        }
        setSaveType(null);
    };

    const fileRenamerUpdateSingleMedia = async () => {
        const currentItemEdited = rename;
        const edited = rename.postsdata && rename.postsdata.originalname && rename.postsdata.originalname.localeCompare(rename.newname ?? '');
        if (edited) {
            const response = await upDateSingleMedia(currentItemEdited) as { status: number | string };
            if (200 === parseInt(String(response.status))) {
                // Silent refresh — no isLoading flag so the table doesn't flash a loader
                const refreshed = await getMedia(mediaData.postQuery);
                setMediaData({ ...refreshed, isLoading: false });
            }
        }
        setSaveType(null);
    };

    const singleMediaUpdateContent = async () => {
        await upDateSingleMedia(singleMedia);
        setSaveType(null);
    };

    const handleBulkModalDataSave = async () => {
        setMediaData({ isLoading: true });
        const response = await submitBulkMediaAction(bulkSubmitData) as {
            status: number | string;
            data: { updated: boolean };
        };
        if (200 === parseInt(String(response.status)) && response.data.updated) {
            setMediaData({
                isLoading: false,
                postQuery: {
                    ...mediaData.postQuery,
                    isUpdate: !mediaData.postQuery.isUpdate,
                },
            });
            setBulkSubmitData({ ...defaultBulkSubmitData, type: bulkSubmitData.type });
            setSaveType(null);
        }
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
    };

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

    const adminBarHeight = useWpAdminBarHeight();
    const wpMenuWidth = useWpMenuWidth();
    const sidebarWidth = generalData.sidebarCollapsed ? 48 : 200;

    return (
        <HashRouter>
            <TopBar />
            <MainHeader />
            <div
                className={'tsmlt-main-content-section z-9 bg-white overflow-y-auto'}
                style={{
                    position: 'fixed',
                    top: adminBarHeight + 56,
                    left: wpMenuWidth + sidebarWidth,
                    right: 0,
                    bottom: 0,
                    transition: 'left 200ms ease-in-out',
                }}
            >
                <Routes>
                    <Route path="/" element={<Settings />} />
                    <Route path="/mediaTable" element={<Datatable />} />
                    <Route path="/mediaTable/page/:page" element={<Datatable />} />
                    <Route path="/mediaRename" element={<RenamerTableData />} />
                    <Route path="/mediaRename/page/:page" element={<RenamerTableData />} />
                    <Route path="/exportImport" element={<Navigate to="/export" replace />} />
                    <Route path="/export" element={<ExportButton />} />
                    <Route path="/import" element={<ImportButton />} />
                    <Route path="/imageSize" element={<ImageSize />} />
                    <Route path="/regenerate" element={<RegenerateInit />} />
                    <Route path="/mediaDownload" element={<MediaDownload />} />
                    <Route path="/rubbishFile" element={<RubbishFile />} />
                    <Route path="/rubbishFile/page/:page" element={<RubbishFile />} />
                    <Route path="/duplicates" element={<DuplicatePage />} />
                    <Route path="/duplicates/page/:page" element={<DuplicatePage />} />
                    <Route path="/usedWhere" element={<UsedWherePage />} />
                    <Route path="/usedWhere/:filter" element={<UsedWherePage />} />
                    <Route path="/usedWhere/:filter/page/:page" element={<UsedWherePage />} />
                    <Route path="/exifStripper" element={<ExifStripperPage />} />
                    <Route path="/exifStripper/page/:page" element={<ExifStripperPage />} />
                    <Route path="/plugins" element={<PluginList />} />
                    <Route path="/support" element={<NeedSupport />} />
                    <Route path="*" element={<Navigate to="/" replace />} />
                </Routes>
                <ProUpgradeBanner />
            </div>
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
        </HashRouter>
    );
}

export default App;
