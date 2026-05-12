import { useEffect, useState, useCallback, useRef } from "react";
import { clearExifScan, exifScanCancel, exifScanGetProgress, exifScanStart } from "@/js/Utils/Data";
import type { ExifScanProgress } from "@/js/Utils/Data";
import ProgressBar from "@/js/Component/Common/ProgressBar";

const POLL_INTERVAL_MS = 3000;

export default function ExifScannerSection() {
    const [progress, setProgress] = useState<ExifScanProgress | null>(null);
    const [showPanel, setShowPanel] = useState(false);
    const [actionPending, setActionPending] = useState(false);

    const isMounted = useRef(false);
    const pollTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const stopPolling = useCallback(() => {
        if (pollTimer.current) {
            clearTimeout(pollTimer.current);
            pollTimer.current = null;
        }
    }, []);

    const pollOnce = useCallback(async () => {
        try {
            const next = await exifScanGetProgress();
            if (!isMounted.current) return;
            setProgress(next);
            if (next.status === 'running') {
                pollTimer.current = setTimeout(pollOnce, POLL_INTERVAL_MS);
            }
        } catch {
            if (isMounted.current) {
                pollTimer.current = setTimeout(pollOnce, POLL_INTERVAL_MS);
            }
        }
    }, []);

    // Load initial progress on mount; resume polling if a scan is already running.
    useEffect(() => {
        isMounted.current = true;
        (async () => {
            try {
                const initial = await exifScanGetProgress();
                if (!isMounted.current) return;
                setProgress(initial);
                if (initial.status === 'running') {
                    setShowPanel(true);
                    pollTimer.current = setTimeout(pollOnce, POLL_INTERVAL_MS);
                }
            } catch {
                // Leave state empty — user can still click Scan.
            }
        })();
        return () => {
            isMounted.current = false;
            stopPolling();
        };
    }, [pollOnce, stopPolling]);

    const startScan = useCallback(async () => {
        if (actionPending) return;
        setActionPending(true);
        stopPolling();
        setShowPanel(true);
        try {
            const next = await exifScanStart();
            if (!isMounted.current) return;
            setProgress(next);
            if (next.status === 'running') {
                pollTimer.current = setTimeout(pollOnce, POLL_INTERVAL_MS);
            }
        } finally {
            if (isMounted.current) setActionPending(false);
        }
    }, [actionPending, pollOnce, stopPolling]);

    const cancelScan = useCallback(async () => {
        if (actionPending) return;
        setActionPending(true);
        stopPolling();
        try {
            const next = await exifScanCancel();
            if (isMounted.current) setProgress(next);
        } finally {
            if (isMounted.current) setActionPending(false);
        }
    }, [actionPending, stopPolling]);

    const handleClearScan = async () => {
        if (!window.confirm("Are you sure you want to clear all EXIF scan results?")) {
            return;
        }
        try {
            await clearExifScan();
            if (isMounted.current) {
                setProgress({
                    status: 'idle',
                    processed: 0,
                    total: 0,
                    with_exif: 0,
                    without_exif: 0,
                    started_at: 0,
                    updated_at: 0,
                    timestamp: '',
                    tick_scheduled: false,
                });
                setShowPanel(false);
            }
        } catch (error) {
            console.error("Error clearing EXIF scan:", error);
        }
    };

    // ── Derived values ──────────────────────────────────────────────────────
    const status        = progress?.status ?? 'idle';
    const processed     = progress?.processed ?? 0;
    const total         = progress?.total ?? 0;
    const withExif      = progress?.with_exif ?? 0;
    const withoutExif   = progress?.without_exif ?? 0;
    const timestamp     = progress?.timestamp ?? '';
    const isScanning    = status === 'running';
    const scanComplete  = status === 'done';
    const wasCancelled  = status === 'cancelled';
    const progressPercent = scanComplete
        ? 100
        : total > 0
            ? Math.round((processed / total) * 100)
            : 0;

    return (
        <div>
            {/* Header bar — always visible, acts as toggle */}
            <div
                className="flex items-center justify-between cursor-pointer select-none"
                onClick={() => { if (!isScanning) setShowPanel(prev => !prev); }}
            >
                <div className="flex items-center gap-2">
                    <svg
                        className={`w-4 h-4 text-gray-400 transition-transform ${showPanel ? "rotate-90" : ""}`}
                        fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    >
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                    </svg>
                    <h3 className="text-sm font-semibold text-gray-900 m-0!">EXIF Scanner</h3>
                    {scanComplete && !showPanel && (
                        <span className="text-[11px] text-gray-400 ml-1">
                            ({withExif} with EXIF, {withoutExif} without)
                        </span>
                    )}
                </div>
                {!showPanel && (
                    <button
                        onClick={(e) => { e.stopPropagation(); setShowPanel(true); startScan(); }}
                        className="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-blue-600 border-none rounded-md hover:bg-blue-700 cursor-pointer transition-colors"
                    >
                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        {scanComplete ? "Re-Scan" : "Scan Now"}
                    </button>
                )}
            </div>

            {/* Expanded content */}
            {showPanel && (<div className="mt-4">

            {/* Stats */}
            <div className="grid grid-cols-3 gap-3 mb-4">
                <div className="p-3 bg-gray-50 rounded-lg text-center">
                    <div className="text-[11px] text-gray-500 mb-0.5">Total</div>
                    <div className="text-2xl font-bold text-gray-900">{total}</div>
                </div>
                <div className="p-3 bg-emerald-50 rounded-lg text-center">
                    <div className="text-[11px] text-gray-500 mb-0.5">With EXIF</div>
                    <div className="text-2xl font-bold text-emerald-600">{withExif}</div>
                </div>
                <div className="p-3 bg-red-50 rounded-lg text-center">
                    <div className="text-[11px] text-gray-500 mb-0.5">Without EXIF</div>
                    <div className="text-2xl font-bold text-red-500">{withoutExif}</div>
                </div>
            </div>

            {/* Progress */}
            {(isScanning || scanComplete || wasCancelled) && (
                <div className="mb-4">
                    <div className="flex items-center justify-between mb-1.5">
                        <span className="text-xs font-medium text-gray-700">
                            {scanComplete
                                ? "Scan Complete"
                                : wasCancelled
                                    ? "Scan Cancelled"
                                    : "Scanning in background…"}
                        </span>
                        <span className="text-xs text-gray-400">
                            {processed} / {total}
                        </span>
                    </div>
                    <ProgressBar percent={progressPercent} />
                </div>
            )}

            {/* Background scan notice */}
            {isScanning && (
                <p className="text-[11px] text-blue-600 m-0! mb-3">
                    Scan runs in the background — you can close this tab and come back to check progress.
                </p>
            )}

            {/* Last scan time */}
            {timestamp && !isScanning && (
                <p className="text-[11px] text-gray-400 m-0! mb-3">
                    Last update: {timestamp}
                </p>
            )}

            {/* Actions */}
            <div className="flex gap-2">
                <button
                    onClick={startScan}
                    disabled={isScanning || actionPending}
                    className={`inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white border-none rounded-md cursor-pointer transition-colors ${
                        (isScanning || actionPending) ? "bg-gray-400 cursor-not-allowed" : "bg-blue-600 hover:bg-blue-700"
                    }`}
                >
                    {isScanning && (
                        <svg className="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                    )}
                    {isScanning ? "Scanning..." : (scanComplete || wasCancelled) ? "Re-Scan" : "Start Scan"}
                </button>
                {isScanning && (
                    <button
                        onClick={cancelScan}
                        disabled={actionPending}
                        className="px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors disabled:opacity-50"
                    >
                        Cancel
                    </button>
                )}
                {(scanComplete || wasCancelled) && !isScanning && (
                    <button
                        onClick={handleClearScan}
                        className="text-xs text-red-400 hover:text-red-600 cursor-pointer transition-colors bg-transparent border-none p-0"
                    >
                        Clear Results
                    </button>
                )}
            </div>
            </div>
        )}
        </div>
    );
}
