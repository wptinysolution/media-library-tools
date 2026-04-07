import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useStore } from '@/js/Utils/store';
import { regenerateBatch, regenerateGetStatus } from '@/js/Utils/Data';

// ─── Types ───────────────────────────────────────────────────────────────────

interface BatchError {
    id: number;
    file: string;
    error: string;
}

interface BatchSucceeded {
    id: number;
    file: string;
    deleted_sizes: string[];
}

interface BatchResponse {
    processed: number;
    total: number;
    complete: boolean;
    deleted_total: number;
    errors: BatchError[];
    succeeded: BatchSucceeded[];
}

type Status = 'idle' | 'running' | 'stopped' | 'done';

const BATCH_SIZE = 10;

// ─── Component ───────────────────────────────────────────────────────────────

function RegenerateInit() {
    const [total, setTotal]           = useState<number | null>(null);
    const [processed, setProcessed]   = useState(0);
    const [status, setStatus]         = useState<Status>('idle');
    const [errors, setErrors]         = useState<BatchError[]>([]);
    const [history, setHistory]       = useState<BatchSucceeded[]>([]);
    const [dismissedErrors, setDismissedErrors] = useState<Set<number>>(new Set());

    const navigate        = useNavigate();
    const { setGeneralData } = useStore();
    const stopRef         = useRef(false);

    // ── Load total on mount ─────────────────────────────────────────────────
    useEffect(() => {
        regenerateGetStatus().then(({ total: t }) => setTotal(t));
    }, []);

    // ── Helpers ─────────────────────────────────────────────────────────────

    const reset = () => {
        setProcessed(0);
        setErrors([]);
        setHistory([]);
        setDismissedErrors(new Set());
    };

    // ── Start / Stop ────────────────────────────────────────────────────────

    const handleStart = async () => {
        if (total === null) return;
        reset();
        stopRef.current = false;
        setStatus('running');

        let offset = 0;

        while (!stopRef.current) {
            let data: BatchResponse;
            try {
                const response = await regenerateBatch({ offset, batch_size: BATCH_SIZE });
                data = response.data as BatchResponse;
            } catch {
                setStatus('stopped');
                return;
            }

            offset = data.processed;

            if (data.errors.length)    setErrors(prev => [...prev, ...data.errors]);
            if (data.succeeded.length) setHistory(prev => [...prev, ...data.succeeded]);

            setProcessed(offset);

            if (data.complete || stopRef.current) break;
        }

        setStatus(stopRef.current ? 'stopped' : 'done');
    };

    const handleStop = () => { stopRef.current = true; };

    const dismissError = (id: number) => {
        setDismissedErrors(prev => new Set(prev).add(id));
    };

    // ── Derived values ───────────────────────────────────────────────────────

    const safeTotal     = total ?? 0;
    const percent       = safeTotal > 0 ? Math.min(100, Math.round((processed / safeTotal) * 100)) : 0;
    const visibleErrors = errors.filter(e => !dismissedErrors.has(e.id));
    const isRunning     = status === 'running';

    // ── Render ───────────────────────────────────────────────────────────────

    return (
        <div className="min-h-screen bg-gray-50">
            <div className="max-w-10xl mx-auto px-6 py-8">

                {/* Page header */}
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold text-gray-900 m-0! inline-flex items-center gap-2">
                        Regenerate Thumbnails
                    </h1>
                    <p className="text-sm text-gray-500 mt-1">
                        Regenerates all registered thumbnail sizes for every image, and automatically
                        deletes orphan files for any sizes that are no longer registered.
                        Processing happens in batches of {BATCH_SIZE} to avoid server timeouts.
                    </p>
                </div>

                {/* Control card */}
                <div className="bg-white rounded-lg border border-gray-200 p-6 mb-6">

                    {/* Stats row */}
                    <div className="flex flex-wrap items-center gap-6 mb-6">
                        <div className="text-center">
                            <p className="text-3xl font-bold text-gray-900">
                                {total === null ? '…' : total.toLocaleString()}
                            </p>
                            <p className="text-xs text-gray-500 mt-0.5">Total images</p>
                        </div>
                        <div className="text-center">
                            <p className="text-3xl font-bold text-blue-600">
                                {processed.toLocaleString()}
                            </p>
                            <p className="text-xs text-gray-500 mt-0.5">Processed</p>
                        </div>
                        <div className="text-center">
                            <p className="text-3xl font-bold text-green-600">
                                {history.length.toLocaleString()}
                            </p>
                            <p className="text-xs text-gray-500 mt-0.5">Succeeded</p>
                        </div>
                        <div className="text-center">
                            <p className="text-3xl font-bold text-red-500">
                                {errors.length.toLocaleString()}
                            </p>
                            <p className="text-xs text-gray-500 mt-0.5">Errors</p>
                        </div>
                    </div>

                    {/* Progress bar */}
                    {(isRunning || status === 'done' || status === 'stopped') && (
                        <div className="mb-5">
                            <div className="flex items-center justify-between text-xs text-gray-500 mb-1">
                                <span>{processed.toLocaleString()} of {safeTotal.toLocaleString()} images</span>
                                <span>{percent}%</span>
                            </div>
                            <div className="w-full bg-gray-100 rounded-full h-3 overflow-hidden">
                                <div
                                    className={`h-3 rounded-full transition-all duration-300 ${
                                        status === 'done'
                                            ? 'bg-green-500'
                                            : status === 'stopped'
                                            ? 'bg-amber-400'
                                            : 'bg-blue-500'
                                    }`}
                                    style={{ width: `${percent}%` }}
                                />
                            </div>
                        </div>
                    )}

                    {/* Action buttons */}
                    <div className="flex items-center gap-3">
                        {!isRunning ? (
                            <button
                                type="button"
                                disabled={total === null || total === 0}
                                onClick={handleStart}
                                className="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded-md transition-colors cursor-pointer"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                {status === 'idle' ? 'Start Regenerating' : 'Restart'}
                            </button>
                        ) : (
                            <button
                                type="button"
                                onClick={handleStop}
                                className="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-md transition-colors cursor-pointer"
                            >
                                <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <rect x="6" y="6" width="12" height="12" rx="1" />
                                </svg>
                                Stop
                            </button>
                        )}

                        {(status === 'stopped' || status === 'done') && (
                            <>
                                <button
                                    type="button"
                                    onClick={handleStart}
                                    className="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors cursor-pointer"
                                >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    Restart from Beginning
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setGeneralData({ isDirModalOpen: true, autoStartScan: false });
                                        navigate('/rubbishFile');
                                    }}
                                    className="inline-flex items-center gap-2 px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium rounded-md transition-colors cursor-pointer"
                                >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    Check Rubbish Files
                                </button>
                            </>
                        )}
                    </div>

                    {/* Status notices */}
                    {status === 'done' && (
                        <div className="mt-4 flex items-center gap-2 text-sm text-green-700 bg-green-50 border border-green-200 rounded-md px-4 py-3">
                            <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                            All {safeTotal.toLocaleString()} images processed.
                        </div>
                    )}
                    {status === 'stopped' && (
                        <div className="mt-4 flex items-center gap-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-4 py-3">
                            <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                            </svg>
                            Stopped at {processed.toLocaleString()} of {safeTotal.toLocaleString()}. Click "Restart" to process from the beginning.
                        </div>
                    )}
                    {isRunning && (
                        <div className="mt-4 flex items-center gap-2 text-sm text-blue-700 bg-blue-50 border border-blue-200 rounded-md px-4 py-3">
                            <svg className="w-4 h-4 shrink-0 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            Regenerating thumbnails and cleaning orphan sizes… do not close this page.
                        </div>
                    )}
                </div>

                {/* Errors panel */}
                {visibleErrors.length > 0 && (
                    <div className="bg-white rounded-lg border border-red-200 mb-6 overflow-hidden">
                        <div className="flex items-center justify-between px-4 py-3 border-b border-red-100 bg-red-50">
                            <span className="text-sm font-medium text-red-700">
                                {visibleErrors.length} error{visibleErrors.length !== 1 ? 's' : ''}
                            </span>
                            <button
                                type="button"
                                onClick={() => setDismissedErrors(new Set(errors.map(e => e.id)))}
                                className="text-xs text-red-500 hover:text-red-700 cursor-pointer"
                            >
                                Dismiss all
                            </button>
                        </div>
                        <ul className="divide-y divide-red-100 max-h-60 overflow-y-auto">
                            {visibleErrors.map(err => (
                                <li key={err.id} className="flex items-start justify-between gap-4 px-4 py-2.5 hover:bg-red-50">
                                    <div className="min-w-0">
                                        <p className="text-xs font-medium text-gray-800 truncate">{err.file}</p>
                                        <p className="text-xs text-red-600">{err.error}</p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => dismissError(err.id)}
                                        className="shrink-0 text-gray-400 hover:text-gray-600 cursor-pointer"
                                        title="Dismiss"
                                    >
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {/* History list */}
                {history.length > 0 && (
                    <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                        <div className="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50">
                            <span className="text-sm font-medium text-gray-700">
                                Regenerated ({history.length.toLocaleString()})
                            </span>
                            {isRunning && (
                                <span className="inline-flex items-center gap-1 text-xs text-blue-600">
                                    <svg className="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                    </svg>
                                    Live
                                </span>
                            )}
                        </div>
                        <ul className="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                            {[...history].reverse().map(item => (
                                <li key={item.id} className="px-4 py-2.5 hover:bg-gray-50">
                                    <div className="flex items-center gap-3">
                                        <svg className="w-4 h-4 shrink-0 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span className="text-xs text-gray-700 font-mono truncate">{item.file}</span>
                                        <span className="ml-auto text-xs text-gray-400 shrink-0">ID: {item.id}</span>
                                    </div>
                                    {item.deleted_sizes.length > 0 && (
                                        <div className="flex flex-wrap gap-1 mt-1.5 ml-7">
                                            <span className="text-[10px] text-gray-400 mr-0.5">orphans removed:</span>
                                            {item.deleted_sizes.map(s => (
                                                <span key={s} className="inline-block px-1.5 py-0.5 text-[10px] font-medium bg-orange-100 text-orange-700 rounded">
                                                    {s}
                                                </span>
                                            ))}
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

            </div>
        </div>
    );
}

export default RegenerateInit;
