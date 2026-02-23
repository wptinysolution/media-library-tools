import React, { useEffect } from "react";

import { renamerColumns } from '@/js/Utils/UtilData';

import RenamerMainHeader from "./RenamerMainHeader";

import { useStore } from "@/js/Utils/store";

import Loader from "@/js/Utils/Loader";

import MainHeader from "@/js/Component/MainHeader";

import DataTable from "@/js/Component/Common/DataTable";

import Pagination from "@/js/Component/Common/Pagination";

function RenamerTableData() {

    const { mediaData, setMediaData } = useStore();

    const RenameTableColumns = renamerColumns();

    const handlePagination = (current) => {
        setMediaData({
            postQuery: {
                ...mediaData.postQuery,
                paged: current,
                orderby: 'id',
                order: 'DESC'
            }
        });
    };

    const setRenamerMainQuery = () => {
        if (mediaData.postQuery.filtering) {
            setMediaData({
                postQuery: {
                    status: null,
                    filtering: false,
                    order: 'DESC',
                    orderby: 'id',
                    paged: 1,
                    isUpdate: false,
                }
            });
        }
    };

    useEffect(() => {
        setRenamerMainQuery();
    }, []);

    const totalPosts = mediaData.total_post || 0;
    const postsPerPage = mediaData.posts_per_page || 20;
    const currentPage = mediaData.paged || 1;
    const totalPages = Math.ceil(totalPosts / postsPerPage);
    const posts = mediaData.posts || [];

    return (
        <>
            <MainHeader />
            <div className="min-h-screen bg-gray-50">
                <RenamerMainHeader />
                {mediaData.isLoading || mediaData.total_post < 0 ? <Loader /> : (
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
