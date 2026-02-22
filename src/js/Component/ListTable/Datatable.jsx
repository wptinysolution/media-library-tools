import React from "react";

import { columns, defaultBulkSubmitData } from '@/js/Utils/UtilData';

import Loader from "@/js/Utils/Loader";

import TheHeader from "@/js/Component/ListTable/TheHeader";

import { useStateValue } from "@/js/Utils/StateProvider";

import BulkModal from "@/js/Component/ListTable/BulkModal";

import BulkModalForCSV from "@/js/Component/ListTable/BulkModalForCSV";

import MainHeader from "@/js/Component/MainHeader";

import * as Types from "@/js/Utils/actionType";

import DataTable from "@/js/Component/Common/DataTable";

import Pagination from "@/js/Component/Common/Pagination";

export default function Datatable() {

    const [stateValue, dispatch] = useStateValue();

    const handlePagination = (current) => {
        dispatch({
            type: Types.GET_MEDIA_LIST,
            mediaData: {
                ...stateValue.mediaData,
                isLoading: true,
                postQuery: {
                    ...stateValue.mediaData.postQuery,
                    paged: current
                }
            },
        });
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: defaultBulkSubmitData,
        });
    };

    const thecolumn = columns();
    const tablecolumn = thecolumn.filter((currentValue) => {
        if (!stateValue.options.media_table_column || 'CheckboxID' === currentValue.key) {
            return true;
        }
        return stateValue.options.media_table_column.includes(`${currentValue.key}`);
    });

    const renderModal = () => {
        if (stateValue.bulkSubmitData.isModalOpen) return <BulkModal />;
        if (stateValue.bulkExport.isModalOpen) return <BulkModalForCSV />;
        return null;
    };

    const totalPosts = stateValue.mediaData.total_post || 0;
    const postsPerPage = stateValue.mediaData.posts_per_page || 20;
    const currentPage = stateValue.mediaData.paged || 1;
    const totalPages = Math.ceil(totalPosts / postsPerPage);
    const posts = stateValue.mediaData.posts || [];

    return (
        <>
            <MainHeader />
            <div className="min-h-screen bg-gray-50">
                <TheHeader />
                {stateValue.generalData.isLoading || stateValue.mediaData.isLoading ? <Loader /> : (
                    <>
                        <div>
                            <DataTable
                                columns={tablecolumn}
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
                        </div>
                        {renderModal()}
                    </>
                )}
            </div>
        </>
    );
}
