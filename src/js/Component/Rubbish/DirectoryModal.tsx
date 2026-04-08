import { useState, useEffect, useRef, useCallback } from "react";
import { useStore } from "@/js/Utils/store";
import { rescanDir, truncateUnlistedFile } from "@/js/Utils/Data";
import Axios from 'axios';
import Modal from "@/js/Component/Common/Modal";
import { trimPath } from "@/js/Component/Rubbish/DirectoryList";

const MAX_RETRIES = 3;

interface DirStatusItem {
    total_items: number;
    counted: number;
    scanned?: boolean;
}

type ScanPhase = 'idle' | 'rescan' | 'scanning' | 'done' | 'stopped';

function DirectoryModal() {
    const { generalData, setGeneralData } = useStore();

    // ── Directory list state ─────────────────────────────────────────────────
    const [dirList, setDirList]         = useState<Record<string, DirStatusItem>>({});

    // ── Scan state ───────────────────────────────────────────────────────────
    const [phase, setPhase]             = useState<ScanPhase>('idle');
    const [scannedDirs, setScannedDirs] = useState(0);
    const [totalDirs, setTotalDirs]     = useState(0);
    const [currentDir, setCurrentDir]   = useState('');
    const [instantDeletion, setInstantDeletion] = useState('not-instant');
    const [skip] = useState<string[]>([]);

    const stopRef    = useRef(false);
    const retryCount = useRef(0);
    const instantRef = useRef(instantDeletion);
    const skipRef    = useRef(skip);

    useEffect(() => { instantRef.current = instantDeletion; }, [instantDeletion]);
    useEffect(() => { skipRef.current = skip; }, [skip]);

    // Sync dir list from store when modal opens (normal open without autoStart)
    useEffect(() => {
        if (!generalData.isDirModalOpen) return;
        const stored = (generalData.scanRubbishDirList ?? {}) as Record<string, DirStatusItem>;
        setDirList(stored);
        setTotalDirs(Object.keys(stored).length);
    }, [generalData.isDirModalOpen, generalData.scanRubbishDirList]);

    const close = () => setGeneralData({ isDirModalOpen: false, autoStartScan: false });

    // ── Fire one AJAX batch ───────────────────────────────────────────────────
    const fireBatch = (): Promise<{ remaining: string[]; statusList: Record<string, DirStatusItem> }> => {
        return new Promise((resolve, reject) => {
            const params = new URLSearchParams();
            params.append('action', 'immediately_search_rubbish_file');
            params.append('nonce', tsmltParams.tsmlt_wpnonce);
            params.append('instantDeletion', instantRef.current);
            skipRef.current.forEach(v => params.append('skip[]', v));

            Axios.post(tsmltParams.ajaxUrl, params)
                .then(res => {
                    retryCount.current = 0;
                    const data      = res.data?.data ?? {};
                    const remaining = Object.keys(data.dirList ?? {});
                    resolve({ remaining, statusList: data.dirStatusList ?? {} });
                })
                .catch(err => {
                    if (retryCount.current < MAX_RETRIES) {
                        retryCount.current++;
                        setTimeout(() => fireBatch().then(resolve).catch(reject), 2000);
                    } else {
                        retryCount.current = 0;
                        reject(err);
                    }
                });
        });
    };

    // ── Run the rubbish file scan against a given dir list ───────────────────
    const runScan = useCallback(async (dirs: Record<string, DirStatusItem>) => {
        stopRef.current = false;
        setPhase('scanning');
        setScannedDirs(0);
        setCurrentDir('');

        const allDirs = Object.keys(dirs).filter(k => !skipRef.current.includes(k));
        const total   = allDirs.length;
        setTotalDirs(total);

        let remaining = [...allDirs];

        while (remaining.length > 0 && !stopRef.current) {
            setCurrentDir(remaining[remaining.length - 1] ?? '');

            let result: { remaining: string[]; statusList: Record<string, DirStatusItem> };
            try {
                result = await fireBatch();
            } catch {
                setPhase('stopped');
                return;
            }

            if (stopRef.current) break;

            setDirList(result.statusList);
            setScannedDirs(total - result.remaining.length);
            remaining = result.remaining;
        }

        setCurrentDir('');
        setPhase(stopRef.current ? 'stopped' : 'done');
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    // ── Full flow: truncate → rebuild dir list → scan ────────────────────────
    const runFullFlow = useCallback(async () => {
        stopRef.current = false;
        setPhase('rescan');
        setScannedDirs(0);
        setCurrentDir('');
        setDirList({});

        let newList: Record<string, DirStatusItem> = {};
        try {
            await truncateUnlistedFile();
            const res = await rescanDir({ dir: 'all' }) as { data: { thedirlist: Record<string, DirStatusItem> } };
            newList = res.data.thedirlist ?? {};
        } catch {
            setPhase('stopped');
            return;
        }

        setDirList(newList);
        setTotalDirs(Object.keys(newList).length);
        setGeneralData({ scanRubbishDirList: newList, autoStartScan: false });

        if (stopRef.current) {
            setPhase('stopped');
            return;
        }

        await runScan(newList);
    }, [runScan, setGeneralData]);

    // ── Auto-start when opened with autoStartScan flag ───────────────────────
    useEffect(() => {
        if (generalData.isDirModalOpen && generalData.autoStartScan) {
            runFullFlow();
        }
    }, [generalData.isDirModalOpen, generalData.autoStartScan, runFullFlow]);

    const handleStop = () => { stopRef.current = true; };

    // ── Derived ───────────────────────────────────────────────────────────────
    const isScanning  = phase === 'scanning';
    const isRescan    = phase === 'rescan';
    const isBusy      = isScanning || isRescan;
    const percent     = totalDirs > 0 ? Math.min(100, Math.round((scannedDirs / totalDirs) * 100)) : 0;
    const dirEntries  = Object.entries(dirList) as [string, DirStatusItem][];

    const fullyScanned = (item: DirStatusItem) =>
        (item.total_items > 0 && item.counted >= item.total_items) ||
        (item.total_items === 0 && !!item.scanned);

    const doneCount    = dirEntries.filter(([, v]) => fullyScanned(v)).length;
    const pendingCount = dirEntries.length - doneCount;

    // ── Render ────────────────────────────────────────────────────────────────
    return (
        <Modal
            isOpen={generalData.isDirModalOpen}
            onClose={close}
            title="Scan Directories for Rubbish Files"
            maxWidth="max-w-[820px]"
            footer={
                <div className="px-6 py-4 border-t border-gray-200 flex items-center justify-between gap-3">
                    {/* Instant deletion toggle — pro only */}
                    <div>
                        {tsmltParams.hasExtended && (
                            <label className="inline-flex items-center gap-2 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    className="w-4 h-4 rounded border-gray-300 text-red-600 focus:ring-red-500 cursor-pointer"
                                    disabled={isBusy}
                                    onChange={e => setInstantDeletion(e.target.checked ? 'instant' : 'not-instant')}
                                />
                                <span className="text-sm text-gray-700">Instant delete rubbish file during scan</span>
                                <span className="text-xs text-red-500">(irreversible)</span>
                            </label>
                        )}
                    </div>

                    {/* Action buttons */}
                    <div className="flex items-center gap-2">
                        {!isBusy ? (
                            <button
                                type="button"
                                onClick={runFullFlow}
                                className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                {phase === 'idle' ? 'Start Scanning' : 'Restart Scan'}
                            </button>
                        ) : (
                            <button
                                type="button"
                                onClick={handleStop}
                                className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md transition-colors cursor-pointer"
                            >
                                <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <rect x="6" y="6" width="12" height="12" rx="1" />
                                </svg>
                                Stop
                            </button>
                        )}
                    </div>
                </div>
            }
        >
            <div className="flex flex-col">

                {/* ── Progress banner ── */}
                {phase !== 'idle' && (
                    <div className={`px-6 py-4 border-b ${
                        phase === 'done'    ? 'bg-green-50 border-green-100' :
                        phase === 'stopped' ? 'bg-amber-50 border-amber-100' :
                                             'bg-blue-50 border-blue-100'
                    }`}>
                        <div className="flex items-center gap-2 mb-2.5">
                            {isBusy && (
                                <svg className="w-4 h-4 animate-spin text-blue-600 shrink-0" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                            )}
                            {phase === 'done' && (
                                <svg className="w-4 h-4 text-green-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                            )}
                            {phase === 'stopped' && (
                                <svg className="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            )}
                            <span className={`text-sm font-semibold ${
                                phase === 'done'    ? 'text-green-700' :
                                phase === 'stopped' ? 'text-amber-700' :
                                                     'text-blue-700'
                            }`}>
                                {isRescan    && 'Rebuilding directory list…'}
                                {isScanning  && 'Scanning — do not close this page'}
                                {phase === 'done'    && `Complete — all ${totalDirs} director${totalDirs === 1 ? 'y' : 'ies'} scanned`}
                                {phase === 'stopped' && `Stopped at ${scannedDirs} of ${totalDirs} directories`}
                            </span>
                        </div>

                        {/* Progress bar — only shown during file scan phase */}
                        {(isScanning || phase === 'done' || phase === 'stopped') && (
                            <div className="flex items-center gap-3">
                                <div className="flex-1 bg-white rounded-full h-2 overflow-hidden border border-gray-200">
                                    <div
                                        className={`h-2 rounded-full transition-all duration-300 ${
                                            phase === 'done'    ? 'bg-green-500' :
                                            phase === 'stopped' ? 'bg-amber-400' :
                                                                 'bg-blue-500'
                                        }`}
                                        style={{ width: `${percent}%` }}
                                    />
                                </div>
                                <span className="text-xs font-medium text-gray-500 whitespace-nowrap">
                                    {scannedDirs} / {totalDirs} ({percent}%)
                                </span>
                            </div>
                        )}

                        {isScanning && currentDir && (
                            <p className="text-xs text-blue-600 mt-1.5 truncate">
                                <span className="text-gray-400">Current:</span>{' '}
                                <span className="font-medium">{trimPath(currentDir)}</span>
                            </p>
                        )}
                    </div>
                )}

                {/* ── Stats strip ── */}
                {dirEntries.length > 0 && (
                    <div className="flex items-center gap-5 px-6 py-2.5 bg-gray-50 border-b border-gray-100">
                        <span className="flex items-center gap-1.5 text-xs text-gray-500">
                            <span className="w-2 h-2 rounded-full bg-gray-300 inline-block" />
                            {dirEntries.length} total
                        </span>
                        <span className="flex items-center gap-1.5 text-xs text-gray-500">
                            <span className="w-2 h-2 rounded-full bg-green-500 inline-block" />
                            {doneCount} done
                        </span>
                        <span className="flex items-center gap-1.5 text-xs text-gray-500">
                            <span className="w-2 h-2 rounded-full bg-amber-400 inline-block" />
                            {pendingCount} pending
                        </span>
                    </div>
                )}

                {/* ── Directory table ── */}
                <div className="h-[340px] overflow-y-auto">
                    {isRescan ? (
                        <div className="flex items-center justify-center h-full gap-2 text-gray-400 text-sm">
                            <svg className="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            Rebuilding directory list…
                        </div>
                    ) : dirEntries.length === 0 ? (
                        <div className="flex flex-col items-center justify-center h-full gap-3 text-gray-400 px-8 text-center">
                            <svg className="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3 7a2 2 0 012-2h4l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" />
                            </svg>
                            <p className="text-sm">No directories found.<br />Click <strong className="text-gray-600">Start Scanning</strong> to begin.</p>
                        </div>
                    ) : (
                        <table className="w-full text-sm border-collapse">
                            <thead className="sticky top-0 bg-white border-b border-gray-100 z-10">
                                <tr>
                                    <th className="text-left px-6 py-2.5 text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Directory</th>
                                    <th className="text-right px-6 py-2.5 text-[11px] font-semibold text-gray-400 uppercase tracking-wide w-80">Files</th>
                                    <th className="text-center px-4 py-2.5 text-[11px] font-semibold text-gray-400 uppercase tracking-wide w-24">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {dirEntries.map(([key, item]) => {
                                    const done   = fullyScanned(item);
                                    const active = isScanning && currentDir === key;
                                    return (
                                        <tr
                                            key={key}
                                            className={`border-b border-gray-50 last:border-0 transition-colors ${
                                                active ? 'bg-blue-50' : 'hover:bg-gray-50'
                                            }`}
                                        >
                                            <td className="px-6 py-2.5">
                                                <div className="flex items-center gap-2 min-w-0">
                                                    {active ? (
                                                        <svg className="w-3.5 h-3.5 animate-spin text-blue-500 shrink-0" fill="none" viewBox="0 0 24 24">
                                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                        </svg>
                                                    ) : (
                                                        <svg className="w-3.5 h-3.5 shrink-0 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                                            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                                                        </svg>
                                                    )}
                                                    <span className="text-xs font-mono text-gray-600 truncate" title={key}>
                                                        {trimPath(key)}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-6 py-2.5 text-right text-xs text-gray-500 tabular-nums">
                                                {item.total_items > 0
                                                    ? `${item.counted} / ${item.total_items}`
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-2.5 text-center">
                                                {done ? (
                                                    <span className="inline-flex items-center gap-1 text-[10px] font-medium text-green-700 bg-green-100 px-2 py-0.5 rounded-full">
                                                        <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        Done
                                                    </span>
                                                ) : active ? (
                                                    <span className="inline-flex items-center gap-1 text-[10px] font-medium text-blue-700 bg-blue-100 px-2 py-0.5 rounded-full">
                                                        Scanning…
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 text-[10px] font-medium text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">
                                                        Pending
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </Modal>
    );
}

export default DirectoryModal;
