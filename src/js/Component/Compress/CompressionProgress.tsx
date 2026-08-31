import ProgressBar from '@/js/Component/Common/ProgressBar';
import type { CompressionProgress as Progress } from '@/js/Utils/Data';

interface CompressionProgressProps {
    progress: Progress;
}

/**
 * Live progress for a running compression job.
 *
 * Batches run server-side, so the counters here reflect work that continues
 * even if the browser is closed.
 */
export default function CompressionProgress({ progress }: CompressionProgressProps) {
    const isRunning = 'running' === progress.status;

    return (
        <div className="space-y-4">
            <ProgressBar
                percent={progress.percent}
                state={isRunning ? (progress.processed > 0 ? 'active' : 'queued') : 'idle'}
                label={isRunning ? `${progress.processed} of ${progress.total}` : undefined}
            />

            <div className="grid grid-cols-4 gap-2 text-center">
                <div className="bg-gray-50 rounded-md py-2">
                    <div className="text-lg font-semibold text-gray-900">{progress.total}</div>
                    <div className="text-xs text-gray-500">Total</div>
                </div>
                <div className="bg-green-50 rounded-md py-2">
                    <div className="text-lg font-semibold text-green-700">{progress.succeeded}</div>
                    <div className="text-xs text-gray-500">Compressed</div>
                </div>
                <div className="bg-amber-50 rounded-md py-2">
                    <div className="text-lg font-semibold text-amber-700">{progress.skipped}</div>
                    <div className="text-xs text-gray-500">Skipped</div>
                </div>
                <div className="bg-red-50 rounded-md py-2">
                    <div className="text-lg font-semibold text-red-700">{progress.failed}</div>
                    <div className="text-xs text-gray-500">Failed</div>
                </div>
            </div>

            <div className="flex items-center justify-between px-3 py-2 bg-blue-50 border border-blue-200 rounded-md">
                <span className="text-sm text-blue-900">Total space saved</span>
                <span className="text-sm font-semibold text-blue-900">{progress.saved_readable}</span>
            </div>

            {isRunning && (
                <p className="text-xs text-gray-500 m-0!">
                    Compression runs on the server. You can close this window &mdash; the job will keep
                    going and progress is kept.
                </p>
            )}
        </div>
    );
}
