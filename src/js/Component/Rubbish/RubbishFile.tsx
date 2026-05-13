import { useEffect, useState } from "react";
import { useParams } from 'react-router-dom';
import { useStore } from "@/js/Utils/store";
import type { RubbishMediaFile } from "@/js/Utils/store";
import RubbishHeader from "@/js/Component/Rubbish/RubbishHeader";
import { getRubbishFile } from "@/js/Utils/Data";
import DirectoryModal from "@/js/Component/Rubbish/DirectoryModal";
import RubbishNotice from "@/js/Component/Rubbish/RubbishNotice";
import EmptyDirectories from "@/js/Component/Rubbish/EmptyDirectories";
import Pagination from "@/js/Component/Common/Pagination";
import Modal from "@/js/Component/Common/Modal";
import MediaThumbnail from "@/js/Component/Common/MediaThumbnail";
import { rubbishSingleDeleteAction, rubbishSingleIgnoreAction, rubbishSingleShowAction, rubbishSingleRestoreAction } from "@/js/Utils/Data";

type ConfirmState = { record: RubbishMediaFile; action: string } | null;

const actionConfig: Record<string, { title: string; message: string; confirmLabel: string; confirmClass: string }> = {
    delete: {
        title: 'Delete Unnecessary File',
        message: 'Are you sure you want to permanently delete this file? This cannot be undone.',
        confirmLabel: 'Delete',
        confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
    },
    restore: {
        title: 'Restore to Library',
        message: 'Are you sure you want to restore this file to the WordPress media library?',
        confirmLabel: 'Restore',
        confirmClass: 'bg-green-600 hover:bg-green-700 text-white',
    },
    ignore: {
        title: 'Ignore Important File',
        message: 'Are you sure you want to mark this as an important file? It will be excluded from the rubbish file list.',
        confirmLabel: 'Ignore',
        confirmClass: 'bg-blue-600 hover:bg-blue-700 text-white',
    },
    show: {
        title: 'Mark As Unnecessary File',
        message: 'Are you sure you want to mark this file as unnecessary?',
        confirmLabel: 'Confirm',
        confirmClass: 'bg-gray-600 hover:bg-gray-700 text-white',
    },
};

