import React, { useState, useCallback, useRef } from "react";
import { useParams } from "react-router-dom";
import { useStore } from "@/js/Utils/store";
import { getExifResults, getExifStatus, exifStripSingle } from "@/js/Utils/Data";
import ProgressBar from "@/js/Component/Common/ProgressBar";
import Pagination from "@/js/Component/Common/Pagination";
import Modal from "@/js/Component/Common/Modal";
import ExifScannerSection from "@/js/Component/ExifData/ExifScannerSection";
import ExifEditModal from "@/js/Component/ExifData/ExifEditModal";

interface ExifImage {
    attachment_id: number;
    title: string;
    url: string;
    has_exif: boolean;
    exif_summary: {
        has_exif: boolean;
        camera: Record<string, string>;
        gps: Record<string, any>;
        other: Record<string, string>;
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

    // --- Pro version ---
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
            <div className="bg-white rounded-t-xl border border-gray-200">
                <div className="flex items-center gap-3 px-5 py-3">
                    {/* Select all */}
                    <label className="flex items-center gap-2 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                            ref={(el) => { if (el) el.indeterminate = selectedIds.size > 0 && !allSelected; }}
                            checked={allSelected}
                            onChange={toggleSelectAll}
                        />
                        <span className="text-sm text-gray-600">
                            {selectedIds.size > 0 ? `${selectedIds.size} selected` : 'Select all'}
                        </span>
                    </label>

                    {/* Divider */}
                    <div className="w-px h-5 bg-gray-200" />

                    {/* Bulk actions */}
                    <select
                        className="h-8 px-2.5 text-sm border border-gray-300 rounded-md bg-white text-gray-700 cursor-pointer focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                        value={bulkAction}
                        onChange={(e) => setBulkAction(e.target.value)}
                    >
                        <option value="" disabled>Bulk Actions</option>
                        <option value="read_exif">Read EXIF Data</option>
                        <option value="add_exif">Add EXIF Data</option>
                        <option value="edit_exif">Edit EXIF Data</option>
                        <option value="delete_exif">Delete EXIF Data</option>
                    </select>
                    <button
                        type="button"
                        className="h-8 px-3.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                        onClick={handleBulkApply}
                        disabled={!bulkAction || selectedIds.size === 0 || isStripping}
                    >
                        Apply
                    </button>

                    {/* Count */}
                    <div className="ml-auto flex items-center gap-2">
                        <span className="text-sm text-gray-500">{totalImages} images</span>
                    </div>
                </div>

                {/* Strip progress */}
                {isStripping && (
                    <div className="px-5 py-3 border-t border-gray-100">
                        <p className="text-sm text-gray-600 mb-2 mt-0!">
                            Deleting EXIF data... {stripProgress.processed} / {stripProgress.total}
                        </p>
                        <ProgressBar percent={stripProgress.total > 0 ? Math.round((stripProgress.processed / stripProgress.total) * 100) : 0} />
                    </div>
                )}
            </div>

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
                        {images.map((image, index) => {
                            const isSelected = selectedIds.has(image.attachment_id);
                            const isExpanded = expandedIds.has(image.attachment_id);
                            const exif = image.exif_summary;
                            const hasGps = exif.gps?.has_location;
                            const hasCamera = Object.keys(exif.camera || {}).length > 0;
                            const hasOther = Object.keys(exif.other || {}).length > 0;
                            const isLast = index === images.length - 1;
                            return (
                                <div key={image.attachment_id} className={!isLast ? 'border-b border-gray-100' : ''}>
                                    {/* Row */}
                                    <div
                                        className={`flex items-center gap-4 px-5 py-3 cursor-pointer transition-colors ${
                                            isSelected ? 'bg-blue-50/50' : 'hover:bg-gray-50/60'
                                        }`}
                                        onClick={() => toggleExpand(image.attachment_id)}
                                    >
                                        {/* Checkbox */}
                                        <div className="shrink-0" onClick={(e) => e.stopPropagation()}>
                                            <input
                                                type="checkbox"
                                                className="w-4 h-4 rounded border-gray-300 text-blue-600 cursor-pointer"
                                                checked={isSelected}
                                                onChange={() => toggleSelect(image.attachment_id)}
                                            />
                                        </div>

                                        {/* Expand arrow */}
                                        <svg
                                            className={`w-4 h-4 shrink-0 text-gray-400 transition-transform duration-200 ${isExpanded ? 'rotate-90' : ''}`}
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        >
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                        </svg>

                                        {/* Thumbnail */}
                                        <div className="shrink-0 w-12 h-12 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center">
                                            {image.url ? (
                                                <img src={image.url} alt={image.title} className="w-full h-full object-cover" />
                                            ) : (
                                                <svg className="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            )}
                                        </div>

                                        {/* Title + badges */}
                                        <div className="flex-1 min-w-0">
                                            <h3 className="text-sm mt-0! mb-1 font-medium text-gray-900 truncate">
                                                {image.title || `Untitled (ID: ${image.attachment_id})`}
                                            </h3>
                                            <div className="flex flex-wrap gap-1.5">
                                                {image.stripped ? (
                                                    <span className="inline-flex items-center px-2 py-0.5 text-[10px] font-medium text-emerald-700 bg-emerald-50 rounded">
                                                        Stripped
                                                    </span>
                                                ) : !image.has_exif ? (
                                                    <span className="inline-flex items-center px-2 py-0.5 text-[10px] font-medium text-gray-500 bg-gray-100 rounded">
                                                        No EXIF
                                                    </span>
                                                ) : (
                                                    <>
                                                        {hasCamera && (
                                                            <span className="inline-flex items-center px-2 py-0.5 text-[10px] font-medium text-blue-700 bg-blue-50 rounded">
                                                                Camera
                                                            </span>
                                                        )}
                                                        {hasGps && (
                                                            <span className="inline-flex items-center px-2 py-0.5 text-[10px] font-medium text-red-700 bg-red-50 rounded">
                                                                GPS
                                                            </span>
                                                        )}
                                                        {hasOther && (
                                                            <span className="inline-flex items-center px-2 py-0.5 text-[10px] font-medium text-amber-700 bg-amber-50 rounded">
                                                                Meta
                                                            </span>
                                                        )}
                                                    </>
                                                )}
                                            </div>
                                        </div>

                                        {/* ID badge */}
                                        <span className="shrink-0 hidden sm:inline-flex items-center px-2 py-0.5 text-[10px] font-medium text-gray-500 bg-gray-100 rounded">
                                            #{image.attachment_id}
                                        </span>

                                        {/* Row actions */}
                                        <div className="shrink-0 flex items-center gap-2" onClick={(e) => e.stopPropagation()}>
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    if (!isPro) {
                                                        setGeneralData({ openProModal: true });
                                                        return;
                                                    }
                                                    setEditIds([image.attachment_id]);
                                                    setShowEditModal(true);
                                                }}
                                                className="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-200 rounded-md hover:bg-gray-50 hover:border-gray-300 cursor-pointer transition-colors"
                                            >
                                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                                Edit
                                            </button>
                                            {image.has_exif && !image.stripped && (
                                                <button
                                                    type="button"
                                                    disabled={isStrippingSingle === image.attachment_id}
                                                    onClick={() => handleStripSingle(image.attachment_id)}
                                                    className="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-red-600 bg-white border border-red-200 rounded-md hover:bg-red-50 hover:border-red-300 disabled:opacity-50 cursor-pointer transition-colors"
                                                >
                                                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                    {isStrippingSingle === image.attachment_id ? 'Deleting...' : 'Delete'}
                                                </button>
                                            )}
                                        </div>
                                    </div>

                                    {/* Expanded EXIF details */}
                                    {isExpanded && (
                                        <div className="px-5 py-4 bg-gray-50/80 border-t border-gray-100">
                                            {(() => {
                                                const hasAny = hasCamera || hasGps || hasOther;
                                                if (!hasAny) {
                                                    return (
                                                        <p className="text-sm text-gray-400 text-center py-3 m-0!">No EXIF data available for this image.</p>
                                                    );
                                                }

                                                return (
                                                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                                                        {hasCamera && (
                                                            <div className="bg-white rounded-lg p-3.5 border border-gray-200">
                                                                <div className="flex items-center gap-1.5 mb-2.5">
                                                                    <svg className="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                    </svg>
                                                                    <h4 className="text-xs font-semibold text-gray-500 uppercase m-0!">Camera</h4>
                                                                </div>
                                                                <dl className="space-y-1.5 text-xs">
                                                                    {exif.camera?.make && <div className="flex justify-between"><dt className="text-gray-400">Make</dt><dd className="font-medium text-gray-700 m-0!">{exif.camera.make}</dd></div>}
                                                                    {exif.camera?.model && <div className="flex justify-between"><dt className="text-gray-400">Model</dt><dd className="font-medium text-gray-700 m-0!">{exif.camera.model}</dd></div>}
                                                                    {exif.other?.iso && <div className="flex justify-between"><dt className="text-gray-400">ISO</dt><dd className="font-medium text-gray-700 m-0!">{exif.other.iso}</dd></div>}
                                                                    {exif.other?.exposure_time && <div className="flex justify-between"><dt className="text-gray-400">Exposure</dt><dd className="font-medium text-gray-700 m-0!">{exif.other.exposure_time}</dd></div>}
                                                                    {exif.other?.focal_length && <div className="flex justify-between"><dt className="text-gray-400">Focal</dt><dd className="font-medium text-gray-700 m-0!">{exif.other.focal_length}</dd></div>}
                                                                    {exif.camera?.software && <div className="flex justify-between"><dt className="text-gray-400">Software</dt><dd className="font-medium text-gray-700 m-0!">{exif.camera.software}</dd></div>}
                                                                </dl>
                                                            </div>
                                                        )}

                                                        {hasGps && (
                                                            <div className="bg-white rounded-lg p-3.5 border border-gray-200">
                                                                <div className="flex items-center gap-1.5 mb-2.5">
                                                                    <svg className="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                    </svg>
                                                                    <h4 className="text-xs font-semibold text-gray-500 uppercase m-0!">GPS</h4>
                                                                </div>
                                                                <dl className="space-y-1.5 text-xs">
                                                                    {exif.gps?.latitude && <div className="flex justify-between"><dt className="text-gray-400">Latitude</dt><dd className="font-medium text-gray-700 m-0!">{exif.gps.latitude}</dd></div>}
                                                                    {exif.gps?.longitude && <div className="flex justify-between"><dt className="text-gray-400">Longitude</dt><dd className="font-medium text-gray-700 m-0!">{exif.gps.longitude}</dd></div>}
                                                                    {exif.gps?.altitude && <div className="flex justify-between"><dt className="text-gray-400">Altitude</dt><dd className="font-medium text-gray-700 m-0!">{exif.gps.altitude}</dd></div>}
                                                                </dl>
                                                            </div>
                                                        )}

                                                        {hasOther && (
                                                            <div className="bg-white rounded-lg p-3.5 border border-gray-200">
                                                                <div className="flex items-center gap-1.5 mb-2.5">
                                                                    <svg className="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                    </svg>
                                                                    <h4 className="text-xs font-semibold text-gray-500 uppercase m-0!">Other</h4>
                                                                </div>
                                                                <dl className="space-y-1.5 text-xs">
                                                                    {exif.other?.date_time_original && <div className="flex justify-between"><dt className="text-gray-400">Date</dt><dd className="font-medium text-gray-700 m-0!">{exif.other.date_time_original}</dd></div>}
                                                                    {exif.other?.image_width && <div className="flex justify-between"><dt className="text-gray-400">Width</dt><dd className="font-medium text-gray-700 m-0!">{exif.other.image_width}</dd></div>}
                                                                    {exif.other?.image_height && <div className="flex justify-between"><dt className="text-gray-400">Height</dt><dd className="font-medium text-gray-700 m-0!">{exif.other.image_height}</dd></div>}
                                                                    {exif.other?.orientation && <div className="flex justify-between"><dt className="text-gray-400">Orientation</dt><dd className="font-medium text-gray-700 m-0!">{exif.other.orientation}</dd></div>}
                                                                    {exif.other?.f_number && <div className="flex justify-between"><dt className="text-gray-400">Aperture</dt><dd className="font-medium text-gray-700 m-0!">{exif.other.f_number}</dd></div>}
                                                                </dl>
                                                            </div>
                                                        )}
                                                    </div>
                                                );
                                            })()}
                                        </div>
                                    )}
                                </div>
                            );
                        })}

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
            <Modal
                isOpen={showStripModal}
                onClose={() => setShowStripModal(false)}
                title={
                    <div className="flex items-center gap-2">
                        <div className="flex items-center justify-center w-8 h-8 rounded-full bg-red-100 shrink-0">
                            <svg className="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                            </svg>
                        </div>
                        <h3 className="text-lg font-semibold text-gray-900 m-0!">Delete EXIF from Selected Images</h3>
                    </div>
                }
                maxWidth="max-w-[520px]"
                closeOnBackdrop={false}
                footer={
                    <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200">
                        <button
                            type="button"
                            className="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors"
                            onClick={() => setShowStripModal(false)}
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            className="px-5 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 cursor-pointer transition-colors"
                            onClick={confirmBulkStrip}
                        >
                            Yes, Delete EXIF from {selectedIds.size} Image{selectedIds.size !== 1 ? 's' : ''}
                        </button>
                    </div>
                }
            >
                <div className="px-6 py-5 space-y-4">
                    <p className="text-sm text-gray-700 mt-0!">
                        You are about to delete EXIF metadata from <strong>{selectedIds.size} image{selectedIds.size !== 1 ? 's' : ''}</strong>.
                    </p>
                    <div className="bg-amber-50 border border-amber-200 rounded-md px-4 py-3">
                        <p className="text-sm font-semibold text-amber-800 m-0! mb-1">This action cannot be undone.</p>
                        <p className="text-xs text-amber-700 m-0!">EXIF data will be permanently removed from the original image files on your server.</p>
                    </div>
                </div>
            </Modal>

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
