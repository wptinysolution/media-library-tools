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
            <h3 style={{ marginTop: "0", marginBottom: "12px" }}>Scanner</h3>
            <p style={{ color: "#666", marginBottom: "20px", fontSize: "14px" }}>
                Scan your media library to identify images with and without EXIF metadata.
            </p>

            {/* Summary Stats */}
            <div
                style={{
                    display: "grid",
                    gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))",
                    gap: "16px",
                    marginBottom: "30px",
                }}
            >
                <div style={{ padding: "16px", backgroundColor: "#f5f5f5", borderRadius: "4px" }}>
                    <div style={{ fontSize: "12px", color: "#666", marginBottom: "4px" }}>Total Scanned</div>
                    <div style={{ fontSize: "28px", fontWeight: "bold" }}>{scanStatus.total}</div>
                </div>

                <div style={{ padding: "16px", backgroundColor: "#d4edda", borderRadius: "4px" }}>
                    <div style={{ fontSize: "12px", color: "#666", marginBottom: "4px" }}>With EXIF</div>
                    <div style={{ fontSize: "28px", fontWeight: "bold", color: "#28a745" }}>
                        {scanStatus.with_exif}
                    </div>
                </div>

                <div style={{ padding: "16px", backgroundColor: "#f8d7da", borderRadius: "4px" }}>
                    <div style={{ fontSize: "12px", color: "#666", marginBottom: "4px" }}>Without EXIF</div>
                    <div style={{ fontSize: "28px", fontWeight: "bold", color: "#dc3545" }}>
                        {scanStatus.without_exif}
                    </div>
                </div>
            </div>

            {/* Progress Bar — show during scan or after completion */}
            {(isScanning || scanComplete) && (
                <div style={{ marginBottom: "20px" }}>
                    <div style={{ marginBottom: "8px" }}>
                        <strong>{scanComplete ? "Scan Complete" : "Scanning Progress"}</strong>
                        <span style={{ marginLeft: "10px", color: "#666" }}>
                            {scanStatus.processed} / {scanStatus.total}
                        </span>
                    </div>
                    <ProgressBar percent={progressPercent} />
                </div>
            )}

            {/* Last Scan Time */}
            {lastScanTime && (
                <div style={{ marginBottom: "20px", padding: "12px", backgroundColor: "#e7f3ff", borderRadius: "4px" }}>
                    <small style={{ color: "#666" }}>
                        Last scan completed: <strong>{lastScanTime}</strong>
                    </small>
                </div>
            )}

            {/* Action Buttons */}
            <div style={{ display: "flex", gap: "8px" }}>
                <button
                    onClick={startScan}
                    disabled={isScanning}
                    style={{
                        padding: "10px 16px",
                        backgroundColor: isScanning ? "#ccc" : "#0073aa",
                        color: "white",
                        border: "none",
                        borderRadius: "4px",
                        cursor: isScanning ? "not-allowed" : "pointer",
                        fontWeight: "500",
                    }}
                >
                    {isScanning ? "Scanning..." : scanComplete ? "Re-Scan" : "Start Scan"}
                </button>

                {scanComplete && (
                    <button
                        onClick={handleClearScan}
                        disabled={isScanning}
                        style={{
                            padding: "10px 16px",
                            backgroundColor: "#dc3545",
                            color: "white",
                            border: "none",
                            borderRadius: "4px",
                            cursor: isScanning ? "not-allowed" : "pointer",
                            fontWeight: "500",
                        }}
                    >
                        Clear Results
                    </button>
                )}
            </div>
        </div>
    );
}
