import { useCallback, useEffect, useRef } from 'react';
import { useStore } from '@/js/Utils/store';
import {
    compressionCancel,
    compressionGetProgress,
    compressionGetSettings,
    compressionProcessBatch,
    compressionRetry,
    compressionStart,
    compressionStartLibrary,
    notifications,
} from '@/js/Utils/Data';
import type { CompressionProgress } from '@/js/Utils/Data';

/**
 * Delay between batch requests. Kept short because each request performs real
 * work rather than only reporting status; the gap just yields to the browser
 * and avoids hammering the server back-to-back.
 */
const POLL_INTERVAL_MS = 500;

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
    // Incremented whenever the loop is torn down (Stop, or a new run starting).
    // A batch request is usually already in flight when the user clicks Stop;
    // without this its late response would report "running" and reschedule the
    // loop, making Stop look like it did nothing.
    const runId = useRef(0);

    const stopPolling = useCallback(() => {
        runId.current += 1;
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

    /**
     * Drive one batch, then schedule the next.
     *
     * The modal actively processes batches rather than only watching progress:
     * relying on WP-Cron alone stalls at 0% whenever `DISABLE_WP_CRON` is set,
     * or when no other request reaches the site while the user waits. The
     * `inFlight` guard keeps exactly one batch request outstanding at a time.
     */
    const pollOnce = useCallback(async () => {
        if (inFlight.current) return;
        inFlight.current = true;

        // Snapshot the generation this request belongs to. If Stop (or a new
        // run) bumps it while we are awaiting, this response is stale and must
        // neither update the UI nor schedule another batch.
        const myRun = runId.current;
        const isStale = () => !mountedRef.current || myRun !== runId.current;

        try {
            const next = await compressionProcessBatch();
            if (isStale()) return;

            if (applyProgress(next)) {
                pollTimer.current = setTimeout(pollOnce, POLL_INTERVAL_MS);
            }
        } catch {
            // Transient failure (nonce refresh, brief network blip). Fall back to
            // a read-only progress check so a job that the cron ticks are still
            // advancing is not abandoned by the UI.
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

    /** Load settings, entitlements and any job already in flight. */
    const loadSettings = useCallback(async () => {
        // The store is global and outlives this page, so a previous run's
        // progress is still in it on remount. Clear it before the awaits below
        // resolve, otherwise the page paints the old run — and a stale
        // "running" would make it look like compression restarted by itself.
        stopPolling();
        setCompression({ isLoading: true, error: '', progress: null, isProcessing: false });
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

            // Only reattach to a run that is genuinely still going: status
            // "running" *and* work left to do. Resuming on status alone meant a
            // stopped or drained job restarted itself when the page reopened.
            if ('running' === progress.status && progress.remaining > 0) {
                applyProgress(progress);
                pollTimer.current = setTimeout(pollOnce, POLL_INTERVAL_MS);
            } else {
                // A finished job from an earlier selection must not be shown as
                // if it were this one — otherwise opening the modal for a new
                // page of images displays the previous run's results and hides
                // the start button. Clear it so the settings picker is shown.
                setCompression({ progress: null, isProcessing: false });
            }
        } catch {
            if (mountedRef.current) {
                setCompression({
                    isLoading: false,
                    error: 'Could not load compression settings.',
                });
            }
        }
    }, [applyProgress, pollOnce, setCompression, stopPolling]);

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

    /** Start a run covering every not-yet-compressed image in the library. */
    const startLibraryJob = useCallback(async (overrides: Record<string, unknown> = {}) => {
        stopPolling();
        setCompression({ isProcessing: true, error: '' });

        try {
            const next = await compressionStartLibrary(overrides);
            if (!mountedRef.current) return;

            if (next.limit_applied) {
                notifications(
                    false,
                    `Free version limit: only ${next.limit} images will be compressed in this run. Run it again to continue, or upgrade to Pro for unlimited compression.`
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

        // Reflect the stop straight away. An in-flight batch can still be
        // finishing server-side, so waiting for the round-trip would leave the
        // button looking unresponsive for a second or more.
        setCompression({ isProcessing: false });

        try {
            const next = await compressionCancel();
            if (mountedRef.current) {
                // Keep the final counts, but never let a stale "running" status
                // from a batch that landed first restart the UI.
                setCompression({ progress: next, isProcessing: false });
            }
        } catch {
            if (mountedRef.current) setCompression({ isProcessing: false });
        }
    }, [setCompression, stopPolling]);

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
        startLibraryJob,
        cancelJob,
        retryJob,
        isFinished: TERMINAL_STATUSES.includes(status) && 'idle' !== status,
    };
}
