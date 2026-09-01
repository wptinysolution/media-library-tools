import { useCallback, useEffect, useState } from 'react';
import Loader from '@/js/Utils/Loader';
import ProLabel from '@/js/Component/Badges/ProLabel';
import CompressionProgress from '@/js/Component/Compress/CompressionProgress';
import CompressionResults from '@/js/Component/Compress/CompressionResults';
import { useConversionJob } from '@/js/Component/Compress/useConversionJob';
import { conversionGetLibraryStatus } from '@/js/Utils/Data';
import type { ConversionFormat } from '@/js/Utils/Data';

/**
 * Convert tab of the Compress Images page.
 *
 * Generates WebP/AVIF companions for library images without touching the
 * originals. Formats the server cannot produce are disabled with an
 * explanation, and AVIF additionally carries a PRO badge — the server enforces
 * both independently of what this UI shows.
 */
export default function ConvertTab() {
    const {
        conversion,
        setConversion,
        loadCapabilities,
        startLibraryJob,
        cancelJob,
        isFinished,
    } = useConversionJob();

    const { isLoading, isProcessing, settings, capabilities, access, available, progress } = conversion;

    const [stats, setStats] = useState<{ total: number; converted: number; remaining: number } | null>(null);
    const [formats, setFormats] = useState<Record<ConversionFormat, boolean>>({ webp: true, avif: false });
    const [webpQuality, setWebpQuality] = useState(82);
    const [avifQuality, setAvifQuality] = useState(70);
    const [generatedSizes, setGeneratedSizes] = useState(false);

    const refreshStats = useCallback(async () => {
        try {
            const status = await conversionGetLibraryStatus();
            setStats({
                total: status.total,
                converted: status.converted,
                remaining: status.remaining,
            });
        } catch {
            // Counts are informational; the tab still works without them.
        }
    }, []);

    useEffect(() => {
        loadCapabilities();
        refreshStats();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Seed the form from saved defaults once they arrive.
    useEffect(() => {
        if (!settings) return;
        setFormats({ webp: settings.webp_enabled, avif: settings.avif_enabled });
        setWebpQuality(settings.webp_quality);
        setAvifQuality(settings.avif_quality);
        setGeneratedSizes(settings.generated_sizes);
    }, [settings]);

    useEffect(() => {
        if (isFinished) refreshStats();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isFinished]);

    const serverWebp = capabilities?.formats?.webp ?? false;
    const serverAvif = capabilities?.formats?.avif ?? false;
    const canAvif = (access?.can_avif ?? false) && serverAvif;
    const limit = access?.job_limit ?? 0;

    const selected = (['webp', 'avif'] as ConversionFormat[]).filter(
        (f) => formats[f] && ('webp' === f ? serverWebp : canAvif)
    );

    const nothingRemaining = 0 === (stats?.remaining ?? 0);
    const rerunAll = nothingRemaining && (stats?.total ?? 0) > 0;
    const targetCount = rerunAll ? (stats?.total ?? 0) : (stats?.remaining ?? 0);
    const willTrim = limit > 0 && targetCount > limit;
    const showProgress = isProcessing || (progress && 'idle' !== progress.status && progress.total > 0);

    const handleStart = () => {
        startLibraryJob({
            formats: selected,
            quality: { webp: webpQuality, avif: avifQuality },
            generated_sizes: generatedSizes,
            include_converted: rerunAll,
        });
    };

    const handleDone = () => {
        setConversion({ progress: null, isProcessing: false });
        refreshStats();
    };

    /** One format checkbox, with the reason it is unavailable when it is. */
    const formatToggle = (format: ConversionFormat, label: string, description: string, allowed: boolean, serverOk: boolean) => (
        <label className={`flex items-start gap-2 p-3 border rounded-md ${allowed ? 'cursor-pointer' : 'cursor-not-allowed opacity-70'} ${formats[format] && allowed ? 'border-blue-500 bg-blue-50' : 'border-gray-200'}`}>
            <input
                type="checkbox"
                className="w-4 h-4 mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                checked={allowed && formats[format]}
                disabled={!allowed}
                onChange={(e) => setFormats({ ...formats, [format]: e.target.checked })}
            />
            <span>
                <span className="text-sm font-medium text-gray-900">
                    {label}
                    {'avif' === format && ! (access?.can_avif ?? false) && <ProLabel />}
                </span>
                <span className="block text-xs text-gray-500">{description}</span>
                {!serverOk && (
                    <span className="block text-xs text-red-600 mt-0.5">
                        Not supported by this server&rsquo;s image library.
                    </span>
                )}
            </span>
        </label>
    );

    if (isLoading) {
        return <Loader />;
    }

    return (
        <div className="p-6 space-y-6">
            {!available && (
                <div className="p-3 bg-red-50 border border-red-200 rounded-md">
                    <p className="text-sm text-red-800 m-0!">
                        This server cannot produce WebP or AVIF images. Ask your host to enable
                        <strong> ImageMagick</strong> or <strong>GD</strong> with WebP support.
                    </p>
                </div>
            )}

            {conversion.error && (
                <div className="p-3 bg-red-50 border border-red-200 rounded-md">
                    <p className="text-sm text-red-800 m-0!">{conversion.error}</p>
                </div>
            )}

            {stats && (
                <div className="grid grid-cols-3 gap-3 text-center">
                    <div className="bg-gray-50 rounded-md py-3">
                        <div className="text-2xl font-semibold text-gray-900">{stats.total}</div>
                        <div className="text-xs text-gray-500">Convertible images</div>
                    </div>
                    <div className="bg-green-50 rounded-md py-3">
                        <div className="text-2xl font-semibold text-green-700">{stats.converted}</div>
                        <div className="text-xs text-gray-500">Already converted</div>
                    </div>
                    <div className="bg-blue-50 rounded-md py-3">
                        <div className="text-2xl font-semibold text-blue-700">{stats.remaining}</div>
                        <div className="text-xs text-gray-500">Remaining</div>
                    </div>
                </div>
            )}

            {available && showProgress && progress ? (
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
                        {isFinished && (
                            <button
                                type="button"
                                className="px-4 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer"
                                onClick={handleDone}
                            >
                                Done
                            </button>
                        )}
                    </div>
                </>
            ) : available && (
                <>
                    <div className="p-3 bg-blue-50 border border-blue-200 rounded-md">
                        <p className="text-sm text-blue-900 m-0!">
                            Converted files are saved alongside your originals &mdash;
                            <code className="mx-1">image.jpg</code> keeps its place in the media library and
                            <code className="mx-1">image.webp</code> is generated next to it. Nothing is replaced.
                        </p>
                    </div>

                    {rerunAll && (
                        <div className="p-3 bg-green-50 border border-green-200 rounded-md">
                            <p className="text-sm text-green-900 m-0!">
                                Every supported image has already been converted. Run it again to regenerate
                                them &mdash; useful after changing formats or quality.
                            </p>
                        </div>
                    )}

                    {willTrim && (
                        <div className="p-3 bg-amber-50 border border-amber-200 rounded-md">
                            <p className="text-sm text-amber-900 m-0!">
                                The free version converts up to <strong>{limit}</strong> images per run. This run
                                will process {limit} of {targetCount} &mdash; run it again to continue, or{' '}
                                <a href={tsmltParams.proLink} target="_blank" rel="noreferrer" className="underline">
                                    upgrade to Pro
                                </a>.
                            </p>
                        </div>
                    )}

                    <div>
                        <h4 className="text-sm font-semibold text-gray-700 m-0! mb-2!">Output formats</h4>
                        <div className="space-y-2">
                            {formatToggle('webp', 'WebP', 'Widely supported. Typically 25–35% smaller than JPEG.', serverWebp, serverWebp)}
                            {formatToggle('avif', 'AVIF', 'Smallest files, but noticeably slower to generate.', canAvif, serverAvif)}
                        </div>
                    </div>

                    {selected.includes('avif') && (
                        <div className="p-3 bg-amber-50 border border-amber-200 rounded-md">
                            <p className="text-sm text-amber-900 m-0!">
                                AVIF encoding is several times slower than WebP, so this run will take
                                noticeably longer and process fewer images per batch.
                            </p>
                        </div>
                    )}

                    {(access?.can_custom_quality ?? false) && (
                        <div className="space-y-3 pt-4 border-t border-gray-100">
                            {selected.includes('webp') && (
                                <div className="flex items-center gap-3">
                                    <span className="text-sm text-gray-700 w-28">WebP quality</span>
                                    <input type="range" min={1} max={100} value={webpQuality}
                                           onChange={(e) => setWebpQuality(parseInt(e.target.value, 10))}
                                           className="flex-1 max-w-xs" />
                                    <span className="text-sm font-semibold text-gray-900 w-10">{webpQuality}</span>
                                </div>
                            )}
                            {selected.includes('avif') && (
                                <div className="flex items-center gap-3">
                                    <span className="text-sm text-gray-700 w-28">AVIF quality</span>
                                    <input type="range" min={1} max={100} value={avifQuality}
                                           onChange={(e) => setAvifQuality(parseInt(e.target.value, 10))}
                                           className="flex-1 max-w-xs" />
                                    <span className="text-sm font-semibold text-gray-900 w-10">{avifQuality}</span>
                                </div>
                            )}
                        </div>
                    )}

                    <div className="pt-4 border-t border-gray-100">
                        <label className={`flex items-start gap-2 ${(access?.can_generated_sizes ?? false) ? 'cursor-pointer' : 'cursor-not-allowed opacity-70'}`}>
                            <input
                                type="checkbox"
                                className="w-4 h-4 mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                checked={(access?.can_generated_sizes ?? false) && generatedSizes}
                                disabled={!(access?.can_generated_sizes ?? false)}
                                onChange={(e) => setGeneratedSizes(e.target.checked)}
                            />
                            <span>
                                <span className="text-sm font-medium text-gray-900">
                                    Convert generated image sizes
                                    {!(access?.can_generated_sizes ?? false) && <ProLabel />}
                                </span>
                                <span className="block text-xs text-gray-500">
                                    Also generate thumbnails and other registered sizes in each format.
                                </span>
                            </span>
                        </label>
                    </div>

                    <div className="flex items-center gap-3 pt-2">
                        <button
                            type="button"
                            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-md transition-colors font-medium cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                            onClick={handleStart}
                            disabled={0 === targetCount || 0 === selected.length}
                        >
                            {willTrim
                                ? `Convert ${limit} images`
                                : `${rerunAll ? 'Re-convert' : 'Convert'} ${targetCount} image${1 === targetCount ? '' : 's'}`}
                        </button>
                        {0 === selected.length && (
                            <span className="text-xs text-gray-500">Select at least one output format.</span>
                        )}
                    </div>
                </>
            )}
        </div>
    );
}
