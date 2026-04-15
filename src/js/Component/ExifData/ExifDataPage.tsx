import React, { useState, useCallback, useRef } from "react";
import { useParams } from "react-router-dom";
import { useStore } from "@/js/Utils/store";
import { exifStripBatch, getExifResults, getExifStatus, exifStripSingle } from "@/js/Utils/Data";
import ProgressBar from "@/js/Component/Common/ProgressBar";
import Pagination from "@/js/Component/Common/Pagination";
import Modal from "@/js/Component/Common/Modal";
import ProLabel from "@/js/Component/Badges/ProLabel";
import ExifScannerSection from "@/js/Component/ExifData/ExifScannerSection";

interface ExifImage {
    attachment_id: number;
    title: string;
    url: string;
    has_exif: boolean;
    exif_summary: {
        has_exif: boolean;
        camera: Record<string, string>;
        gps: Record<string, any>;
        date: Record<string, string>;
        other: Record<string, string>;
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
    const [isStartStripAll, setIsStartStripAll] = useState(false);
    const [expandedId, setExpandedId] = useState<number | null>(null);
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

    const startStripAll = async () => {
        if (!isPro) {
            setGeneralData({ openProModal: true });
            return;
        }

        setIsStartStripAll(true);
    };

    const confirmStripAll = async () => {
        setIsStartStripAll(false);
        setIsStripping(true);
        let offset = 0;
        let complete = false;
        let totalStripped = 0;
        let totalFailed = 0;

        try {
            while (!complete) {
                const response = await exifStripBatch({
                    offset,
                    batch_size: 20,
                }) as any;
                const result = response.data || response;
                offset = result.processed;
                complete = result.complete;
                totalStripped += result.stripped || 0;
                totalFailed += result.failed || 0;
                setStripProgress({
                    processed: result.processed,
                    total: result.total,
                    stripped: totalStripped,
                    failed: totalFailed,
                });
            }

            setIsStripping(false);
            await loadStatus();
            await loadResults(currentPage);
        } catch (error) {
            console.error('Error during strip:', error);
            setIsStripping(false);
        }
    };

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

    const handleBulkStrip = () => {
        if (!isPro) {
            setGeneralData({ openProModal: true });
            return;
        }
        if (selectedIds.size === 0) return;
        setShowStripModal(true);
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
    const imagesWithExif = images.filter(img => img.has_exif && !img.stripped);

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
            <div className="flex items-center gap-4 px-8 py-3 bg-white rounded-t-lg border border-b-0 border-gray-200">
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
                {selectedIds.size > 0 && isPro && (
                    <button
                        type="button"
                        className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors"
                        onClick={() => {
                            // TODO: Open bulk edit EXIF modal
                        }}
                    >
                        Edit Selected ({selectedIds.size})
                    </button>
                )}
                {selectedIds.size > 0 && (
                    <>
                        <button
                            type="button"
                            className="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 cursor-pointer transition-colors"
                            onClick={handleBulkStrip}
                        >
                            Strip Selected ({selectedIds.size})
                        </button>
                    </>
                )}
                {isPro ? (
                    <button
                        type="button"
                        className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer transition-colors disabled:opacity-50"
                        onClick={startStripAll}
                        disabled={isStripping}
                    >
                        {isStripping ? 'Stripping...' : 'Strip All EXIF'}
                    </button>
                ) : (
                    <button
                        type="button"
                        className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer transition-colors opacity-80"
                        onClick={() => setGeneralData({ openProModal: true })}
                    >
                        Strip All EXIF <ProLabel />
                    </button>
                )}
                <div className="ml-auto text-sm text-gray-500">
                    {totalImages} images found
                </div>
            </div>

            {/* Strip progress */}
            {isStripping && (
                <div className="px-4 py-4 bg-white border-b border-gray-200">
                    <p className="text-sm text-gray-600 mb-2 mt-0!">
                        Stripping EXIF data... {stripProgress.processed} / {stripProgress.total} processed
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
                                        onClick={() => setExpandedId(expandedId === image.attachment_id ? null : image.attachment_id)}
                                    >
                                        {/* Checkbox */}
                                        <div className="shrink-0">
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
                                                        // TODO: Open Edit EXIF modal
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
                                                    className="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700 disabled:opacity-50 cursor-pointer transition-colors"
                                                >
                                                    {isStrippingSingle === image.attachment_id ? 'Stripping...' : 'Strip EXIF'}
                                                </button>
                                            ) : (
                                                <span className="text-xs text-gray-400">
                                                    {image.stripped ? 'Stripped' : 'N/A'}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    
                                    {/* Expandable EXIF Details */}
                                    {expandedId === image.attachment_id && (
                                        <div className="px-4 py-4 bg-gray-50 border-t border-gray-200">
                                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                {/* Camera Info */}
                                                {hasCamera && (
                                                    <div className="bg-white rounded p-3 border border-gray-200">
                                                        <h4 className="text-xs font-semibold text-gray-500 uppercase mb-2">Camera</h4>
                                                        <div className="space-y-1 text-sm">
                                                            {exif.camera.Make && <p><span className="text-gray-500">Make:</span> {exif.camera.Make}</p>}
                                                            {exif.camera.Model && <p><span className="text-gray-500">Model:</span> {exif.camera.Model}</p>}
                                                            {exif.camera.Software && <p><span className="text-gray-500">Software:</span> {exif.camera.Software}</p>}
                                                        </div>
                                                    </div>
                                                )}
                                                
                                                {/* GPS Info */}
                                                {hasGps && (
                                                    <div className="bg-white rounded p-3 border border-gray-200">
                                                        <h4 className="text-xs font-semibold text-gray-500 uppercase mb-2">GPS Location</h4>
                                                        <div className="space-y-1 text-sm">
                                                            {exif.gps.latitude && <p><span className="text-gray-500">Lat:</span> {exif.gps.latitude}</p>}
                                                            {exif.gps.longitude && <p><span className="text-gray-500">Long:</span> {exif.gps.longitude}</p>}
                                                            {exif.gps.altitude && <p><span className="text-gray-500">Altitude:</span> {exif.gps.altitude}</p>}
                                                        </div>
                                                    </div>
                                                )}
                                                
                                                {/* Other Info */}
                                                {hasOther && (
                                                    <div className="bg-white rounded p-3 border border-gray-200">
                                                        <h4 className="text-xs font-semibold text-gray-500 uppercase mb-2">Other</h4>
                                                        <div className="space-y-1 text-sm">
                                                            {exif.other.DateTimeOriginal && <p><span className="text-gray-500">Date:</span> {exif.other.DateTimeOriginal}</p>}
                                                            {exif.other.ImageWidth && <p><span className="text-gray-500">Width:</span> {exif.other.ImageWidth}</p>}
                                                            {exif.other.ImageHeight && <p><span className="text-gray-500">Height:</span> {exif.other.ImageHeight}</p>}
                                                            {exif.other.Orientation && <p><span className="text-gray-500">Orientation:</span> {exif.other.Orientation}</p>}
                                                            {exif.other.ISO && <p><span className="text-gray-500">ISO:</span> {exif.other.ISO}</p>}
                                                            {exif.other.FocalLength && <p><span className="text-gray-500">Focal:</span> {exif.other.FocalLength}</p>}
                                                            {exif.other.ExposureTime && <p><span className="text-gray-500">Exposure:</span> {exif.other.ExposureTime}</p>}
                                                            {exif.other.FNumber && <p><span className="text-gray-500">F-Number:</span> {exif.other.FNumber}</p>}
                                                        </div>
                                                    </div>
                                                )}
                                                
                                                {/* No EXIF Data Message */}
                                                {!hasCamera && !hasGps && !hasOther && (
                                                    <div className="col-span-full text-center py-4 text-sm text-gray-500">
                                                        No EXIF data found for this image.
                                                    </div>
                                                )}
                                            </div>
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
                        <h3 className="text-lg font-semibold text-gray-900 m-0!">Strip EXIF from Selected Images</h3>
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
                            Yes, Strip EXIF from {selectedIds.size} Image{selectedIds.size !== 1 ? 's' : ''}
                        </button>
                    </div>
                }
            >
                <div className="px-6 py-5 space-y-4">
                    <p className="text-sm text-gray-700 mt-0!">
                        You are about to strip EXIF metadata from <strong>{selectedIds.size} image{selectedIds.size !== 1 ? 's' : ''}</strong>.
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

            {/* Strip All confirmation modal */}
            <Modal
                isOpen={isStartStripAll}
                onClose={() => setIsStartStripAll(false)}
                title="Strip All EXIF?"
                maxWidth="max-w-md"
                closeOnBackdrop={false}
                footer={
                    <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200">
                        <button
                            type="button"
                            className="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors"
                            onClick={() => setIsStartStripAll(false)}
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            className="px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer transition-colors"
                            onClick={confirmStripAll}
                        >
                            Yes, Strip All
                        </button>
                    </div>
                }
            >
                <div className="px-6 py-5">
                    <p className="text-sm! text-gray-600 m-0!">
                        You are about to <strong className="text-blue-600">strip EXIF data</strong> from all images. This action <strong>cannot be undone</strong>.
                    </p>
                </div>
            </Modal>
        </div>
    );
}
