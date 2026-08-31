import { useState } from 'react';
import { useStore } from '@/js/Utils/store';
import {
    compressionCompressSingle,
    compressionRestoreSingle,
    notifications,
} from '@/js/Utils/Data';
import type { CompressionDetail } from '@/js/Utils/Data';

interface CompressionDetailsProps {
    attachmentId: number;
    mimeType: string;
    /** Called after a compress/restore so the row can refresh its summary. */
    onChanged: (detail: CompressionDetail) => void;
}

/** MIME types the compression feature accepts, mirrored from the server. */
const SUPPORTED = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

/**
 * Per-row compression summary and actions for the media table.
 *
 * Reads its summary from the shared store, which the table populates in one
 * batched request per page — this component never fetches per row.
 */
export default function CompressionDetails({ attachmentId, mimeType, onChanged }: CompressionDetailsProps) {
    const { compression } = useStore();
    const [busy, setBusy] = useState<'' | 'compress' | 'restore'>('');
    const [expanded, setExpanded] = useState(false);

    if (!SUPPORTED.includes(mimeType)) {
        return null;
    }

    const detail = compression.details[attachmentId];
    const access = compression.access;

    const handleCompress = async () => {
        if (busy) return;
        setBusy('compress');
        try {
            const response = await compressionCompressSingle({ attachment_id: attachmentId }) as {
                data?: { status?: string; compression?: CompressionDetail };
            };
            const next = response?.data?.compression;
            if (next) onChanged(next);

            if ('skipped' === response?.data?.status) {
                notifications(true, 'Already optimised — the file could not be made smaller.');
            } else {
                notifications(true, 'Image compressed.');
            }
        } catch (error) {
            const message =
                (error as { response?: { data?: { message?: string } } })?.response?.data?.message
                || 'Compression failed.';
            notifications(false, message);
        } finally {
            setBusy('');
        }
    };

    const handleRestore = async () => {
        if (busy) return;
        setBusy('restore');
        try {
            const response = await compressionRestoreSingle({ attachment_id: attachmentId }) as {
                data?: { compression?: CompressionDetail };
            };
            const next = response?.data?.compression;
            if (next) onChanged(next);
            notifications(true, 'Original image restored.');
        } catch (error) {
            const message =
                (error as { response?: { data?: { message?: string } } })?.response?.data?.message
                || 'Restore failed.';
            notifications(false, message);
        } finally {
            setBusy('');
        }
    };

    const hasData = detail?.has_data;
    const saved = detail?.saved_percent ?? 0;

    return (
        <div className="flex flex-col items-end gap-1">
            {hasData ? (
                <>
                    <button
                        type="button"
                        className="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-medium bg-green-50 text-green-700 border border-green-200 rounded-full cursor-pointer hover:bg-green-100 transition-colors"
                        onClick={() => setExpanded(!expanded)}
                        title="View compression details"
                    >
                        Compressed &minus;{saved}%
                        <svg className="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                  d={expanded ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7'} />
                        </svg>
                    </button>

                    {expanded && (
                        <div className="text-right text-[11px] text-gray-500 leading-relaxed">
                            <div>
                                {detail?.original_size_readable} &rarr; {detail?.current_size_readable}
                            </div>
                            <div>Saved {detail?.saved_bytes_readable}</div>
                            {detail?.engine && <div>Engine: {detail.engine}</div>}
                            {!!detail?.quality && <div>Quality: {detail.quality}</div>}
                            {!!detail?.sizes?.length && <div>{detail.sizes.length} file(s) processed</div>}
                            {detail?.last_error && (
                                <div className="text-red-600">{detail.last_error}</div>
                            )}
                        </div>
                    )}

                    {detail?.restore_available && (
                        <button
                            type="button"
                            className="text-[11px] text-blue-600 hover:text-blue-800 cursor-pointer disabled:opacity-50"
                            onClick={handleRestore}
                            disabled={!!busy}
                        >
                            {'restore' === busy ? 'Restoring…' : 'Restore original'}
                        </button>
                    )}
                </>
            ) : (
                access?.feature_available !== false && (
                    <button
                        type="button"
                        className="text-[11px] text-blue-600 hover:text-blue-800 cursor-pointer disabled:opacity-50"
                        onClick={handleCompress}
                        disabled={!!busy}
                    >
                        {'compress' === busy ? 'Compressing…' : 'Compress image'}
                    </button>
                )
            )}
        </div>
    );
}
