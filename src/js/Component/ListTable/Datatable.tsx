import { useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { columns, columnList, defaultBulkSubmitData, localStoreData } from '@/js/Utils/UtilData';
import Loader from "@/js/Utils/Loader";
import TheHeader from "@/js/Component/ListTable/TheHeader";
import { useStore } from "@/js/Utils/store";
import BulkModal from "@/js/Component/ListTable/BulkModal";
import BulkModalForCSV from "@/js/Component/ListTable/BulkModalForCSV";
import DataTable from "@/js/Component/Common/DataTable";
import Pagination from "@/js/Component/Common/Pagination";

export default function Datatable() {
    const { mediaData, setMediaData, setBulkSubmitData, options, setOptions, bulkSubmitData, bulkExport, generalData } = useStore();
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

    const onChangeColumnList = (key: string) => {
        const currentColumn = options.media_table_column;
        const newColumn = currentColumn.includes(key)
            ? currentColumn.filter((item: string) => item !== key)
            : [...currentColumn, key];
        setOptions({ media_table_column: newColumn });
        localStoreData('media_table_column', newColumn);
    };

    const totalPosts = mediaData.total_post || 0;
    const postsPerPage = mediaData.posts_per_page || 20;
    const currentPage = mediaData.paged || 1;
    const totalPages = Math.ceil(totalPosts / postsPerPage);
    const posts = mediaData.posts || [];

    return (
        <div className="min-h-screen bg-gray-50">
                <TheHeader />
                {generalData.isLoading ? <Loader /> : (
                    <>
                        <div className="mx-3 mt-3 px-4 py-3 bg-white border border-gray-200 rounded-lg flex flex-wrap items-center gap-x-3 gap-y-2">
                            <label className="inline-flex items-center gap-1.5 cursor-pointer">
                                <span className="text-sm font-medium text-gray-900 whitespace-nowrap">Per page:</span>
                                <input
                                    type="number"
                                    className="w-16 px-2! py-1.5! text-sm! text-gray-900! bg-white! border! border-gray-300! rounded-md! shadow-none! focus:outline-none! focus:border-blue-500! focus:ring-2! focus:ring-blue-500/20! focus:shadow-none! hover:border-gray-400!"
                                    value={options.media_per_page as number | string}
                                    onChange={(event) => { localStorage.setItem('mlt_media_per_page', event.target.value); setOptions({ media_per_page: event.target.value }); }}
                                    onBlur={() => setMediaData({ postQuery: { ...mediaData.postQuery, media_per_page: parseInt(String(options.media_per_page || 20), 10), paged: 1 } })}
                                />
                            </label>
                            <div className="inline-flex items-center gap-3 flex-wrap">
                                <span className="text-sm font-medium text-gray-900 whitespace-nowrap">Table Column:</span>
                                {columnList.map((column) => (
                                    <label key={column.key} className="inline-flex items-center gap-1.5 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            className="w-3.5 h-3.5 m-0! rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                            checked={options.media_table_column.includes(column.key)}
                                            onChange={() => onChangeColumnList(column.key)}
                                        />
                                        <span className="text-sm text-gray-700">{column.title}</span>
                                    </label>
                                ))}
                            </div>
                        </div>
                        <div className="my-2 mx-2 rounded-lg overflow-hidden">
                            <DataTable
                                columns={tablecolumn}
                                data={posts}
                                rowKey="ID"
                                loading={mediaData.isLoading}
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
    );
}
