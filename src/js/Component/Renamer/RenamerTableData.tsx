import { useEffect } from "react";
import { useParams } from 'react-router-dom';
import { renamerColumns } from '@/js/Utils/UtilData';
import RenamerMainHeader from "./RenamerMainHeader";
import { useStore } from "@/js/Utils/store";
import Loader from "@/js/Utils/Loader";
import DataTable from "@/js/Component/Common/DataTable";
import Pagination from "@/js/Component/Common/Pagination";

function RenamerTableData() {
    const { mediaData, setMediaData } = useStore();
    const { page: pageParam } = useParams<{ page?: string }>();

    const RenameTableColumns = renamerColumns();

    const handlePagination = (current: number) => {
        setMediaData({
            postQuery: {
                ...mediaData.postQuery,
                paged: current,
                orderby: 'id',
                order: 'DESC',
            }
        });
    };

    useEffect(() => {
        const pageFromUrl = parseInt(pageParam || '1', 10);
        if (pageFromUrl !== (mediaData.postQuery.paged || 1)) {
            handlePagination(pageFromUrl);
        }
    }, [pageParam]);

    const setRenamerMainQuery = () => {
        if (mediaData.postQuery.filtering) {
            setMediaData({
                postQuery: {
                    status: null,
                    filtering: false,
                    media_per_page: mediaData.postQuery.media_per_page,
                    searchKeyWords: null,
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
        <div className="min-h-screen bg-gray-50">
                <RenamerMainHeader />
                <>
                    {mediaData.isLoading || mediaData.total_post < 0 ? <Loader /> : (
                        <div className="my-6 mx-2 rounded-lg overflow-hidden">
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
                        </div>
                    )}
                </>
        </div>
    );
}

export default RenamerTableData;
