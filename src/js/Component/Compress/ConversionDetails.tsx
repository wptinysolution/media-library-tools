import { useState } from 'react';
import { useStore } from '@/js/Utils/store';
import {
    conversionConvertSingle,
    conversionDelete,
    conversionRegenerate,
    notifications,
} from '@/js/Utils/Data';
import type { ConversionDetail } from '@/js/Utils/Data';

interface ConversionDetailsProps {
    attachmentId: number;
    mimeType: string;
    /** Called after a change so the row can refresh its summary. */
    onChanged: (detail: ConversionDetail) => void;
}

/** Source types the conversion feature accepts, mirrored from the server. */
const SUPPORTED = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

/**
 * Per-row conversion summary and actions for the media table.
 *
 * Reads its summary from the shared store, which the table populates in one
 * batched request per page — this component never fetches per row.
 */
export default function ConversionDetails({ attachmentId, mimeType, onChanged }: ConversionDetailsProps) {
    const { conversion } = useStore();
    const [busy, setBusy] = useState<'' | 'convert' | 'delete' | 'regenerate'>('');

    if (!SUPPORTED.includes(mimeType)) {
        return null;
    }

    const detail = conversion.details[attachmentId];
    const available = conversion.available;

    /** Run one action, surfacing the server's message either way. */
    const run = async (
        kind: 'convert' | 'delete' | 'regenerate',
        fn: () => Promise<{ data?: { conversion?: ConversionDetail } }>,
        successText: string
    ) => {
        if (busy) return;
        setBusy(kind);
        try {
            const response = await fn();
            const next = response?.data?.conversion;
            if (next) onChanged(next);
            notifications(true, successText);
        } catch (error) {
            const message =
                (error as { response?: { data?: { message?: string } } })?.response?.data?.message
                || 'The action could not be completed.';
            notifications(false, message);
        } finally {
            setBusy('');
        }
    };

    const formats = (detail?.formats ?? []).filter((f) => 'completed' === f.status);

    if (!detail?.has_data) {
        if (false === available) {
            return null;
        }
        return (
            <button
                type="button"
                className="text-[11px] text-blue-600 hover:text-blue-800 cursor-pointer disabled:opacity-50"
                onClick={() => run(
                    'convert',
                    () => conversionConvertSingle({ attachment_id: attachmentId }),
                    'Converted.'
                )}
                disabled={!!busy}
            >
                {'convert' === busy ? 'Converting…' : 'Convert to WebP/AVIF'}
            </button>
        );
    }

    return (
        <div className="flex flex-col items-end gap-1">
            <div className="flex items-center gap-1">
                {formats.map((f) => (
                    <span
                        key={f.format}
                        className="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-full uppercase"
                        title={`${f.size_readable} · ${f.saved_percent}% smaller than the original`}
                    >
                        {f.format} &minus;{f.saved_percent}%
                    </span>
                ))}
            </div>

            {/* The source image changed after these files were generated, so they
                no longer represent it. Regenerating is the fix. */}
            {detail.is_stale && (
                <span className="text-[11px] text-amber-700">Original changed — out of date</span>
            )}

            <div className="flex items-center gap-2">
                <button
                    type="button"
                    className="text-[11px] text-blue-600 hover:text-blue-800 cursor-pointer disabled:opacity-50"
                    onClick={() => run(
                        'regenerate',
                        () => conversionRegenerate({ attachment_id: attachmentId }),
                        'Regenerated.'
                    )}
                    disabled={!!busy}
                >
                    {'regenerate' === busy ? 'Regenerating…' : 'Regenerate'}
                </button>
                <button
                    type="button"
                    className="text-[11px] text-red-600 hover:text-red-800 cursor-pointer disabled:opacity-50"
                    onClick={() => run(
                        'delete',
                        () => conversionDelete({ attachment_id: attachmentId }),
                        'Converted files deleted.'
                    )}
                    disabled={!!busy}
                >
                    {'delete' === busy ? 'Deleting…' : 'Delete'}
                </button>
            </div>
        </div>
    );
}