function RubbishFile() {
    const { saveType, rubbishMedia, setRubbishMedia, bulkRubbishData, setBulkRubbishData, setGeneralData } = useStore();
    const { page: pageParam } = useParams<{ page?: string }>();

    const [deleteCurrentItem, setDeleteCurrentItem] = useState<string | number | null>(null);
    const [ignoreCurrentItem, setIgnoreCurrentItem] = useState<string | number | null>(null);
    const [restoreCurrentItem, setRestoreCurrentItem] = useState<string | number | null>(null);
    const [confirmState, setConfirmState] = useState<ConfirmState>(null);

    const getTheRubbishFile = async () => {
        setRubbishMedia({ isLoading: true });
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

    useEffect(() => {
        getTheRubbishFile();
    }, [rubbishMedia.postQuery, saveType]);

    const onRubbishBulkCheck = (event: React.ChangeEvent<HTMLInputElement>) => {
        const postsId = event.target.checked ? posts.map(item => item.id) : [];
        const files = event.target.checked
            ? posts.map(item => ({ id: item.id, path: item.file_path }))
            : [];
        setBulkRubbishData({
            bulkChecked: !!postsId.length,
            ids: postsId,
            files,
            progressTotal: files.length,
        });
    };

    const onCheckboxChange = (event: React.ChangeEvent<HTMLInputElement>, record: RubbishMediaFile) => {
        const value = event.target.value;
        const changeData = event.target.checked
            ? [...bulkRubbishData.ids, value]
            : bulkRubbishData.ids.filter(item => item !== value);
        const changePath = event.target.checked
            ? [...bulkRubbishData.files, { id: record.id, path: record.file_path }]
            : bulkRubbishData.files.filter(item => item.id !== record.id);
        setBulkRubbishData({
            bulkChecked: !!(changeData.length && changeData.length === posts.length),
            ids: changeData,
            files: changePath,
            progressTotal: changeData.length,
        });
    };

    const onRubbishSingleAction = async (data: RubbishMediaFile, action: string) => {
        if (tsmltParams.hasExtended) {
            let response: { status: number | string; data: { updated: boolean } } | undefined;
            if ('restore' === action) {
                setRestoreCurrentItem(data.id);
                try {
                    response = await rubbishSingleRestoreAction(data) as typeof response;
                    if (200 === parseInt(String(response?.status)) && response?.data.updated) {
                        setRubbishMedia({ mediaFile: rubbishMedia.mediaFile.filter(item => data.id !== item.id) });
                    }
                } finally {
                    setRestoreCurrentItem(null);
                }
                return;
            } else if ('ignore' === action) {
                setIgnoreCurrentItem(data.id);
                response = await rubbishSingleIgnoreAction(data) as typeof response;
            } else if ('delete' === action) {
                setDeleteCurrentItem(data.id);
                response = await rubbishSingleDeleteAction(data) as typeof response;
            } else if ('show' === action) {
                response = await rubbishSingleShowAction(data) as typeof response;
            }
            if (200 === parseInt(String(response?.status))) {
                const mediaFile = response?.data.updated
                    ? rubbishMedia.mediaFile.filter(item => data.id !== item.id)
                    : rubbishMedia.mediaFile;
                setRubbishMedia({ mediaFile });
                setIgnoreCurrentItem(null);
                setDeleteCurrentItem(null);
            }
            return;
        }
        setGeneralData({ openProModal: true });
    };

    const totalPosts = rubbishMedia.totalPost || 0;
    const postsPerPage = rubbishMedia.postsPerPage || 20;
    const currentPage = rubbishMedia.paged || 1;
    const totalPages = Math.ceil(totalPosts / postsPerPage);
    const posts = rubbishMedia.mediaFile || [];

    const config = confirmState ? actionConfig[confirmState.action] : null;

    return (
        <div className="min-h-screen bg-gray-50">
            <RubbishHeader />

            <div className="p-3">
                {rubbishMedia.isLoading ? (
                    <div className="space-y-3">
                        {Array.from({ length: 5 }).map((_, i) => (
                            <div key={i} className="bg-white rounded-lg border border-gray-200 p-4 animate-pulse">
                                <div className="flex items-center gap-4">
                                    <div className="w-4 h-4 bg-gray-200 rounded" />
                                    <div className="w-12 h-12 bg-gray-200 rounded-lg" />
                                    <div className="flex-1 space-y-2">
                                        <div className="h-4 bg-gray-200 rounded w-2/3" />
                                    </div>
                                    <div className="h-8 bg-gray-200 rounded w-24" />
                                </div>
                            </div>
                        ))}
                    </div>
                ) : posts.length === 0 ? (
                    <div className="text-center py-16 bg-white rounded-lg border border-gray-200">
                        <svg className="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                        <p className="text-gray-500 text-sm">No rubbish files found. Your uploads directory is clean!</p>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {/* Select all */}
                        <div className="flex items-center gap-3 px-4 py-2 bg-white rounded-lg border border-gray-200">
                            <input
                                type="checkbox"
                                className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                ref={(el) => { if (el) el.indeterminate = bulkRubbishData.ids.length > 0 && !bulkRubbishData.bulkChecked; }}
                                checked={bulkRubbishData.bulkChecked}
                                onChange={onRubbishBulkCheck}
                            />
                            <span className="text-sm text-gray-600">
                                {bulkRubbishData.ids.length > 0
                                    ? `${bulkRubbishData.ids.length} selected`
                                    : 'Select all'}
                            </span>
                            <div className="flex items-center gap-2">
                                <svg className="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                                </svg>
                                <span className="text-sm font-medium text-gray-800">Rubbish File</span>
                            </div>
                        </div>

                        {posts.map((record) => {
                            const fileUrl = `${tsmltParams.uploadUrl}/${record.file_path}`;
                            const isIgnoreMode = 'ignore' === rubbishMedia.postQuery.fileStatus;

                            return (
                                <div key={record.id} className="bg-white rounded-lg border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
                                    {/* Main row */}
                                    <div className="flex items-center gap-4 p-4">
                                        {/* Checkbox */}
                                        <input
                                            type="checkbox"
                                            className="w-4 h-4 shrink-0 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                            checked={bulkRubbishData.ids.includes(record.id as string | number)}
                                            name="item_id"
                                            value={String(record.id)}
                                            onChange={(event) => onCheckboxChange(event, record)}
                                        />

                                        {/* Thumbnail */}
                                        <div className="shrink-0 w-12 h-12 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center">
                                            <MediaThumbnail
                                                url={fileUrl}
                                                fileName={record.file_path}
                                                alt=""
                                                className="w-full h-full object-cover"
                                                iconClassName="w-6 h-6 text-gray-400"
                                            />
                                        </div>

                                        {/* File URL */}
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm text-gray-700 break-all m-0!">{fileUrl}</p>
                                        </div>
                                    </div>

                                    {/* Actions bar at bottom */}
                                    <div className="flex items-center pl-25 gap-2 px-4 py-2.5 border-t border-gray-100 bg-gray-50">
                                        {isIgnoreMode ? (
                                            <button
                                                className="px-3 py-1.5 text-xs font-medium border border-gray-300 rounded-md hover:bg-gray-100 cursor-pointer transition-colors disabled:opacity-50 bg-white"
                                                onClick={() => setConfirmState({ record, action: 'show' })}
                                                disabled={record.id === deleteCurrentItem}
                                            >
                                                {record.id === deleteCurrentItem ? 'Processing...' : 'Mark As Unnecessary'}
                                            </button>
                                        ) : (
                                            <>
                                                <button
                                                    className="px-3 py-1.5 text-xs font-medium text-green-600 border border-green-300 rounded-md hover:bg-green-50 cursor-pointer transition-colors disabled:opacity-50 bg-white"
                                                    onClick={() => setConfirmState({ record, action: 'restore' })}
                                                    disabled={record.id === restoreCurrentItem}
                                                    title="Import this file into the WordPress media library"
                                                >
                                                    {record.id === restoreCurrentItem ? 'Restoring...' : 'Restore'}
                                                </button>
                                                <button
                                                    className="px-3 py-1.5 text-xs font-medium text-red-600 border border-red-300 rounded-md hover:bg-red-50 cursor-pointer transition-colors disabled:opacity-50 bg-white"
                                                    onClick={() => setConfirmState({ record, action: 'delete' })}
                                                    disabled={record.id === deleteCurrentItem}
                                                >
                                                    {record.id === deleteCurrentItem ? 'Deleting...' : 'Delete'}
                                                </button>
                                                <button
                                                    className="px-3 py-1.5 text-xs font-medium border border-gray-300 rounded-md hover:bg-gray-100 cursor-pointer transition-colors disabled:opacity-50 bg-white"
                                                    onClick={() => setConfirmState({ record, action: 'ignore' })}
                                                    disabled={record.id === ignoreCurrentItem}
                                                >
                                                    {record.id === ignoreCurrentItem ? 'Processing...' : 'Ignore'}
                                                </button>
                                            </>
                                        )}
                                    </div>
                                </div>
                            );
                        })}

                        <Pagination
                            currentPage={currentPage}
                            totalPages={totalPages}
                            totalPosts={totalPosts}
                            postsPerPage={postsPerPage}
                            onPageChange={handlePagination}
                        />
                    </div>
                )}
            </div>

            {/* Rubbish Directories */}
            <div className="p-3 pt-5">
                <details className="group bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <summary className="flex items-center justify-between px-4 py-3 cursor-pointer select-none hover:bg-gray-50 transition-colors list-none">
                        <div className="flex items-center gap-2">
                            <svg className="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                            </svg>
                            <span className="text-sm font-medium text-gray-800">Rubbish Directories</span>
                            <span className="text-xs text-gray-400">— empty folders that can be safely removed</span>
                        </div>
                        <svg className="w-4 h-4 text-gray-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                        </svg>
                    </summary>
                    <div className="px-4 py-3 border-t border-gray-100">
                        <EmptyDirectories />
                    </div>
                </details>
            </div>

            <DirectoryModal />
            <RubbishNotice />

            {/* Confirm Modal */}
            <Modal
                isOpen={!!confirmState}
                onClose={() => setConfirmState(null)}
                title={config?.title ?? ''}
                maxWidth="max-w-[480px]"
                footer={
                    <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200">
                        <button
                            type="button"
                            className="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors"
                            onClick={() => setConfirmState(null)}
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            className={`px-5 py-2 text-sm font-medium rounded-md cursor-pointer transition-colors ${config?.confirmClass ?? ''}`}
                            onClick={() => {
                                if (!confirmState) return;
                                onRubbishSingleAction(confirmState.record, confirmState.action);
                                setConfirmState(null);
                            }}
                        >
                            {config?.confirmLabel}
                        </button>
                    </div>
                }
            >
                <div className="px-6 py-5">
                    <p className="text-sm mt-0! text-gray-700 mb-3">{config?.message}</p>
                    {confirmState && (
                        <p className="text-xs mb-0! text-gray-500 bg-gray-50 border border-gray-200 rounded px-3 py-2 break-all">
                            {`${tsmltParams.uploadUrl}/${confirmState.record.file_path}`}
                        </p>
                    )}
                </div>
            </Modal>
        </div>
    );
}

export default RubbishFile;
