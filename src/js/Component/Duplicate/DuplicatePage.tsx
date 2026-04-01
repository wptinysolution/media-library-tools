import { useEffect, useState, useCallback } from "react";
import { useParams } from "react-router-dom";
import { useStore } from "@/js/Utils/store";
import type { DuplicateGroup } from "@/js/Utils/store";
import { duplicateScanBatch, getDuplicateResults, getDuplicateStatus, clearDuplicateScan, mergeDuplicates } from "@/js/Utils/Data";
import DuplicateHeader from "./DuplicateHeader";
import ProgressBar from "@/js/Component/Common/ProgressBar";
import Pagination from "@/js/Component/Common/Pagination";
import Modal from "@/js/Component/Common/Modal";
import ProLabel from "@/js/Component/ProLabel";

function formatBytes(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

export default function DuplicatePage() {
    const { duplicateData, setDuplicateData, setGeneralData } = useStore();
    const { page: pageParam } = useParams<{ page?: string }>();
    const [mergeGroup, setMergeGroup] = useState<DuplicateGroup | null>(null);
    const [keepId, setKeepId] = useState<number>(0);
    const [merging, setMerging] = useState(false);

    const loadStatus = useCallback(async () => {
        const status = await getDuplicateStatus() as { total_attachments: number; scanned: number; duplicate_groups: number; potential_savings: number };
        setDuplicateData({
            totalAttachments: status.total_attachments,
            scanned: status.scanned,
            totalGroups: status.duplicate_groups,
            potentialSavings: status.potential_savings,
        });
    }, [setDuplicateData]);

    const loadResults = useCallback(async (page = 1) => {
        setDuplicateData({ isLoading: true });
        const result = await getDuplicateResults({ paged: page, postsPerPage: duplicateData.postsPerPage }) as {
            groups: DuplicateGroup[];
            totalGroups: number;
            paged: number;
            postsPerPage: number;
        };
        setDuplicateData({
            isLoading: false,
            groups: result.groups,
            totalGroups: result.totalGroups,
            paged: result.paged,
            postsPerPage: result.postsPerPage,
        });
    }, [duplicateData.postsPerPage, setDuplicateData]);

    const startScan = async () => {
        setDuplicateData({ isScanning: true, scanProgress: { processed: 0, total: 0 } });
        let offset = 0;
        let complete = false;

        while (!complete) {
            const result = await duplicateScanBatch({ offset, batch_size: 50 }) as { data: { processed: number; total: number; complete: boolean } };
            const data = result.data;
            offset = data.processed;
            complete = data.complete;
            setDuplicateData({ scanProgress: { processed: data.processed, total: data.total } });
        }

        setDuplicateData({ isScanning: false });
        await loadStatus();
        await loadResults(1);
    };

    const handleClear = async () => {
        await clearDuplicateScan();
        setDuplicateData({
            groups: [],
            totalGroups: 0,
            potentialSavings: 0,
            scanned: 0,
            paged: 1,
        });
    };

    const handleMerge = async () => {
        if (!mergeGroup || !keepId) return;
        setMerging(true);
        const deleteIds = mergeGroup.items
            .filter(item => item.attachment_id !== keepId)
            .map(item => item.attachment_id);

        await mergeDuplicates({ keep_id: keepId, delete_ids: deleteIds });
        setMerging(false);
        setMergeGroup(null);
        setKeepId(0);
        await loadStatus();
        await loadResults(duplicateData.paged);
    };

    const openMergeModal = (group: DuplicateGroup) => {
        if (!tsmltParams.hasExtended) {
            setGeneralData({ openProModal: true });
            return;
        }
        setMergeGroup(group);
        setKeepId(group.items[0]?.attachment_id || 0);
    };

    useEffect(() => {
        loadStatus();
        loadResults(1);
    }, []);

    useEffect(() => {
        const pageFromUrl = parseInt(pageParam || '1', 10);
        if (pageFromUrl !== duplicateData.paged) {
            loadResults(pageFromUrl);
        }
    }, [pageParam]);

    const handlePagination = (page: number) => {
        loadResults(page);
    };

    const scanPercent = duplicateData.scanProgress.total > 0
        ? Math.round((duplicateData.scanProgress.processed / duplicateData.scanProgress.total) * 100)
        : 0;

    const totalPages = Math.ceil(duplicateData.totalGroups / duplicateData.postsPerPage);

    const duplicateDataGroups = duplicateData.groups ? duplicateData.groups : [];

    return (
        <div className="mx-auto px-6 py-8 min-h-screen bg-gray-50">
            <DuplicateHeader />

            {/* Actions bar */}
            <div className="flex items-center gap-3 px-4 py-3 border-b border-gray-200 bg-white">
                <button
                    type="button"
                    className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer transition-colors disabled:opacity-50"
                    onClick={startScan}
                    disabled={duplicateData.isScanning}
                >
                    {duplicateData.isScanning ? 'Scanning...' : (duplicateData.scanned > 0 ? 'Re-scan' : 'Scan for Duplicates')}
                </button>
                {duplicateData.scanned > 0 && !duplicateData.isScanning && (
                    <button
                        type="button"
                        className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors"
                        onClick={handleClear}
                    >
                        Clear Results
                    </button>
                )}
            </div>

            {/* Scan progress */}
            {duplicateData.isScanning && (
                <div className="px-4 py-4 bg-white border-b border-gray-200">
                    <p className="text-sm text-gray-600 mb-2 mt-0!">
                        Scanning attachments... {duplicateData.scanProgress.processed} / {duplicateData.scanProgress.total}
                    </p>
                    <ProgressBar percent={scanPercent} />
                </div>
            )}

            {/* Results */}
            <div className="p-4">
                {duplicateData.isLoading ? (
                    <div className="text-center py-12 text-gray-500">Loading...</div>
                ) : duplicateDataGroups.length === 0 ? (
                    <div className="text-center py-12">
                        <svg className="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p className="text-gray-500 text-sm">
                            {duplicateData.scanned > 0
                                ? 'No duplicate files found. Your media library is clean!'
                                : 'Click "Scan for Duplicates" to check your media library for duplicate files.'}
                        </p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        { duplicateDataGroups.map((group) => (
                            <div key={group.file_hash} className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                                {/* Group header */}
                                <div className="flex items-center justify-between px-4 py-3 bg-gray-50 border-b border-gray-200">
                                    <div className="flex items-center gap-3">
                                        <span className="inline-flex items-center px-2.5 py-1 bg-red-50 text-red-700 rounded-full text-xs font-medium">
                                            {group.item_count} copies
                                        </span>
                                        <span className="text-xs text-gray-500">
                                            {formatBytes(group.file_size)} each &middot; {formatBytes(group.file_size * (group.item_count - 1))} wasted
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer transition-colors"
                                        onClick={() => openMergeModal(group)}
                                    >
                                        Merge
                                        {!tsmltParams.hasExtended && <ProLabel /> }
                                    </button>
                                </div>

                                {/* Group items */}
                                <div className="divide-y divide-gray-100">
                                    {group.items.map((item) => (
                                        <div key={item.attachment_id} className="flex items-center gap-4 px-4 py-3">
                                            <div className="shrink-0 w-12 h-12 rounded overflow-hidden bg-gray-100">
                                                {item.thumbnail ? (
                                                    <img src={item.thumbnail} alt="" className="w-full h-full object-cover" />
                                                ) : (
                                                    <div className="w-full h-full flex items-center justify-center text-gray-400">
                                                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </div>
                                                )}
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center gap-2">
                                                    <span className="text-xs text-gray-400">#{item.attachment_id}</span>
                                                    <span className="text-sm font-medium text-gray-900 truncate">{item.title || '(untitled)'}</span>
                                                </div>
                                                <p className="text-xs text-gray-500 truncate mt-0.5 mb-0!">{item.file_path}</p>
                                            </div>
                                            <div className="shrink-0 text-right">
                                                <span className="text-xs text-gray-500">{formatBytes(item.file_size)}</span>
                                                {item.used_in && item.used_in.length > 0 && (
                                                    <div className="mt-0.5">
                                                        <span className="text-[10px] text-gray-400 uppercase">Used in:</span>
                                                        {item.used_in.map((post, i) => (
                                                            <p key={i} className="text-xs text-gray-400 mt-0 mb-0!">
                                                                <a href={post.permalink} target="_blank" className="hover:text-blue-600">
                                                                    {post.title}
                                                                </a>
                                                            </p>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}

                        <Pagination
                            currentPage={duplicateData.paged}
                            totalPages={totalPages}
                            totalPosts={duplicateData.totalGroups}
                            postsPerPage={duplicateData.postsPerPage}
                            onPageChange={handlePagination}
                        />
                    </div>
                )}
            </div>

            {/* Merge Modal */}
            <Modal
                isOpen={!!mergeGroup}
                onClose={() => { setMergeGroup(null); setKeepId(0); }}
                title="Merge Duplicates"
                maxWidth="max-w-[550px]"
                footer={
                    <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200">
                        <button
                            type="button"
                            className="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors"
                            onClick={() => { setMergeGroup(null); setKeepId(0); }}
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            className="px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer transition-colors disabled:opacity-50"
                            onClick={handleMerge}
                            disabled={merging || !keepId}
                        >
                            {merging ? 'Merging...' : 'Merge & Delete Others'}
                        </button>
                    </div>
                }
            >
                <div className="px-6 py-5">
                    <p className="text-sm text-gray-700 mt-0! mb-4">
                        Select which copy to keep. The others will be deleted and all references in your content will be updated to point to the kept file.
                    </p>
                    <div className="space-y-2">
                        {mergeGroup?.items.map((item) => (
                            <label
                                key={item.attachment_id}
                                className={`flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors ${
                                    keepId === item.attachment_id
                                        ? 'border-blue-500 bg-blue-50'
                                        : 'border-gray-200 hover:bg-gray-50'
                                }`}
                            >
                                <input
                                    type="radio"
                                    name="keep_duplicate"
                                    className="w-4 h-4 text-blue-600"
                                    checked={keepId === item.attachment_id}
                                    onChange={() => setKeepId(item.attachment_id)}
                                />
                                <div className="shrink-0 w-10 h-10 rounded overflow-hidden bg-gray-100">
                                    {item.thumbnail && <img src={item.thumbnail} alt="" className="w-full h-full object-cover" />}
                                </div>
                                <div className="flex-1 min-w-0">
                                    <div className="text-sm font-medium text-gray-900 truncate">
                                        #{item.attachment_id} &middot; {item.title || '(untitled)'}
                                    </div>
                                    <div className="text-xs text-gray-500 truncate">{item.file_path}</div>
                                </div>
                                {keepId === item.attachment_id && (
                                    <span className="shrink-0 px-2 py-0.5 text-xs font-medium text-green-700 bg-green-100 rounded-full">Keep</span>
                                )}
                            </label>
                        ))}
                    </div>
                </div>
            </Modal>
        </div>
    );
}
