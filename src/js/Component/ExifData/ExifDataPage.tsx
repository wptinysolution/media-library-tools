import React, { useState, useCallback, useRef } from "react";
import { useParams } from "react-router-dom";
import { useStore } from "@/js/Utils/store";
import { getExifResults, getExifStatus, exifStripSingle } from "@/js/Utils/Data";
import ProgressBar from "@/js/Component/Common/ProgressBar";
import Pagination from "@/js/Component/Common/Pagination";
import Modal from "@/js/Component/Common/Modal";
import ProLabel from "@/js/Component/Badges/ProLabel";
import ExifScannerSection from "@/js/Component/ExifData/ExifScannerSection";
import ExifEditModal from "@/js/Component/ExifData/ExifEditModal";

interface ExifImage {
    attachment_id: number;
    title: string;
    url: string;
    has_exif: boolean;
    exif_summary: {
        has_exif: boolean;
        camera: Record<string, string>;   // make, model, iso, exposure, focal_length
        gps: Record<string, any>;         // has_location, lat_ref, lon_ref, altitude
        date: Record<string, string>;     // original
        other: Record<string, string>;    // software, artist, copyright, user_comment
        strippable: string[];
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
        if (!isPro) return;
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

    const confirmBulkStrip = async () => {
        setShowStripModal(false);
        setIsStripping(true);

        let totalStripped = 0;
        let totalFailed = 0;

        try {
            for (const id of selectedIds) {
                try {
                    await exifStripSingle({ attachment_id: id });
                    totalStripped++;
                } catch {
                    totalFailed++;
                }
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
        if (!bulkAction) return;
        if (selectedIds.size === 0) return;

        if (!isPro) {
            setGeneralData({ openProModal: true });
            return;
        }

        switch (bulkAction) {
            case 'read_exif':
                // Expand all selected images to show EXIF details
                setExpandedIds(new Set(selectedIds));
                break;
            case 'delete_exif':
                setShowStripModal(true);
                break;
            case 'edit_exif':
                setEditIds(Array.from(selectedIds));
                setShowEditModal(true);
                break;
        }
    };

    // Load status + results on first mount (only in pro).
    React.useEffect(() => {
        loadStatus().then(() => {
            const pageFromUrl = parseInt(pageParam || '1', 10);
            loadResults(pageFromUrl).finally(() => {
                isMounted.current = true;
            });
        });
    }, []);

    // Load results when currentPage changes (pagination click).
    React.useEffect(() => {
        if (!isMounted.current) return;
        if (!isPro) return;
        loadResults(currentPage);
    }, [currentPage, isPro]);

    const totalPages = Math.ceil(totalImages / limit);
    const allSelected = images.length > 0 && images.every(img => selectedIds.has(img.attachment_id));

    // Show free version with scanner and upgrade option
    if (!isPro) {
        return (
            <div className="mx-auto px-6 py-8 min-h-screen bg-gray-50">
                {/* Header */}
                <div className="mb-8">
                    <div className="flex items-center gap-3 mb-2">
                        <h1 className="text-2xl font-semibold text-gray-900 m-0!">EXIF Data</h1>
                        {!isPro && <ProLabel />}
                    </div>
                    <p className="text-sm text-gray-500">Analyze and manage EXIF metadata in your media library. Upgrade to Pro to remove EXIF data from images.</p>
                </div>

                {/* Scanner Section */}
                <div className="bg-white rounded-xl border border-gray-200 p-8 mb-8">
                    <ExifScannerSection />
                </div>

                {/* Pro Feature Notice */}
                <div className="bg-linear-to-r from-purple-50 to-indigo-50 border border-purple-200 rounded-xl p-8 text-center">
                    <div className="w-16 h-16 mx-auto mb-4 bg-purple-100 rounded-full flex items-center justify-center">
                        <svg className="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <h2 className="text-xl font-semibold text-gray-900 mb-2">Unlock EXIF Removal</h2>
                    <p className="text-gray-600 mb-6 max-w-md mx-auto">
                        Upgrade to Pro to remove EXIF metadata from your images and protect privacy.
                    </p>
                    <button
                        type="button"
                        className="px-6 py-3 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 cursor-pointer transition-colors shadow-lg shadow-purple-200"
                        onClick={() => setGeneralData({ openProModal: true })}
                    >
                        Upgrade to Pro
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="mx-auto px-6 py-8 min-h-screen bg-gray-50">
            {/* Header */}
            <div className="mb-8">
                <div className="flex items-center gap-3 mb-2">
                    <h1 className="text-2xl font-semibold text-gray-900 m-0!">EXIF Data</h1>
                    {!isPro && <ProLabel />}
                </div>
                <p className="text-sm text-gray-500">Remove EXIF metadata (GPS, camera info, author) from your images to protect privacy and reduce file size.</p>
            </div>

            {/* Scanner Section - Available for both Free and Pro */}
            <div className="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                <ExifScannerSection />
            </div>

            {/* Actions bar */}
            <div className="flex items-center gap-3 px-8 py-3 bg-white rounded-t-lg border border-b-0 border-gray-200">
                <label className="flex items-center gap-2 cursor-pointer">
                    <input
                        type="checkbox"
                        className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                        ref={(el) => { if (el) el.indeterminate = selectedIds.size > 0 && !allSelected; }}
                        checked={allSelected}
                        onChange={toggleSelectAll}
                    />
                    <span className="text-sm text-gray-700">
                        {selectedIds.size > 0 ? `${selectedIds.size} selected` : 'Select all'}
                    </span>
                </label>

                {/* Bulk Action Dropdown + Apply */}
                <select
                    className="h-9 px-3 text-sm border border-gray-300 rounded-md bg-white text-gray-700 cursor-pointer focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    value={bulkAction}
                    onChange={(e) => setBulkAction(e.target.value)}
                >
                    <option value="" disabled>Bulk Actions</option>
                    <option value="read_exif">Read EXIF Data</option>
                    <option value="delete_exif">Delete EXIF Data</option>
                    <option value="edit_exif">Edit EXIF Data</option>
                </select>
                <button
                    type="button"
                    className="h-9 px-4 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    onClick={handleBulkApply}
                    disabled={!bulkAction || selectedIds.size === 0 || isStripping}
                >
                    Apply
                </button>

                <div className="ml-auto text-sm text-gray-500">
                    {totalImages} images found
                </div>
            </div>

            {/* Strip progress */}
            {isStripping && (
                <div className="px-4 py-4 bg-white border-b border-gray-200">
                    <p className="text-sm text-gray-600 mb-2 mt-0!">
                        Deleting EXIF data... {stripProgress.processed} / {stripProgress.total} processed
                    </p>
                    <ProgressBar percent={stripProgress.total > 0 ? Math.round((stripProgress.processed / stripProgress.total) * 100) : 0} />
                    {stripProgress.stripped > 0 && (
                        <p className="text-xs text-green-600 mt-1">
                            {stripProgress.stripped} images processed successfully
                        </p>
                    )}
                </div>
            )}

            {/* Results */}
            <div className="bg-white rounded-b-lg border border-t-0 border-gray-200 p-4">
                {isLoading ? (
                    <div className="flex items-center justify-center py-16">
                        <div className="flex flex-col items-center gap-3 text-gray-400">
                            <svg className="w-8 h-8 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            <span className="text-sm">Loading…</span>
                        </div>
                    </div>
                ) : images.length === 0 ? (
                    <div className="text-center py-12">
                        <svg className="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p className="text-gray-500 text-sm">
                            No JPEG images found in your media library.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {images.map((image) => {
                            const isSelected = selectedIds.has(image.attachment_id);
                            const exif = image.exif_summary;
                            const hasGps = exif.gps?.has_location;
                            const hasCamera = Object.keys(exif.camera || {}).length > 0;
                            const hasOther = Object.keys(exif.other || {}).length > 0;

                            return (
                                <div
                                    key={image.attachment_id}
                                    className={`bg-white rounded-lg border overflow-hidden hover:shadow-md transition-shadow ${
                                        isSelected ? 'border-blue-400 ring-1 ring-blue-300' : 'border-gray-200'
                                    }`}
                                >
                                    <div 
                                        className="flex items-center gap-4 p-4 cursor-pointer"
                                        onClick={() => setExpandedIds(prev => {
                                            const next = new Set(prev);
                                            next.has(image.attachment_id) ? next.delete(image.attachment_id) : next.add(image.attachment_id);
                                            return next;
                                        })}
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

                                        {/* Thumbnail */}
                                        <div className="shrink-0 w-14 h-14 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center">
                                            {image.url ? (
                                                <img src={image.url} alt={image.title} className="w-full h-full object-cover" />
                                            ) : (
                                                <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            )}
                                        </div>

                                        {/* Info */}
                                        <div className="flex-1 min-w-0">
                                            <h3 className="text-sm mt-0! mb-1.5 font-semibold text-gray-900 truncate">
                                                {image.title || `(ID: ${image.attachment_id})`}
                                            </h3>
                                            <div className="flex flex-wrap gap-1.5">
                                                {hasGps && (
                                                    <span className="inline-flex items-center px-2 py-0.5 text-[10px] font-medium text-red-700 bg-red-50 rounded">
                                                        GPS Location
                                                    </span>
                                                )}
                                                {hasCamera && (
                                                    <span className="inline-flex items-center px-2 py-0.5 text-[10px] font-medium text-blue-700 bg-blue-50 rounded">
                                                        Camera Info
                                                    </span>
                                                )}
                                                {hasOther && (
                                                    <span className="inline-flex items-center px-2 py-0.5 text-[10px] font-medium text-purple-700 bg-purple-50 rounded">
                                                        Other EXIF
                                                    </span>
                                                )}
                                                {image.stripped && (
                                                    <span className="inline-flex items-center px-2 py-0.5 text-[10px] font-medium text-green-700 bg-green-50 rounded">
                                                        EXIF Stripped
                                                    </span>
                                                )}
                                                {!image.has_exif && !image.stripped && (
                                                    <span className="inline-flex items-center px-2 py-0.5 text-[10px] font-medium text-gray-500 bg-gray-100 rounded">
                                                        No EXIF Data
                                                    </span>
                                                )}
                                            </div>
                                        </div>

                                        {/* Actions */}
                                        <div className="shrink-0 flex items-center gap-2">
                                            {image.has_exif && !image.stripped && isPro && (
                                                <button
                                                    type="button"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        setEditIds([image.attachment_id]);
                                                        setShowEditModal(true);
                                                    }}
                                                    className="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50 cursor-pointer transition-colors"
                                                >
                                                    Edit EXIF
                                                </button>
                                            )}
                                            {image.has_exif && !image.stripped ? (
                                                <button
                                                    type="button"
                                                    disabled={isStrippingSingle === image.attachment_id}
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        handleStripSingle(image.attachment_id);
                                                    }}
                                                    className="px-3 py-1.5 text-xs font-medium text-white bg-red-600 rounded hover:bg-red-700 disabled:opacity-50 cursor-pointer transition-colors"
                                                >
                                                    {isStrippingSingle === image.attachment_id ? 'Deleting...' : 'Delete EXIF'}
                                                </button>
                                            ) : (
                                                <span className="text-xs text-gray-400">
                                                    {image.stripped ? 'Stripped' : 'N/A'}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    
                                    {/* Expandable EXIF Details */}
                                    {expandedIds.has(image.attachment_id) && (
                                        <div className="px-4 py-4 bg-gray-50 border-t border-gray-200">
                                            {(() => {
                                                const hasDate = Object.keys(exif.date || {}).length > 0;
                                                const hasAny = hasCamera || hasGps || hasDate || hasOther;

                                                if (!hasAny) {
                                                    return (
                                                        <div className="text-center py-4 text-sm text-gray-500">
                                                            No EXIF data found for this image.
                                                        </div>
                                                    );
                                                }

                                                return (
                                                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                                        {/* Camera Info */}
                                                        {hasCamera && (
                                                            <div className="bg-white rounded p-3 border border-gray-200">
                                                                <h4 className="text-xs font-semibold text-gray-500 uppercase mb-2">Camera</h4>
                                                                <div className="space-y-1 text-sm">
                                                                    {exif.camera.make && <p className="m-0!"><span className="text-gray-500">Make:</span> {exif.camera.make}</p>}
                                                                    {exif.camera.model && <p className="m-0!"><span className="text-gray-500">Model:</span> {exif.camera.model}</p>}
                                                                    {exif.camera.iso && <p className="m-0!"><span className="text-gray-500">ISO:</span> {exif.camera.iso}</p>}
                                                                    {exif.camera.exposure && <p className="m-0!"><span className="text-gray-500">Exposure:</span> {exif.camera.exposure}</p>}
                                                                    {exif.camera.focal_length && <p className="m-0!"><span className="text-gray-500">Focal Length:</span> {exif.camera.focal_length}</p>}
                                                                </div>
                                                            </div>
                                                        )}

                                                        {/* Date Info */}
                                                        {hasDate && (
                                                            <div className="bg-white rounded p-3 border border-gray-200">
                                                                <h4 className="text-xs font-semibold text-gray-500 uppercase mb-2">Date</h4>
                                                                <div className="space-y-1 text-sm">
                                                                    {exif.date.original && <p className="m-0!"><span className="text-gray-500">Taken:</span> {exif.date.original}</p>}
                                                                </div>
                                                            </div>
                                                        )}

                                                        {/* GPS Info */}
                                                        {hasGps && (
                                                            <div className="bg-white rounded p-3 border border-gray-200">
                                                                <h4 className="text-xs font-semibold text-gray-500 uppercase mb-2">GPS Location</h4>
                                                                <div className="space-y-1 text-sm">
                                                                    {exif.gps.lat_ref && <p className="m-0!"><span className="text-gray-500">Lat Ref:</span> {exif.gps.lat_ref}</p>}
                                                                    {exif.gps.lon_ref && <p className="m-0!"><span className="text-gray-500">Lon Ref:</span> {exif.gps.lon_ref}</p>}
                                                                    {exif.gps.altitude && <p className="m-0!"><span className="text-gray-500">Altitude:</span> {exif.gps.altitude}</p>}
                                                                </div>
                                                            </div>
                                                        )}

                                                        {/* Other Info */}
                                                        {hasOther && (
                                                            <div className="bg-white rounded p-3 border border-gray-200">
                                                                <h4 className="text-xs font-semibold text-gray-500 uppercase mb-2">Other</h4>
                                                                <div className="space-y-1 text-sm">
                                                                    {exif.other.software && <p className="m-0!"><span className="text-gray-500">Software:</span> {exif.other.software}</p>}
                                                                    {exif.other.artist && <p className="m-0!"><span className="text-gray-500">Artist:</span> {exif.other.artist}</p>}
                                                                    {exif.other.copyright && <p className="m-0!"><span className="text-gray-500">Copyright:</span> {exif.other.copyright}</p>}
                                                                    {exif.other.user_comment && <p className="m-0!"><span className="text-gray-500">Comment:</span> {exif.other.user_comment}</p>}
                                                                </div>
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

                        {totalPages > 1 && (
                            <Pagination
                                currentPage={currentPage}
                                totalPages={totalPages}
                                totalPosts={totalImages}
                                postsPerPage={limit}
                                onPageChange={setCurrentPage}
                            />
                        )}
                    </div>
                )}
            </div>

            {/* Bulk strip confirmation modal */}
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
                        <p className="text-sm font-semibold text-amber-800 m-0! mb-1">
                            This action cannot be undone.
                        </p>
                        <p className="text-xs text-amber-700 m-0!">
                            EXIF data will be permanently removed from the original image files on your server.
                        </p>
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
