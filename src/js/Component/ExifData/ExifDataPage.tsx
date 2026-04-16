import React, { useState, useCallback, useRef } from "react";
import { useParams } from "react-router-dom";
import { useStore } from "@/js/Utils/store";
import { getExifResults, getExifStatus, exifStripSingle } from "@/js/Utils/Data";
import Pagination from "@/js/Component/Common/Pagination";
import ExifScannerSection from "@/js/Component/ExifData/ExifScannerSection";
import ExifEditModal from "@/js/Component/ExifData/ExifEditModal";
import ExifToolbar from "@/js/Component/ExifData/ExifToolbar";
import ExifImageRow from "@/js/Component/ExifData/ExifImageRow";
import ExifStripModal from "@/js/Component/ExifData/ExifStripModal";

interface ExifImage {
    attachment_id: number;
    title: string;
    url: string;
    has_exif: boolean;
    exif_summary: {
        has_exif: boolean;
        camera?: Record<string, string>;
        gps?: Record<string, any>;
        other?: Record<string, string>;
    };
    stripped: boolean;
    stripped_info: any;
}

export default function ExifDataPage() {
    const { page: pageParam } = useParams<{ page?: string }>();
    const { setGeneralData } = useStore();
    const [images, setImages] = useState<ExifImage[]>([]);
    const [totalImages, setTotalImages] = useState(0);
    const [currentPage, setCurrentPage] = useState(1);
    const [isLoading, setIsLoading] = useState(false);
    const [isStripping, setIsStripping] = useState(false);
    const [stripProgress, setStripProgress] = useState({ processed: 0, total: 0, stripped: 0, failed: 0 });
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
    const [showStripModal, setShowStripModal] = useState(false);
    const [isStrippingSingle, setIsStrippingSingle] = useState<number | null>(null);
    const [expandedIds, setExpandedIds] = useState<Set<number>>(new Set());
    const [bulkAction, setBulkAction] = useState("");
    const [showEditModal, setShowEditModal] = useState(false);
    const [editIds, setEditIds] = useState<number[]>([]);
    const isMounted = useRef(false);

    const limit = 20;
    const isPro = typeof tsmltParams !== 'undefined' && tsmltParams.hasExtended;

    const loadStatus = useCallback(async () => {
        try {
            const status = await getExifStatus() as any;
            setStripProgress({
                processed: status.stripped || 0,
                total: status.total || 0,
                stripped: status.stripped || 0,
                failed: 0,
            });
        } catch (error) {
            console.error('Error loading status:', error);
        }
    }, []);

    const loadResults = useCallback(async (page = 1) => {
        setIsLoading(true);
        setSelectedIds(new Set());
        try {
            const result = await getExifResults({
                limit,
                offset: (page - 1) * limit,
            }) as any;
            setImages(result.images || []);
            setTotalImages(result.total || 0);
            setCurrentPage(page);
        } catch (error) {
            console.error('Error loading results:', error);
        } finally {
            setIsLoading(false);
        }
    }, []);

    const handleStripSingle = async (attachmentId: number) => {
        if (!isPro) {
            setGeneralData({ openProModal: true });
            return;
        }
        setIsStrippingSingle(attachmentId);
        try {
            await exifStripSingle({ attachment_id: attachmentId });
            await loadResults(currentPage);
        } catch (error) {
            console.error('Error stripping EXIF:', error);
        } finally {
            setIsStrippingSingle(null);
        }
    };

    const toggleSelect = (id: number) => {
        setSelectedIds(prev => {
            const next = new Set(prev);
            next.has(id) ? next.delete(id) : next.add(id);
            return next;
        });
    };

    const toggleSelectAll = () => {
        if (selectedIds.size === images.length) {
            setSelectedIds(new Set());
        } else {
            setSelectedIds(new Set(images.map(img => img.attachment_id)));
        }
    };

    const toggleExpand = (id: number) => {
        setExpandedIds(prev => {
            const next = new Set(prev);
            next.has(id) ? next.delete(id) : next.add(id);
            return next;
        });
    };

    const confirmBulkStrip = async () => {
        setShowStripModal(false);
        setIsStripping(true);
        try {
            for (const id of selectedIds) {
                try {
                    await exifStripSingle({ attachment_id: id });
                } catch { /* skip */ }
            }
            setSelectedIds(new Set());
            await loadResults(currentPage);
            await loadStatus();
        } catch (error) {
            console.error('Error during bulk strip:', error);
        } finally {
            setIsStripping(false);
        }
    };

    const handleBulkApply = () => {
        if (!bulkAction || selectedIds.size === 0) return;
        if (!isPro) {
            setGeneralData({ openProModal: true });
            return;
        }
        switch (bulkAction) {
            case 'read_exif':
                setExpandedIds(new Set(selectedIds));
                break;
            case 'delete_exif':
                setShowStripModal(true);
                break;
            case 'add_exif':
            case 'edit_exif':
                setEditIds(Array.from(selectedIds));
                setShowEditModal(true);
                break;
        }
    };

    React.useEffect(() => {
        loadStatus().then(() => {
            const pageFromUrl = parseInt(pageParam || '1', 10);
            loadResults(pageFromUrl).finally(() => {
                isMounted.current = true;
            });
        });
    }, []);

    React.useEffect(() => {
        if (!isMounted.current || !isPro) return;
        loadResults(currentPage);
    }, [currentPage, isPro]);

    const totalPages = Math.ceil(totalImages / limit);
    const allSelected = images.length > 0 && images.every(img => selectedIds.has(img.attachment_id));

    return (
        <div className="mx-auto px-6 py-8 min-h-screen bg-gray-50">
            {/* Header */}
            <div className="mb-8">
                <div className="flex items-center gap-3 mb-2">
                    <h1 className="text-2xl font-semibold text-gray-900 m-0!">EXIF Data</h1>
                </div>
                <p className="text-sm text-gray-500">View, edit, and remove EXIF metadata (GPS, camera info, author) from your images.</p>
            </div>

            {/* Scanner */}
            <div className="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                <ExifScannerSection />
            </div>

            {/* Toolbar */}
            <ExifToolbar
                selectedCount={selectedIds.size}
                totalImages={totalImages}
                allSelected={allSelected}
                isIndeterminate={selectedIds.size > 0 && !allSelected}
                isStripping={isStripping}
                stripProgress={stripProgress}
                bulkAction={bulkAction}
                onToggleSelectAll={toggleSelectAll}
                onBulkActionChange={setBulkAction}
                onBulkApply={handleBulkApply}
            />

            {/* Table */}
            <div className="bg-white rounded-b-xl border border-t-0 border-gray-200 overflow-hidden">
                {isLoading ? (
                    <div className="flex items-center justify-center py-20">
                        <div className="flex flex-col items-center gap-3 text-gray-400">
                            <svg className="w-7 h-7 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            <span className="text-sm">Loading images...</span>
                        </div>
                    </div>
                ) : images.length === 0 ? (
                    <div className="text-center py-16">
                        <svg className="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p className="text-gray-500 text-sm">No images found in your media library.</p>
                    </div>
                ) : (
                    <>
                        {images.map((image, index) => (
                            <ExifImageRow
                                key={image.attachment_id}
                                image={image}
                                isSelected={selectedIds.has(image.attachment_id)}
                                isExpanded={expandedIds.has(image.attachment_id)}
                                isStripping={isStrippingSingle === image.attachment_id}
                                isLast={index === images.length - 1}
                                onToggleSelect={() => toggleSelect(image.attachment_id)}
                                onToggleExpand={() => toggleExpand(image.attachment_id)}
                                onEdit={() => {
                                    if (!isPro) {
                                        setGeneralData({ openProModal: true });
                                        return;
                                    }
                                    setEditIds([image.attachment_id]);
                                    setShowEditModal(true);
                                }}
                                onStrip={() => handleStripSingle(image.attachment_id)}
                            />
                        ))}

                        {/* Pagination */}
                        {totalPages > 1 && (
                            <div className="px-5 py-3 border-t border-gray-100">
                                <Pagination
                                    currentPage={currentPage}
                                    totalPages={totalPages}
                                    totalPosts={totalImages}
                                    postsPerPage={limit}
                                    onPageChange={setCurrentPage}
                                />
                            </div>
                        )}
                    </>
                )}
            </div>

            {/* Bulk delete confirmation */}
            <ExifStripModal
                isOpen={showStripModal}
                selectedCount={selectedIds.size}
                onClose={() => setShowStripModal(false)}
                onConfirm={confirmBulkStrip}
            />

            {/* Edit EXIF Modal */}
            <ExifEditModal
                isOpen={showEditModal}
                onClose={() => setShowEditModal(false)}
                attachmentIds={editIds}
                onSaved={() => loadResults(currentPage)}
            />
        </div>
    );
}
