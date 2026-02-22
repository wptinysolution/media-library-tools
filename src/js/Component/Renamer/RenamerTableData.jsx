import React, { useEffect } from "react";

import { renamerColumns } from '@/js/Utils/UtilData';

import RenamerMainHeader from "./RenamerMainHeader";

import { useStateValue } from "@/js/Utils/StateProvider";

import Loader from "@/js/Utils/Loader";

import * as Types from "@/js/Utils/actionType";

import MainHeader from "@/js/Component/MainHeader";

import DataTable from "@/js/Component/Common/DataTable";

import Pagination from "@/js/Component/Common/Pagination";

function RenamerTableData() {

    const [stateValue, dispatch] = useStateValue();

    const RenameTableColumns = renamerColumns();

    const handlePagination = (current) => {
        dispatch({
            type: Types.GET_MEDIA_LIST,
            mediaData: {
                ...stateValue.mediaData,
                postQuery: {
                    ...stateValue.mediaData.postQuery,
                    paged: current,
                    orderby: 'id',
                    order: 'DESC'
                }
            },
        });
    };

    const setRenamerMainQuery = () => {
        if (stateValue.mediaData.postQuery.filtering) {
            dispatch({
                type: Types.GET_MEDIA_LIST,
                mediaData: {
                    ...stateValue.mediaData,
                    postQuery: {
                        status: null,
                        filtering: false,
                        order: 'DESC',
                        orderby: 'id',
                        paged: 1,
                        isUpdate: false,
                    }
                },
            });
        }
    };

    useEffect(() => {
        setRenamerMainQuery();
    }, []);

    const totalPosts = stateValue.mediaData.total_post || 0;
    const postsPerPage = stateValue.mediaData.posts_per_page || 20;
    const currentPage = stateValue.mediaData.paged || 1;
    const totalPages = Math.ceil(totalPosts / postsPerPage);
    const posts = stateValue.mediaData.posts || [];

    return (
        <>
            <MainHeader />
            <div className="min-h-screen bg-gray-50">
                <RenamerMainHeader />
                {stateValue.mediaData.isLoading || stateValue.mediaData.total_post < 0 ? <Loader /> : (
                    <>
                        <DataTable
                            columns={RenameTableColumns}
                            data={posts}
                            rowKey="ID"
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
            </div>
        </>
    );
}

export default RenamerTableData;
