import { useCallback, useEffect, useRef, useState } from 'react';
import {
    bulkAiCancel,
    bulkAiGetProgress,
    bulkAiGetResults,
    bulkAiProcessBatch,
    bulkAiRetry,
    bulkAiStart,
    notifications,
} from '@/js/Utils/Data';
import type { BulkAiField, BulkAiProgress, BulkAiResults } from '@/js/Utils/Data';

/**
 * Gap between batch requests. Each request performs real work rather than only
 * reporting status, so the delay just yields to the browser between runs.
 */
const POLL_INTERVAL_MS = 600;

/** Statuses meaning the server has stopped working on the job. */
const TERMINAL_STATUSES = ['completed', 'partial', 'failed', 'cancelled', 'idle'];

/**
 * Drives a bulk AI job: starts a run, keeps the queue moving, and loads the
 * collected suggestions once the server reports a terminal status.
 *
 * The job also ticks server-side via WP-Cron, so closing the tab does not stop
 * it. This loop exists so an open tab still makes progress on installs where
 * WP-Cron never fires, and so the UI has live progress to show.
 */
export function useBulkAiJob() {
    const [progress, setProgress] = useState<BulkAiProgress | null>(null);
    const [results, setResults] = useState<BulkAiResults>({});
    const [loadingResults, setLoadingResults] = useState(false);

    const pollTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const mountedRef = useRef(true);
    // Only one batch request per job at a time.
    const inFlight = useRef(false);
    // Bumped whenever the loop is torn down, so a late response from a batch
    // that was already running when the user pressed Stop cannot reschedule it.
    const runId = useRef(0);

    const stopPolling = useCallback(() => {
        runId.current += 1;
        if (pollTimer.current) {
            clearTimeout(pollTimer.current);
            pollTimer.current = null;
        }
    }, []);

    const loadResults = useCallback(async () => {
        setLoadingResults(true);
        try {
            const payload = await bulkAiGetResults();
            if (mountedRef.current) {
                setResults(payload.results || {});
            }
        } catch {
            notifications(false, 'Could not load the AI suggestions.');
        } finally {
            if (mountedRef.current) {
                setLoadingResults(false);
            }
        }
    }, []);

    /** Store progress and report whether the job is still running. */
    const applyProgress = useCallback((next: BulkAiProgress): boolean => {
        if (mountedRef.current) {
            setProgress(next);
        }
        return !TERMINAL_STATUSES.includes(next.status);
    }, []);

    const scheduleNext = useCallback((ownRunId: number, tick: () => void) => {
        if (!mountedRef.current || ownRunId !== runId.current) {
            return;
        }
        pollTimer.current = setTimeout(tick, POLL_INTERVAL_MS);
    }, []);

    /** Process one batch, then queue the next until the job finishes. */
    const drive = useCallback((ownRunId: number) => {
        const tick = async () => {
            if (!mountedRef.current || ownRunId !== runId.current || inFlight.current) {
                return;
            }
            inFlight.current = true;
            try {
                const next = await bulkAiProcessBatch();
                if (ownRunId !== runId.current) {
                    return;
                }
                const stillRunning = applyProgress(next);
                if (stillRunning) {
                    scheduleNext(ownRunId, tick);
                } else {
                    await loadResults();
                }
            } catch {
                // A failed batch request is not a failed job — the cron tick may
                // still be advancing it. Fall back to polling progress only.
                if (ownRunId === runId.current) {
                    try {
                        const status = await bulkAiGetProgress();
                        if (applyProgress(status)) {
                            scheduleNext(ownRunId, tick);
                        } else {
                            await loadResults();
                        }
                    } catch {
                        notifications(false, 'Lost contact with the AI job.');
                    }
                }
            } finally {
                inFlight.current = false;
            }
        };
        tick();
    }, [applyProgress, loadResults, scheduleNext]);

    const start = useCallback(async (ids: number[], fields: BulkAiField[], mode: 'missing' | 'overwrite') => {
        stopPolling();
        setResults({});
        try {
            const next = await bulkAiStart({ ids, fields, mode });
            applyProgress(next);
            if (!TERMINAL_STATUSES.includes(next.status)) {
                drive(runId.current);
            }
            return true;
        } catch (error) {
            const message = (error as { response?: { data?: { data?: { message?: string } } } })
                ?.response?.data?.data?.message;
            notifications(false, message || 'The AI job could not be started.');
            return false;
        }
    }, [applyProgress, drive, stopPolling]);

    const cancel = useCallback(async () => {
        stopPolling();
        try {
            const next = await bulkAiCancel();
            applyProgress(next);
            await loadResults();
        } catch {
            notifications(false, 'The job could not be stopped.');
        }
    }, [applyProgress, loadResults, stopPolling]);

    const retry = useCallback(async () => {
        stopPolling();
        try {
            const next = await bulkAiRetry();
            applyProgress(next);
            if (!TERMINAL_STATUSES.includes(next.status)) {
                drive(runId.current);
            }
        } catch {
            notifications(false, 'The failed items could not be retried.');
        }
    }, [applyProgress, drive, stopPolling]);

    // Reattach to a job already running server-side — e.g. the user closed the
    // tab mid-run and came back.
    useEffect(() => {
        mountedRef.current = true;

        const reattach = async () => {
            try {
                const status = await bulkAiGetProgress();
                if (!mountedRef.current) {
                    return;
                }
                setProgress(status);
                if (!TERMINAL_STATUSES.includes(status.status)) {
                    drive(runId.current);
                } else if (status.result_count > 0) {
                    await loadResults();
                }
            } catch {
                // No job, or not reachable — nothing to attach to.
            }
        };

        reattach();

        return () => {
            mountedRef.current = false;
            stopPolling();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return { progress, results, loadingResults, start, cancel, retry, setResults };
}
