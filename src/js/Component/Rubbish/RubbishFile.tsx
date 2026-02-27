import { useEffect } from "react";
import { useParams } from 'react-router-dom';
import { useStore } from "@/js/Utils/store";
import RubbishHeader from "./RubbishHeader";
import Loader from "@/js/Utils/Loader";
import { RubbishFileColumns } from "@/js/Utils/UtilData";
import { getRubbishFile } from "@/js/Utils/Data";
import DirectoryModal from "./DirectoryModal";
import RubbishNotice from "./RubbishNotice";
import DataTable from "@/js/Component/Common/DataTable";
import Pagination from "@/js/Component/Common/Pagination";
import type { RubbishMediaFile } from "@/js/Utils/store";

function RubbishFile() {
    const { saveType, rubbishMedia, setRubbishMedia, setBulkRubbishData, options, setOptions } = useStore();
    const { page: pageParam } = useParams<{ page?: string }>();

    const getTheRubbishFile = async () => {
        const rubbishFile = await getRubbishFile(rubbishMedia.postQuery) as {
            mediaFile: RubbishMediaFile[];
            paged: number;
            totalPost: number;
            postsPerPage: number;
        };
        setRubbishMedia({
            isLoading: false,
            mediaFile: rubbishFile.mediaFile,
            paged: rubbishFile.paged,
            totalPost: rubbishFile.totalPost,
            postsPerPage: rubbishFile.postsPerPage,
        });
        setBulkRubbishData({
            bulkChecked: false,
            files: [],
            ids: [],
        });
        console.log('getTheRubbishFile');
    };

    const handlePagination = (current: number) => {
        setRubbishMedia({
            postQuery: {
                ...rubbishMedia.postQuery,
                paged: current,
                isQueryUpdate: true,
            }
        });
    };

    useEffect(() => {
        const pageFromUrl = parseInt(pageParam || '1', 10);
        if (pageFromUrl !== (rubbishMedia.postQuery.paged || 1)) {
            handlePagination(pageFromUrl);
        }
    }, [pageParam]);

    const rubbishColumns = RubbishFileColumns();

    useEffect(() => {
        getTheRubbishFile();
    }, [rubbishMedia.postQuery, saveType]);

    const totalPosts = rubbishMedia.totalPost || 0;
    const postsPerPage = rubbishMedia.postsPerPage || 20;
    const currentPage = rubbishMedia.paged || 1;
    const totalPages = Math.ceil(totalPosts / postsPerPage);
    const posts = rubbishMedia.mediaFile || [];

    return (
        <div className="min-h-screen bg-gray-50">
                <RubbishHeader />
                {rubbishMedia.isLoading ? <Loader /> : (
                    <>
                        <div className="mx-6 mt-6 px-4 py-3 bg-white border border-gray-200 rounded-lg flex items-center">
                            <div className="flex items-center gap-1.5">
                                <label className="text-sm text-gray-500 whitespace-nowrap">Per page:</label>
                                <input
                                    type="number"
                                    className="w-16 px-2! py-1.5! text-sm! text-gray-900! bg-white! border! border-gray-300! rounded-md! shadow-none! focus:outline-none! focus:border-blue-500! focus:ring-2! focus:ring-blue-500/20! focus:shadow-none! hover:border-gray-400!"
                                    value={options.rubbish_per_page as number | string}
                                    onChange={(event) => { localStorage.setItem('mlt_rubbish_per_page', event.target.value); setOptions({ rubbish_per_page: event.target.value }); }}
                                    onBlur={() => setRubbishMedia({ isLoading: true, postQuery: { ...rubbishMedia.postQuery, postsPerPage: parseInt(String(options.rubbish_per_page || 20), 10), paged: 1, isQueryUpdate: true } })}
                                />
                            </div>
                        </div>
                    <div className="my-6 mx-2 rounded-lg overflow-hidden">
                        <DataTable
                            columns={rubbishColumns}
                            data={posts}
                            rowKey={() => (Math.random() + 1).toString(36).substring(7)}
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
                <DirectoryModal />
                <RubbishNotice />
        </div>
    );
}

export default RubbishFile;
