import React, { useEffect } from "react";

import { useStateValue } from "@/js/Utils/StateProvider";

import RubbishHeader from "./RubbishHeader";

import Loader from "@/js/Utils/Loader";

import * as Types from "@/js/Utils/actionType";

import { RubbishFileColumns } from "@/js/Utils/UtilData";

import { getRubbishFile } from "@/js/Utils/Data";

import DirectoryModal from "./DirectoryModal";

import RubbishNotice from "./RubbishNotice";

import MainHeader from "@/js/Component/MainHeader";

import DataTable from "@/js/Component/Common/DataTable";

import Pagination from "@/js/Component/Common/Pagination";

function RubbishFile() {

    const [stateValue, dispatch] = useStateValue();

    const getTheRubbishFile = async () => {
        const rubbishFile = await getRubbishFile(stateValue.rubbishMedia.postQuery);
        await dispatch({
            type: Types.RUBBISH_MEDIA,
            rubbishMedia: {
                ...stateValue.rubbishMedia,
                isLoading: false,
                mediaFile: rubbishFile.mediaFile,
                paged: rubbishFile.paged,
                totalPost: rubbishFile.totalPost,
                postsPerPage: rubbishFile.postsPerPage
            }
        });
        dispatch({
            type: Types.BALK_RUBBISH,
            bulkRubbishData: {
                ...stateValue.bulkRubbishData,
                bulkChecked: false,
                files: [],
                ids: [],
            },
        });
        console.log('getTheRubbishFile');
    };

    const handlePagination = (current) => {
        dispatch({
            type: Types.RUBBISH_MEDIA,
            rubbishMedia: {
                ...stateValue.rubbishMedia,
                postQuery: {
                    ...stateValue.rubbishMedia.postQuery,
                    paged: current,
                    isQueryUpdate: true
                }
            },
        });
    };

    const rubbishColumns = RubbishFileColumns();

    useEffect(() => {
        getTheRubbishFile();
    }, [stateValue.rubbishMedia.postQuery, stateValue.saveType]);

    const totalPosts = stateValue.rubbishMedia.totalPost || 0;
    const postsPerPage = stateValue.rubbishMedia.postsPerPage || 20;
    const currentPage = stateValue.rubbishMedia.paged || 1;
    const totalPages = Math.ceil(totalPosts / postsPerPage);
    const posts = stateValue.rubbishMedia.mediaFile || [];

    return (
        <>
            <MainHeader />
            <div className="min-h-screen bg-gray-50">
                <RubbishHeader />
                {stateValue.rubbishMedia.isLoading ? <Loader /> : (
                    <>
                        <DataTable
                            columns={rubbishColumns}
                            data={posts}
                            rowKey={(record, index) => (Math.random() + 1).toString(36).substring(7)}
                        />
                        <Pagination
                            currentPage={currentPage}
                            totalPages={totalPages}
                            totalPosts={totalPosts}
                            postsPerPage={postsPerPage}
                            onPageChange={handlePagination}
                        />
                    </>
                )}
                <DirectoryModal />
                <RubbishNotice />
            </div>
        </>
    );
}
export default RubbishFile;
