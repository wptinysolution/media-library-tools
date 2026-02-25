import { useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { columns, defaultBulkSubmitData } from '@/js/Utils/UtilData';
import Loader from "@/js/Utils/Loader";
import TheHeader from "@/js/Component/ListTable/TheHeader";
import { useStore } from "@/js/Utils/store";
import BulkModal from "@/js/Component/ListTable/BulkModal";
import BulkModalForCSV from "@/js/Component/ListTable/BulkModalForCSV";
import MainHeader from "@/js/Component/MainHeader";
import DataTable from "@/js/Component/Common/DataTable";
import Pagination from "@/js/Component/Common/Pagination";

export default function Datatable() {
    const { mediaData, setMediaData, setBulkSubmitData, options, bulkSubmitData, bulkExport, generalData } = useStore();
    const { page: pageParam } = useParams<{ page?: string }>();

    const handlePagination = (current: number) => {
        setMediaData({
            isLoading: true,
            postQuery: {
                ...mediaData.postQuery,
                paged: current,
            }
        });
        setBulkSubmitData(defaultBulkSubmitData);
    };

    useEffect(() => {
        const pageFromUrl = parseInt(pageParam || '1', 10);
        if (pageFromUrl !== (mediaData.postQuery.paged || 1)) {
            handlePagination(pageFromUrl);
        }
    }, [pageParam]);

    const thecolumn = columns();
    const tablecolumn = thecolumn.filter((currentValue) => {
        if (!options.media_table_column || 'CheckboxID' === currentValue.key) {
            return true;
        }
        return options.media_table_column.includes(`${currentValue.key}`);
    });

    const renderModal = () => {
        if (bulkSubmitData.isModalOpen) return <BulkModal />;
        if (bulkExport.isModalOpen) return <BulkModalForCSV />;
        return null;
    };

    const totalPosts = mediaData.total_post || 0;
    const postsPerPage = mediaData.posts_per_page || 20;
    const currentPage = mediaData.paged || 1;
    const totalPages = Math.ceil(totalPosts / postsPerPage);
    const posts = mediaData.posts || [];

    return (
        <>
            <MainHeader />
            <div className="min-h-screen bg-gray-50">
                <TheHeader />
                {generalData.isLoading || mediaData.isLoading ? <Loader /> : (
                    <>
                        <div className="my-6 rounded-lg overflow-hidden">
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
