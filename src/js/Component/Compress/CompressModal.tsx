import { useEffect, useState } from 'react';
import Modal from '@/js/Component/Common/Modal';
import ProLabel from '@/js/Component/Badges/ProLabel';
import Loader from '@/js/Utils/Loader';
import CompressionProgress from '@/js/Component/Compress/CompressionProgress';
import CompressionResults from '@/js/Component/Compress/CompressionResults';
import { useCompressionJob } from '@/js/Component/Compress/useCompressionJob';
import { useStore } from '@/js/Utils/store';
import type { CompressionMode } from '@/js/Utils/Data';

/**
 * Bulk compression modal.
 *
 * Presents the preset picker and Pro options, starts the job, then switches to
 * live progress. Every option shown here is re-validated and re-authorised on
 * the server; the gating below only shapes the UI.
 */
export default function CompressModal() {
    const { mediaData, setMediaData } = useStore();
    const {
        compression,
        setCompression,
        loadSettings,
        startJob,
        cancelJob,
        retryJob,
        isFinished,
    } = useCompressionJob();

    const { isModalOpen, isLoading, isProcessing, selectedIds, settings, access, modes, engines, progress } = compression;

    const [mode, setMode] = useState<CompressionMode>('balanced');
    const [useCustomQuality, setUseCustomQuality] = useState(false);
    const [quality, setQuality] = useState(82);
    const [backupOriginals, setBackupOriginals] = useState(false);
    const [generatedSizes, setGeneratedSizes] = useState(false);

    // Load settings whenever the modal opens.
    useEffect(() => {
        if (isModalOpen) {
            loadSettings();
        }
    }, [isModalOpen]);

    // Seed the form from the saved defaults once they arrive.
    useEffect(() => {
        if (!settings) return;
        setMode(settings.mode);
        setUseCustomQuality(settings.use_custom_quality);
        setQuality(settings.quality > 0 ? settings.quality : 82);
        setBackupOriginals(settings.backup_originals);
        setGeneratedSizes(settings.compress_generated_sizes);
    }, [settings]);

    const isPro = access?.is_pro ?? false;
    const limit = access?.job_limit ?? 0;
    const featureAvailable = access?.feature_available ?? true;
    const willTrim = limit > 0 && selectedIds.length > limit;
    const showProgress = isProcessing || (progress && 'idle' !== progress.status && progress.total > 0);

    const handleClose = () => {
        // A running job keeps going server-side; closing only hides the UI.
        setCompression({ isModalOpen: false });

        // Refresh the table so compressed sizes are reflected once work ended.
        if (isFinished) {
            setMediaData({
                postQuery: { ...mediaData.postQuery, isUpdate: !mediaData.postQuery.isUpdate },
            });
        }
    };

    const handleStart = () => {
        startJob(selectedIds, {
            mode,
            use_custom_quality: useCustomQuality,
            quality,
            backup_originals: backupOriginals,
            compress_generated_sizes: generatedSizes,
        });
    };

    /** Pro-only toggle that opens the upgrade modal instead of changing state. */
    const proToggle = (
        label: string,
        description: string,
        checked: boolean,
        allowed: boolean,
        onChange: (next: boolean) => void
    ) => (
        <label className={`flex items-start gap-2 ${allowed ? 'cursor-pointer' : 'cursor-not-allowed opacity-70'}`}>
            <input
                type="checkbox"
                className="w-4 h-4 mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                checked={allowed && checked}
                disabled={!allowed}
                onChange={(e) => onChange(e.target.checked)}
            />
            <span>
                <span className="text-sm font-medium text-gray-900">
                    {label}
                    {!allowed && <ProLabel />}
                </span>
                <span className="block text-xs text-gray-500">{description}</span>
            </span>
        </label>
    );

    return (
        <Modal
            isOpen={isModalOpen}
            onClose={handleClose}
            title="Compress Images"
            maxWidth="max-w-[640px]"
            closeOnBackdrop={!isProcessing}
            footer={
                <div className="flex items-center justify-end gap-2 px-6 py-4 border-t border-gray-200">
                    {isProcessing ? (
                        <>
                            <button
                                type="button"
                                className="px-4 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
                                onClick={cancelJob}
                            >
                                Stop
                            </button>
                            <button
                                type="button"
                                className="px-4 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer"
                                onClick={handleClose}
                            >
                                Run in background
                            </button>
                        </>
                    ) : (
                        <>
                            <button
                                type="button"
                                className="px-4 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
                                onClick={handleClose}
                            >
                                Close
                            </button>
                            {isFinished && progress?.has_failed && (
                                <button
                                    type="button"
                                    className="px-4 py-2 text-sm text-white bg-amber-600 rounded-md hover:bg-amber-700 cursor-pointer"
                                    onClick={retryJob}
                                >
                                    Retry failed
                                </button>
                            )}
                            {!isFinished && (
                                <button
                                    type="button"
                                    className="px-4 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                    onClick={handleStart}
                                    disabled={isLoading || !featureAvailable || !selectedIds.length}
                                >
                                    Compress {selectedIds.length} image{1 === selectedIds.length ? '' : 's'}
                                </button>
                            )}
                        </>
                    )}
                </div>
            }
        >
            <div className="px-6 py-4 space-y-5 max-h-[65vh] overflow-y-auto">
                {isLoading && <Loader />}

                {!isLoading && !featureAvailable && (
                    <div className="p-3 bg-red-50 border border-red-200 rounded-md">
                        <p className="text-sm text-red-800 m-0!">
                            No image compression library is available on this server. Ask your host to
                            enable the <strong>ImageMagick</strong> or <strong>GD</strong> PHP extension.
                        </p>
                    </div>
                )}

                {!isLoading && compression.error && (
                    <div className="p-3 bg-red-50 border border-red-200 rounded-md">
                        <p className="text-sm text-red-800 m-0!">{compression.error}</p>
                    </div>
                )}

                {!isLoading && featureAvailable && showProgress && progress && (
                    <>
                        <CompressionProgress progress={progress} />
                        {isFinished && <CompressionResults progress={progress} />}
                    </>
                )}

                {!isLoading && featureAvailable && !showProgress && (
                    <>
                        {willTrim && (
                            <div className="p-3 bg-amber-50 border border-amber-200 rounded-md">
                                <p className="text-sm text-amber-900 m-0!">
                                    The free version compresses up to <strong>{limit}</strong> images per run.
                                    Only the first {limit} of your {selectedIds.length} selected images will be
                                    processed. <a href={tsmltParams.proLink} target="_blank" rel="noreferrer" className="underline">Upgrade to Pro</a> for unlimited compression.
                                </p>
                            </div>
                        )}

                        <div>
                            <h4 className="text-sm font-semibold text-gray-700 m-0! mb-2!">Compression level</h4>
                            <div className="space-y-2">
                                {modes.map((item) => (
                                    <label
                                        key={item.value}
                                        className={`flex items-start gap-2 p-3 border rounded-md cursor-pointer transition-colors ${
                                            mode === item.value
                                                ? 'border-blue-500 bg-blue-50'
                                                : 'border-gray-200 hover:border-gray-300'
                                        }`}
                                    >
                                        <input
                                            type="radio"
                                            name="tsmlt-compression-mode"
                                            className="mt-0.5 text-blue-600 focus:ring-blue-500"
                                            checked={mode === item.value}
                                            onChange={() => setMode(item.value)}
                                        />
                                        <span>
                                            <span className="text-sm font-medium text-gray-900">{item.label}</span>
                                            <span className="block text-xs text-gray-500">{item.description}</span>
                                        </span>
                                    </label>
                                ))}
                            </div>
                        </div>

                        <div className="space-y-3 pt-4 border-t border-gray-100">
                            {proToggle(
                                'Custom quality',
                                'Set an exact quality value instead of using a preset.',
                                useCustomQuality,
                                access?.can_custom_quality ?? false,
                                setUseCustomQuality
                            )}

                            {isPro && useCustomQuality && (
                                <div className="flex items-center gap-3 pl-6">
                                    <input
                                        type="range"
                                        min={1}
                                        max={100}
                                        value={quality}
                                        onChange={(e) => setQuality(parseInt(e.target.value, 10))}
                                        className="flex-1"
                                    />
                                    <span className="text-sm font-semibold text-gray-900 w-10 text-right">{quality}</span>
                                </div>
                            )}

                            {proToggle(
                                'Back up original images',
                                'Keep a copy of each original so it can be restored later.',
                                backupOriginals,
                                access?.can_backup ?? false,
                                setBackupOriginals
                            )}

                            {proToggle(
                                'Compress generated image sizes',
                                'Also compress thumbnails and other generated sizes.',
                                generatedSizes,
                                access?.can_generated_sizes ?? false,
                                setGeneratedSizes
                            )}
                        </div>

                        {engines.length > 0 && (
                            <p className="text-xs text-gray-400 m-0!">
                                Compression engine: {engines.map((e) => e.label).join(', ')}
                            </p>
                        )}
                    </>
                )}
            </div>
        </Modal>
    );
}
