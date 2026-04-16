import { useEffect, useState, useCallback, useRef } from "react";
import { getExifScanStatus, runExifScanBatch, clearExifScan } from "@/js/Utils/Data";
import ProgressBar from "@/js/Component/Common/ProgressBar";

interface ScanStatus {
    processed: number;
    total: number;
    with_exif: number;
    without_exif: number;
}

export default function ExifScannerSection() {
    const [scanStatus, setScanStatus] = useState<ScanStatus>({
        processed: 0,
        total: 0,
        with_exif: 0,
        without_exif: 0,
    });
    const [isScanning, setIsScanning] = useState(false);
    const [scanComplete, setScanComplete] = useState(false);
    const [lastScanTime, setLastScanTime] = useState<string>("");
    const [showPanel, setShowPanel] = useState(true);
    const isMounted = useRef(false);

    useEffect(() => {
        isMounted.current = true;
        loadStatus();
        return () => { isMounted.current = false; };
    }, []);

    const loadStatus = useCallback(async () => {
        try {
            const result = await getExifScanStatus() as Record<string, unknown>;
            if (isMounted.current) {
                const processed = (result.processed as number) || 0;
                const total = (result.total as number) || 0;

                setScanStatus({
                    processed,
                    total,
                    with_exif: (result.with_exif as number) || 0,
                    without_exif: (result.without_exif as number) || 0,
                });

                if (result.timestamp) {
                    setLastScanTime(result.timestamp as string);
                }

                // If a previous scan completed, restore the panel.
                if (processed > 0 && total > 0 && processed >= total) {
                    setScanComplete(true);
                    setShowPanel(true);
                }
            }
        } catch (error) {
            console.error("Error loading EXIF scan status:", error);
        }
    }, []);

    const startScan = async () => {
        setShowPanel(true);
        setIsScanning(true);
        setScanComplete(false);

        setScanStatus({ processed: 0, total: 0, with_exif: 0, without_exif: 0 });

        try {
            let finalData: Record<string, unknown> = {};

            await runExifScanBatch((data: Record<string, unknown>) => {
                if (isMounted.current) {
                    finalData = data;
                    setScanStatus({
                        processed: (data.processed as number) || 0,
                        total: (data.total as number) || 0,
                        with_exif: (data.with_exif as number) || 0,
                        without_exif: (data.without_exif as number) || 0,
                    });
                }
            });

            if (isMounted.current) {
                setScanStatus({
                    processed: (finalData.processed as number) || 0,
                    total: (finalData.total as number) || 0,
                    with_exif: (finalData.with_exif as number) || 0,
                    without_exif: (finalData.without_exif as number) || 0,
                });

                const status = await getExifScanStatus() as Record<string, unknown>;
                if (status.timestamp && isMounted.current) {
                    setLastScanTime(status.timestamp as string);
                }

                setIsScanning(false);
                setScanComplete(true);
            }
        } catch (error) {
            console.error("Error during EXIF scan:", error);
            if (isMounted.current) {
                setIsScanning(false);
            }
        }
    };

    const handleClearScan = async () => {
        if (!window.confirm("Are you sure you want to clear all EXIF scan results?")) {
            return;
        }

        try {
            await clearExifScan();
            if (isMounted.current) {
                setScanStatus({ processed: 0, total: 0, with_exif: 0, without_exif: 0 });
                setLastScanTime("");
                setScanComplete(false);
                setShowPanel(false);
            }
        } catch (error) {
            console.error("Error clearing EXIF scan:", error);
        }
    };

    const progressPercent = scanComplete
        ? 100
        : scanStatus.total > 0
            ? Math.round((scanStatus.processed / scanStatus.total) * 100)
            : 0;

    // Collapsed: just show the scan button
    if (!showPanel) {
        return (
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-sm font-semibold text-gray-900 mt-0 mb-1">EXIF Scanner</h3>
                    <p className="text-xs text-gray-500 m-0!">Scan your media library to identify images with and without EXIF metadata.</p>
                </div>
                <button
                    onClick={startScan}
                    className="shrink-0 inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-blue-600 border-none rounded-md hover:bg-blue-700 cursor-pointer transition-colors"
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    Scan Now
                </button>
            </div>
        );
    }

    // Expanded: full scanner panel
    return (
        <div>
            <div className="flex items-center justify-between mb-4">
                <h3 className="text-sm font-semibold text-gray-900 m-0!">EXIF Scanner</h3>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-3 gap-3 mb-4">
                <div className="p-3 bg-gray-50 rounded-lg text-center">
                    <div className="text-[11px] text-gray-500 mb-0.5">Total</div>
                    <div className="text-2xl font-bold text-gray-900">{scanStatus.total}</div>
                </div>
                <div className="p-3 bg-emerald-50 rounded-lg text-center">
                    <div className="text-[11px] text-gray-500 mb-0.5">With EXIF</div>
                    <div className="text-2xl font-bold text-emerald-600">{scanStatus.with_exif}</div>
                </div>
                <div className="p-3 bg-red-50 rounded-lg text-center">
                    <div className="text-[11px] text-gray-500 mb-0.5">Without EXIF</div>
                    <div className="text-2xl font-bold text-red-500">{scanStatus.without_exif}</div>
                </div>
            </div>

            {/* Progress */}
            {(isScanning || scanComplete) && (
                <div className="mb-4">
                    <div className="flex items-center justify-between mb-1.5">
                        <span className="text-xs font-medium text-gray-700">
                            {scanComplete ? "Scan Complete" : "Scanning..."}
                        </span>
                        <span className="text-xs text-gray-400">
                            {scanStatus.processed} / {scanStatus.total}
                        </span>
                    </div>
                    <ProgressBar percent={progressPercent} />
                </div>
            )}

            {/* Last scan time */}
            {lastScanTime && !isScanning && (
                <p className="text-[11px] text-gray-400 m-0! mb-3">
                    Last scan: {lastScanTime}
                </p>
            )}

            {/* Actions */}
            <div className="flex gap-2">
                <button
                    onClick={startScan}
                    disabled={isScanning}
                    className={`inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white border-none rounded-md cursor-pointer transition-colors ${
                        isScanning ? "bg-gray-400 cursor-not-allowed" : "bg-blue-600 hover:bg-blue-700"
                    }`}
                >
                    {isScanning && (
                        <svg className="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                    )}
                    {isScanning ? "Scanning..." : scanComplete ? "Re-Scan" : "Start Scan"}
                </button>
                { isScanning && (
                    <button
                        onClick={() => { setShowPanel(false); }}
                        className="px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors"
                    >
                        Cancel
                    </button>
                )}
                {scanComplete && !isScanning && (
                    <button
                        onClick={handleClearScan}
                        className="text-xs text-red-400 hover:text-red-600 cursor-pointer transition-colors bg-transparent border-none p-0"
                    >
                        Clear Results
                    </button>
                )}
            </div>
        </div>
    );
}
