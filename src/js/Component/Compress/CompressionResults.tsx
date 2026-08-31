import type { CompressionProgress as Progress } from '@/js/Utils/Data';

interface CompressionResultsProps {
    progress: Progress;
}

/**
 * Human-readable explanation for a skipped image.
 *
 * A skip is a normal outcome, not a failure — most often the file was already
 * as small as the encoder can make it.
 */
function skipReason(reason: string): string {
    switch (reason) {
        case 'no_improvement':
            return 'Already optimised';
        case 'missing_file':
            return 'File missing';
        default:
            return 'Skipped';
    }
}

/**
 * Per-image outcome list shown once a job finishes.
 *
 * The server keeps only the most recent entries, so this is a summary of the
 * tail of a long run rather than an exhaustive log.
 */
export default function CompressionResults({ progress }: CompressionResultsProps) {
    const results = progress.recent_results || [];
    const errors = progress.recent_errors || [];

    if (!results.length && !errors.length) {
        return null;
    }

    return (
        <div className="space-y-3">
            {results.length > 0 && (
                <div className="border border-gray-200 rounded-md overflow-hidden">
                    <div className="px-3 py-2 bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-600 uppercase tracking-wide">
                        Results
                    </div>
                    <div className="max-h-56 overflow-y-auto divide-y divide-gray-100">
                        {results.map((item) => (
                            <div key={item.id} className="flex items-center justify-between px-3 py-2 gap-3">
                                <span className="text-sm text-gray-800 truncate flex-1" title={item.title}>
                                    {item.title}
                                </span>
                                {'completed' === item.status ? (
                                    <span className="text-xs whitespace-nowrap">
                                        <span className="text-gray-500">{item.before_readable}</span>
                                        <span className="text-gray-400 mx-1">&rarr;</span>
                                        <span className="text-gray-700">{item.after_readable}</span>
                                        <span className="ml-2 font-semibold text-green-700">
                                            &minus;{item.saved_percent}%
                                        </span>
                                    </span>
                                ) : (
                                    <span className="text-xs text-amber-700 whitespace-nowrap">
                                        {skipReason(item.reason)}
                                    </span>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {errors.length > 0 && (
                <div className="border border-red-200 rounded-md overflow-hidden">
                    <div className="px-3 py-2 bg-red-50 border-b border-red-200 text-xs font-semibold text-red-700 uppercase tracking-wide">
                        Failed
                    </div>
                    <div className="max-h-40 overflow-y-auto divide-y divide-red-100">
                        {errors.map((item) => (
                            <div key={item.id} className="px-3 py-2">
                                <div className="text-sm text-gray-800 truncate" title={item.title}>
                                    {item.title}
                                </div>
                                <div className="text-xs text-red-600">{item.error}</div>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
