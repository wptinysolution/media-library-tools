import { useCallback, useEffect, useRef } from 'react';
import { useStore } from '@/js/Utils/store';
import {
    compressionCancel,
    compressionGetProgress,
    compressionGetSettings,
    compressionRetry,
    compressionStart,
    notifications,
} from '@/js/Utils/Data';
import type { CompressionProgress } from '@/js/Utils/Data';

/**
 * Poll interval while a job is running. Batches are processed server-side by
 * WP-Cron, so this only controls how often the UI refreshes — not how fast
 * images are compressed.
 */
const POLL_INTERVAL_MS = 2000;

/** Job states that mean the server has stopped working. */
const TERMINAL_STATUSES = ['completed', 'partial', 'failed', 'cancelled', 'idle'];

/**
 * Drives a compression job: loads settings, starts the run and polls progress
 * until the server reports a terminal status.
 *
 * Progress lives in the `tsmlt_compression_job` option server-side, so a job
 * survives a closed tab — remounting simply resumes polling the same run.
 */
export function useCompressionJob() {
    const { compression, setCompression } = useStore();

    const pollTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const mountedRef = useRef(true);
    // Guards against overlapping polls: only one request per job at a time.
    const inFlight = useRef(false);

    const stopPolling = useCallback(() => {
        if (pollTimer.current) {
            clearTimeout(pollTimer.current);
            pollTimer.current = null;
        }
    }, []);

    const applyProgress = useCallback((next: CompressionProgress) => {
        const isRunning = 'running' === next.status;
        setCompression({ progress: next, isProcessing: isRunning });
        return isRunning;
    }, [setCompression]);

    const pollOnce = useCallback(async () => {
        if (inFlight.current) return;
        inFlight.current = true;

        try {
            const next = await compressionGetProgress();
            if (!mountedRef.current) return;

            if (applyProgress(next)) {
                pollTimer.current = setTimeout(pollOnce, POLL_INTERVAL_MS);
            }
        } catch {
            // Transient failure (nonce refresh, brief network blip) — keep
            // polling rather than abandoning a job that is still running.
            if (mountedRef.current) {
                pollTimer.current = setTimeout(pollOnce, POLL_INTERVAL_MS);
            }
        } finally {
            inFlight.current = false;
        }
    }, [applyProgress]);

    /** Load settings, entitlements and any job already in flight. */
    const loadSettings = useCallback(async () => {
        setCompression({ isLoading: true, error: '' });
        try {
            const payload = await compressionGetSettings();
            if (!mountedRef.current) return;

            setCompression({
                settings: payload.settings,
                access: payload.access,
                modes: payload.modes,
                engines: payload.engines,
                isLoading: false,
            });

            const progress = await compressionGetProgress();
            if (!mountedRef.current) return;

            // Resume a run that is still going from a previous session.
            if (applyProgress(progress)) {
                pollTimer.current = setTimeout(pollOnce, POLL_INTERVAL_MS);
            }
        } catch {
            if (mountedRef.current) {
                setCompression({
                    isLoading: false,
                    error: 'Could not load compression settings.',
                });
            }
        }
    }, [applyProgress, pollOnce, setCompression]);

    const startJob = useCallback(async (ids: number[], overrides: Record<string, unknown> = {}) => {
        stopPolling();
        setCompression({ isProcessing: true, error: '' });

        try {
            const next = await compressionStart({ ids, ...overrides });
            if (!mountedRef.current) return;

            if (next.limit_applied) {
                notifications(
                    false,
                    `Free version limit: only the first ${next.limit} images will be compressed. Upgrade to Pro for unlimited compression.`
                );
            }

            if (applyProgress(next)) {
                pollTimer.current = setTimeout(pollOnce, POLL_INTERVAL_MS);
            }
        } catch (error) {
            if (!mountedRef.current) return;
            const message =
                (error as { response?: { data?: { message?: string } } })?.response?.data?.message
                || 'Compression could not be started.';
            setCompression({ isProcessing: false, error: message });
            notifications(false, message);
        }
    }, [applyProgress, pollOnce, setCompression, stopPolling]);

    const cancelJob = useCallback(async () => {
        stopPolling();
        try {
            const next = await compressionCancel();
            if (mountedRef.current) applyProgress(next);
        } catch {
            if (mountedRef.current) setCompression({ isProcessing: false });
        }
    }, [applyProgress, setCompression, stopPolling]);

    const retryJob = useCallback(async () => {
        stopPolling();
        setCompression({ isProcessing: true, error: '' });
        try {
            const next = await compressionRetry();
            if (!mountedRef.current) return;
            if (applyProgress(next)) {
                pollTimer.current = setTimeout(pollOnce, POLL_INTERVAL_MS);
            }
        } catch (error) {
            if (!mountedRef.current) return;
            const message =
                (error as { response?: { data?: { message?: string } } })?.response?.data?.message
                || 'Retry could not be started.';
            setCompression({ isProcessing: false, error: message });
            notifications(false, message);
        }
    }, [applyProgress, pollOnce, setCompression, stopPolling]);

    useEffect(() => {
        mountedRef.current = true;
        return () => {
            mountedRef.current = false;
            stopPolling();
        };
    }, [stopPolling]);

    const status = compression.progress?.status ?? 'idle';

    return {
        compression,
        setCompression,
        loadSettings,
        startJob,
        cancelJob,
        retryJob,
        isFinished: TERMINAL_STATUSES.includes(status) && 'idle' !== status,
    };
}
