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
    const isMounted = useRef(false);

    // Load initial status on mount — restore completed state if a previous scan exists.
    useEffect(() => {
        isMounted.current = true;
        loadStatus();

        return () => {
            isMounted.current = false;
        };
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

                // If a previous scan completed, restore the completed state.
                if (processed > 0 && total > 0 && processed >= total) {
                    setScanComplete(true);
                }
            }
        } catch (error) {
            console.error("Error loading EXIF scan status:", error);
        }
    }, []);

    const startScan = async () => {
        setIsScanning(true);
        setScanComplete(false);

        // Reset status for fresh scan.
        setScanStatus({
            processed: 0,
            total: 0,
            with_exif: 0,
            without_exif: 0,
        });

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
                // Set final state from the last batch response — don't refetch.
                setScanStatus({
                    processed: (finalData.processed as number) || 0,
                    total: (finalData.total as number) || 0,
                    with_exif: (finalData.with_exif as number) || 0,
                    without_exif: (finalData.without_exif as number) || 0,
                });

                // Fetch timestamp from server.
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
                setScanStatus({
                    processed: 0,
                    total: 0,
                    with_exif: 0,
                    without_exif: 0,
                });
                setLastScanTime("");
                setScanComplete(false);
            }
        } catch (error) {
            console.error("Error clearing EXIF scan:", error);
        }
    };

    // Progress: if scan is complete, always show 100%. Otherwise calculate from processed/total.
    const progressPercent = scanComplete
        ? 100
        : scanStatus.total > 0
            ? Math.round((scanStatus.processed / scanStatus.total) * 100)
            : 0;

    return (
        <div>
            <h3 className="mt-0 mb-3">Scanner</h3>
            <p className="text-sm text-gray-500 mb-5">
                Scan your media library to identify images with and without EXIF metadata.
            </p>

            {/* Summary Stats */}
            <div className="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-4 mb-8">
                <div className="p-4 bg-gray-100 rounded">
                    <div className="text-xs text-gray-500 mb-1">Total Scanned</div>
                    <div className="text-3xl font-bold">{scanStatus.total}</div>
                </div>

                <div className="p-4 bg-green-100 rounded">
                    <div className="text-xs text-gray-500 mb-1">With EXIF</div>
                    <div className="text-3xl font-bold text-green-600">
                        {scanStatus.with_exif}
                    </div>
                </div>

                <div className="p-4 bg-red-100 rounded">
                    <div className="text-xs text-gray-500 mb-1">Without EXIF</div>
                    <div className="text-3xl font-bold text-red-600">
                        {scanStatus.without_exif}
                    </div>
                </div>
            </div>

            {/* Progress Bar — show during scan or after completion */}
            {(isScanning || scanComplete) && (
                <div className="mb-5">
                    <div className="mb-2">
                        <strong>{scanComplete ? "Scan Complete" : "Scanning Progress"}</strong>
                        <span className="ml-2.5 text-gray-500">
                            {scanStatus.processed} / {scanStatus.total}
                        </span>
                    </div>
                    <ProgressBar percent={progressPercent} />
                </div>
            )}

            {/* Last Scan Time */}
            {lastScanTime && (
                <div className="mb-5 p-3 bg-blue-50 rounded">
                    <small className="text-gray-500">
                        Last scan completed: <strong>{lastScanTime}</strong>
                    </small>
                </div>
            )}

            {/* Action Buttons */}
            <div className="flex gap-2">
                <button
                    onClick={startScan}
                    disabled={isScanning}
                    className={`px-4 py-2.5 text-white border-none rounded font-medium cursor-pointer ${
                        isScanning ? "bg-gray-400 cursor-not-allowed" : "bg-[#0073aa] hover:bg-[#005f8c]"
                    }`}
                >
                    {isScanning ? "Scanning..." : scanComplete ? "Re-Scan" : "Start Scan"}
                </button>

                {scanComplete && (
                    <button
                        onClick={handleClearScan}
                        disabled={isScanning}
                        className={`px-4 py-2.5 text-white border-none rounded font-medium cursor-pointer ${
                            isScanning ? "bg-gray-400 cursor-not-allowed" : "bg-red-600 hover:bg-red-700"
                        }`}
                    >
                        Clear Results
                    </button>
                )}
            </div>
        </div>
    );
}
