import { useEffect } from "react";
import { useParams } from 'react-router-dom';
import { renamerColumns } from '@/js/Utils/UtilData';
import RenamerMainHeader from "./RenamerMainHeader";
import { useStore } from "@/js/Utils/store";
import Loader from "@/js/Utils/Loader";
import DataTable from "@/js/Component/Common/DataTable";
import Pagination from "@/js/Component/Common/Pagination";

function RenamerTableData() {
    const { mediaData, setMediaData, options, setOptions } = useStore();
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
                {mediaData.isLoading || mediaData.total_post < 0 ? <Loader /> : (
                    <>
                        <div className="mx-6 mt-6 px-4 py-3 bg-white border border-gray-200 rounded-lg flex items-center">
                            <div className="flex items-center gap-1.5">
                                <label className="text-sm text-gray-500 whitespace-nowrap">Per page:</label>
                                <input
                                    type="number"
                                    className="w-16 px-2! py-1.5! text-sm! text-gray-900! bg-white! border! border-gray-300! rounded-md! shadow-none! focus:outline-none! focus:border-blue-500! focus:ring-2! focus:ring-blue-500/20! focus:shadow-none! hover:border-gray-400!"
                                    value={options.media_per_page as number | string}
                                    onChange={(event) => { localStorage.setItem('mlt_media_per_page', event.target.value); setOptions({ media_per_page: event.target.value }); }}
                                    onBlur={() => setMediaData({ postQuery: { ...mediaData.postQuery, media_per_page: parseInt(String(options.media_per_page || 20), 10), paged: 1 } })}
                                />
                            </div>
                        </div>
                    <div className="my-6 mx-2 rounded-lg overflow-hidden ">
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
                    </>
                )}
        </div>
    );
}

export default RenamerTableData;
