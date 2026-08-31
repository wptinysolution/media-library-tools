import { useCallback, useEffect, useState } from 'react';
import Loader from '@/js/Utils/Loader';
import ProLabel from '@/js/Component/Badges/ProLabel';
import CompressionProgress from '@/js/Component/Compress/CompressionProgress';
import CompressionResults from '@/js/Component/Compress/CompressionResults';
import { useCompressionJob } from '@/js/Component/Compress/useCompressionJob';
import { compressionGetLibraryStatus } from '@/js/Utils/Data';
import type { CompressionMode } from '@/js/Utils/Data';

/**
 * Compress Images page.
 *
 * Operates on the whole media library rather than a hand-picked selection:
 * it reports how many supported images exist, how many are already done, and
 * compresses the remainder. Images that already carry compression data are
 * skipped by the server, so running it repeatedly is safe and resumes where
 * the previous run stopped.
 */
export default function CompressPage() {
    const {
        compression,
        setCompression,
        loadSettings,
        startLibraryJob,
        cancelJob,
        retryJob,
        isFinished,
    } = useCompressionJob();

    const { isLoading, isProcessing, settings, access, modes, engines, progress } = compression;

    const [stats, setStats] = useState<{ total: number; compressed: number; remaining: number } | null>(null);
    const [mode, setMode] = useState<CompressionMode>('balanced');
    const [useCustomQuality, setUseCustomQuality] = useState(false);
    const [quality, setQuality] = useState(82);
    const [backupOriginals, setBackupOriginals] = useState(false);
    const [generatedSizes, setGeneratedSizes] = useState(false);

    const refreshStats = useCallback(async () => {
        try {
            const status = await compressionGetLibraryStatus();
            setStats({
                total: status.total,
                compressed: status.compressed,
                remaining: status.remaining,
            });
        } catch {
            // Counts are informational; the page still works without them.
        }
    }, []);

    useEffect(() => {
        loadSettings();
        refreshStats();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Seed the form from the saved defaults once they arrive.
    useEffect(() => {
        if (!settings) return;
        setMode(settings.mode);
        setUseCustomQuality(settings.use_custom_quality);
        setQuality(settings.quality > 0 ? settings.quality : 82);
        setBackupOriginals(settings.backup_originals);
        setGeneratedSizes(settings.compress_generated_sizes);
    }, [settings]);

    // Once a run ends the remaining count has changed — pull fresh figures.
    useEffect(() => {
        if (isFinished) refreshStats();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isFinished]);

    const isPro = access?.is_pro ?? false;
    const limit = access?.job_limit ?? 0;
    const featureAvailable = access?.feature_available ?? true;
    const remaining = stats?.remaining ?? 0;
    const showProgress = isProcessing || (progress && 'idle' !== progress.status && progress.total > 0);

    // With nothing left uncompressed the only way to act is to re-run over the
    // whole library. That is exactly what turning on backups or generated sizes
    // after an initial pass calls for: those images already carry compression
    // data, but there is genuinely new work to do on them.
    const nothingRemaining = 0 === remaining;
    const rerunAll = nothingRemaining && (stats?.total ?? 0) > 0;
    const targetCount = rerunAll ? (stats?.total ?? 0) : remaining;
    const willTrim = limit > 0 && targetCount > limit;

    const handleStart = () => {
        startLibraryJob({
            mode,
            use_custom_quality: useCustomQuality,
            quality,
            backup_originals: backupOriginals,
            compress_generated_sizes: generatedSizes,
            include_compressed: rerunAll,
        });
    };

    const handleStartAnother = () => {
        setCompression({ progress: null, isProcessing: false });
        refreshStats();
    };

    /** Pro-only toggle that shows a PRO badge instead of changing state. */
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
        <div className="min-h-screen bg-gray-50 overflow-y-auto pb-32">
            <div className="max-w-4xl mx-auto px-3 py-3">
                {isLoading ? (
                    <Loader fullScreen />
                ) : (
                    <div className="space-y-4">
                        <div className="bg-white rounded-lg border border-gray-200">
                            <div className="px-6 py-5 border-b border-gray-200">
                                <h3 className="text-xl m-0! font-semibold text-gray-900">Compress Images</h3>
                                <p className="text-sm text-gray-500 mt-1! m-0!">
                                    Reduce the file size of JPEG, PNG, and WebP images without changing their
                                    filenames, URLs, or dimensions.
                                </p>
                            </div>

                            <div className="p-6 space-y-6">
                                {!featureAvailable && (
                                    <div className="p-3 bg-red-50 border border-red-200 rounded-md">
                                        <p className="text-sm text-red-800 m-0!">
                                            No image compression library is available on this server. Ask your host
                                            to enable the <strong>ImageMagick</strong> or <strong>GD</strong> PHP extension.
                                        </p>
                                    </div>
                                )}

                                {compression.error && (
                                    <div className="p-3 bg-red-50 border border-red-200 rounded-md">
                                        <p className="text-sm text-red-800 m-0!">{compression.error}</p>
                                    </div>
                                )}

                                {/* Library overview */}
                                {stats && (
                                    <div className="grid grid-cols-3 gap-3 text-center">
                                        <div className="bg-gray-50 rounded-md py-3">
                                            <div className="text-2xl font-semibold text-gray-900">{stats.total}</div>
                                            <div className="text-xs text-gray-500">Compressible images</div>
                                        </div>
                                        <div className="bg-green-50 rounded-md py-3">
                                            <div className="text-2xl font-semibold text-green-700">{stats.compressed}</div>
                                            <div className="text-xs text-gray-500">Already compressed</div>
                                        </div>
                                        <div className="bg-blue-50 rounded-md py-3">
                                            <div className="text-2xl font-semibold text-blue-700">{stats.remaining}</div>
                                            <div className="text-xs text-gray-500">Remaining</div>
                                        </div>
                                    </div>
                                )}

                                {featureAvailable && showProgress && progress ? (
                                    <>
                                        <CompressionProgress progress={progress} />
                                        {isFinished && <CompressionResults progress={progress} />}

                                        <div className="flex items-center gap-2">
                                            {isProcessing && (
                                                <button
                                                    type="button"
                                                    className="px-4 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
                                                    onClick={cancelJob}
                                                >
                                                    Stop
                                                </button>
                                            )}
                                            {isFinished && progress.has_failed && (
                                                <button
                                                    type="button"
                                                    className="px-4 py-2 text-sm text-white bg-amber-600 rounded-md hover:bg-amber-700 cursor-pointer"
                                                    onClick={retryJob}
                                                >
                                                    Retry failed
                                                </button>
                                            )}
                                            {isFinished && (
                                                <button
                                                    type="button"
                                                    className="px-4 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer"
                                                    onClick={handleStartAnother}
                                                >
                                                    Done
                                                </button>
                                            )}
                                        </div>
                                    </>
                                ) : featureAvailable && (
                                    <>
                                        {nothingRemaining && stats && (
                                            <div className="p-3 bg-green-50 border border-green-200 rounded-md">
                                                <p className="text-sm text-green-900 m-0!">
                                                    Every supported image has already been compressed. New uploads will
                                                    appear here once you add them.
                                                    {rerunAll && (
                                                        <> If you have just enabled backups or generated image sizes,
                                                        you can run compression again over the whole library — images
                                                        that cannot be improved further are skipped automatically.</>
                                                    )}
                                                </p>
                                            </div>
                                        )}

                                        {willTrim && (
                                            <div className="p-3 bg-amber-50 border border-amber-200 rounded-md">
                                                <p className="text-sm text-amber-900 m-0!">
                                                    The free version compresses up to <strong>{limit}</strong> images per
                                                    run. This run will process {limit} of {targetCount}{' '}
                                                    {rerunAll ? 'images' : 'remaining images'} — run it again to
                                                    continue, or{' '}
                                                    <a href={tsmltParams.proLink} target="_blank" rel="noreferrer" className="underline">
                                                        upgrade to Pro
                                                    </a>{' '}
                                                    to compress everything at once.
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
                                                        className="flex-1 max-w-xs"
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

                                        <div className="flex items-center gap-3 pt-2">
                                            <button
                                                type="button"
                                                className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-md transition-colors font-medium cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                                onClick={handleStart}
                                                disabled={0 === targetCount}
                                            >
                                                {willTrim
                                                    ? `Compress ${limit} images`
                                                    : `${rerunAll ? 'Re-compress' : 'Compress'} ${targetCount} image${1 === targetCount ? '' : 's'}`}
                                            </button>
                                            {engines.length > 0 && (
                                                <span className="text-xs text-gray-400">
                                                    Engine: {engines.map((e) => e.label).join(', ')}
                                                </span>
                                            )}
                                        </div>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
