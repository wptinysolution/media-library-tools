import { useCallback, useEffect, useRef } from 'react';
import { useStore } from '@/js/Utils/store';
import {
    compressionCancel,
    compressionGetProgress,
    compressionProcessBatch,
    conversionGetCapabilities,
    conversionStartLibrary,
    notifications,
} from '@/js/Utils/Data';
import type { CompressionProgress } from '@/js/Utils/Data';

/**
 * Delay between batch requests. Each request performs real work rather than
 * only reporting status, so the gap simply yields to the browser.
 */
const POLL_INTERVAL_MS = 500;

/** Job states that mean the server has stopped working. */
const TERMINAL_STATUSES = ['completed', 'partial', 'failed', 'cancelled'];

/**
 * Drives a conversion job.
 *
 * Job infrastructure is shared with compression — the same queue, batch driver,
 * cancel endpoint and progress shape — because the server distinguishes the two
 * by the job's `job_type`. Only starting a run differs, so this hook reuses the
 * compression endpoints for everything else rather than duplicating them.
 */
export function useConversionJob() {
    const { conversion, setConversion } = useStore();

    const pollTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const mountedRef = useRef(true);
    // Only one batch request outstanding at a time.
    const inFlight = useRef(false);
    // Bumped on stop/restart so a late response cannot revive a finished loop.
    const runId = useRef(0);

    const stopPolling = useCallback(() => {
        runId.current += 1;
        if (pollTimer.current) {
            clearTimeout(pollTimer.current);
            pollTimer.current = null;
        }
    }, []);

    /**
     * Store progress for a conversion run and report whether it is still going.
     *
     * The job queue is shared with compression, so a compression run surfaces
     * here too. Ignoring it stops this page showing someone else's progress or
     * driving batches on a job it does not own.
     */
    const applyProgress = useCallback((next: CompressionProgress) => {
        if ('conversion' !== next.job_type) {
            setConversion({ progress: null, isProcessing: false });
            return false;
        }

        const isRunning = 'running' === next.status;
        setConversion({ progress: next, isProcessing: isRunning });
        return isRunning;
    }, [setConversion]);

    const pollOnce = useCallback(async () => {
        if (inFlight.current) return;
        inFlight.current = true;

        const myRun = runId.current;
        const isStale = () => !mountedRef.current || myRun !== runId.current;

        try {
            const next = await compressionProcessBatch();
            if (isStale()) return;

            if (applyProgress(next)) {
                pollTimer.current = setTimeout(pollOnce, POLL_INTERVAL_MS);
            }
        } catch {
            // Transient failure — fall back to a read-only progress check so a
            // job the cron ticks are still advancing is not abandoned.
            if (isStale()) return;
            try {
                const next = await compressionGetProgress();
                if (!isStale() && applyProgress(next)) {
                    pollTimer.current = setTimeout(pollOnce, POLL_INTERVAL_MS);
                }
            } catch {
                if (!isStale()) {
                    pollTimer.current = setTimeout(pollOnce, POLL_INTERVAL_MS);
                }
            }
        } finally {
            inFlight.current = false;
        }
    }, [applyProgress]);

    /** Load server capabilities, settings and entitlements. */
    const loadCapabilities = useCallback(async () => {
        stopPolling();
        setConversion({ isLoading: true, error: '', progress: null, isProcessing: false });

        try {
            const payload = await conversionGetCapabilities();
            if (!mountedRef.current) return;

            setConversion({
                settings: payload.settings,
                capabilities: payload.capabilities,
                access: payload.access,
                available: payload.available,
                isLoading: false,
            });

            const progress = await compressionGetProgress();
            if (!mountedRef.current) return;

            // Only reattach to a run that is genuinely still going.
            if ('running' === progress.status && progress.remaining > 0 && 'conversion' === progress.job_type) {
                applyProgress(progress);
                pollTimer.current = setTimeout(pollOnce, POLL_INTERVAL_MS);
            } else {
                setConversion({ progress: null, isProcessing: false });
            }
        } catch {
            if (mountedRef.current) {
                setConversion({ isLoading: false, error: 'Could not load conversion settings.' });
            }
        }
    }, [applyProgress, pollOnce, setConversion, stopPolling]);

    /** Start a run over every not-yet-converted image in the library. */
    const startLibraryJob = useCallback(async (overrides: Record<string, unknown> = {}) => {
        stopPolling();
        setConversion({ isProcessing: true, error: '' });

        try {
            const next = await conversionStartLibrary(overrides);
            if (!mountedRef.current) return;

            if (next.limit_applied) {
                notifications(
                    false,
                    `Free version limit: only ${next.limit} images will be converted in this run. Run it again to continue, or upgrade to Pro for unlimited conversion.`
                );
            }

            if (applyProgress(next)) {
                pollTimer.current = setTimeout(pollOnce, POLL_INTERVAL_MS);
            }
        } catch (error) {
            if (!mountedRef.current) return;
            const message =
                (error as { response?: { data?: { message?: string } } })?.response?.data?.message
                || 'Conversion could not be started.';
            setConversion({ isProcessing: false, error: message });
            notifications(false, message);
        }
    }, [applyProgress, pollOnce, setConversion, stopPolling]);

    const cancelJob = useCallback(async () => {
        stopPolling();
        setConversion({ isProcessing: false });

        try {
            const next = await compressionCancel();
            if (mountedRef.current) {
                setConversion({ progress: next, isProcessing: false });
            }
        } catch {
            if (mountedRef.current) setConversion({ isProcessing: false });
        }
    }, [setConversion, stopPolling]);

    useEffect(() => {
        mountedRef.current = true;
        return () => {
            mountedRef.current = false;
            stopPolling();
        };
    }, [stopPolling]);

    const status = conversion.progress?.status ?? 'idle';

    return {
        conversion,
        setConversion,
        loadCapabilities,
        startLibraryJob,
        cancelJob,
        isFinished: TERMINAL_STATUSES.includes(status),
    };
}
