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
    const { saveType, rubbishMedia, setRubbishMedia, setBulkRubbishData } = useStore();
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
                )}
                <DirectoryModal />
                <RubbishNotice />
        </div>
    );
}

export default RubbishFile;
