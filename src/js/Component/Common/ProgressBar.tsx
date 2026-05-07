interface ProgressBarProps {
    percent: number;
    /**
     * "active" — work is in progress; show a shimmer over the filled bar so it
     *            never looks frozen between server polls.
     * "queued" — work is starting but progress is still 0; show an indeterminate
     *            sliding gradient on the empty track to signal liveness.
     * "idle"   — static bar (default).
     */
    state?: 'idle' | 'active' | 'queued';
    /** Optional label rendered next to the percentage (e.g. "queued"). */
    label?: string;
}

export default function ProgressBar({ percent, state = 'idle', label }: ProgressBarProps) {
    const safePercent = Math.max(0, Math.min(100, percent));
    const isActive = state === 'active';
    const isQueued = state === 'queued';
    const isLive = isActive || isQueued;

    return (
        <div className="w-full">
            <div className="flex justify-between items-center mb-1">
                <span className="text-xs font-medium text-gray-600 inline-flex items-center gap-1.5">
                    {isLive && (
                        <span className="relative inline-flex w-2 h-2">
                            <span className="absolute inset-0 rounded-full bg-blue-500 opacity-75 animate-ping" />
                            <span className="relative inline-flex w-2 h-2 rounded-full bg-blue-600" />
                        </span>
                    )}
                    {safePercent}%
                    {label && <span className="text-gray-400 font-normal">· {label}</span>}
                </span>
            </div>
            <div className="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden relative">
                {/* Filled portion. Width transitions on real progress; a shimmer
                    overlay slides across it while the scan is active so the bar
                    feels alive between server polls. */}
                <div
                    className="bg-blue-600 h-2.5 rounded-full transition-all duration-300 relative overflow-hidden"
                    style={{ width: `${safePercent}%` }}
                >
                    {isActive && safePercent > 0 && (
                        <span
                            aria-hidden
                            className="absolute inset-0 mlt-progress-shimmer bg-gradient-to-r from-transparent via-white/40 to-transparent"
                        />
                    )}
                </div>

                {/* Indeterminate gradient — only rendered while queued (no real
                    progress yet). Sits on the empty track, slides left → right. */}
                {isQueued && safePercent === 0 && (
                    <span
                        aria-hidden
                        className="absolute top-0 h-2.5 mlt-progress-indeterminate rounded-full bg-gradient-to-r from-blue-300/0 via-blue-500/70 to-blue-300/0"
                        style={{ width: '40%' }}
                    />
                )}
            </div>
        </div>
    );
}
