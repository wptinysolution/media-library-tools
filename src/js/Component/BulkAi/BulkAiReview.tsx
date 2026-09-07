import { useEffect, useMemo, useState } from 'react';
import type { BulkAiField, BulkAiProgress, BulkAiResults } from '@/js/Utils/Data';

/** Human labels for the field keys the job returns. */
const FIELD_LABELS: Record<string, string> = {
    title: 'Title',
    alt_text: 'Alt Text',
    caption: 'Caption',
    description: 'Description',
    filename: 'Filename',
};

/** One reviewable row: a single field of a single attachment. */
interface ReviewRow {
    key: string;
    id: number;
    field: BulkAiField;
    current: string;
    suggested: string;
    accepted: boolean;
}

interface BulkAiReviewProps {
    results: BulkAiResults;
    loading: boolean;
    progress: BulkAiProgress | null;
    applying: boolean;
    /** Filename runs show a single-line old → new comparison and warn before applying. */
    isFilename?: boolean;
    onApply: (approved: Record<number, Partial<Record<BulkAiField, string>>>) => void;
    onRetry: () => void;
    onDiscard: () => void;
}

/**
 * Review table for generated suggestions.
 *
 * Every row starts accepted so a large run needs one click, while individual
 * rows can still be edited or ignored. Nothing here writes to the media
 * library — the approved set is handed back to the caller to apply.
 */
export default function BulkAiReview({
    results,
    loading,
    progress,
    applying,
    isFilename = false,
    onApply,
    onRetry,
    onDiscard,
}: BulkAiReviewProps) {
    const [rows, setRows] = useState<ReviewRow[]>([]);

    // Flatten { id: { field: {current, suggested} } } into one row per field.
    const flattened = useMemo<ReviewRow[]>(() => {
        const list: ReviewRow[] = [];
        Object.entries(results).forEach(([id, fields]) => {
            Object.entries(fields || {}).forEach(([field, value]) => {
                if (!value) return;
                list.push({
                    key: `${id}:${field}`,
                    id: Number(id),
                    field: field as BulkAiField,
                    current: value.current || '',
                    suggested: value.suggested || '',
                    accepted: true,
                });
            });
        });
        return list;
    }, [results]);

    useEffect(() => {
        setRows(flattened);
    }, [flattened]);

    const acceptedCount = rows.filter(r => r.accepted).length;

    const setAll = (accepted: boolean) => {
        setRows(current => current.map(row => ({ ...row, accepted })));
    };

    const toggleRow = (key: string) => {
        setRows(current => current.map(row => (
            row.key === key ? { ...row, accepted: !row.accepted } : row
        )));
    };

    const editRow = (key: string, suggested: string) => {
        setRows(current => current.map(row => (
            row.key === key ? { ...row, suggested } : row
        )));
    };

    const handleApply = () => {
        const approved: Record<number, Partial<Record<BulkAiField, string>>> = {};
        rows.forEach(row => {
            if (!row.accepted) return;
            const value = row.suggested.trim();
            if (!value) return;
            approved[row.id] = { ...(approved[row.id] || {}), [row.field]: value };
        });
        onApply(approved);
    };

    if (loading) {
        return <p className="text-sm text-gray-500 m-0!">Loading suggestions…</p>;
    }

    if (!rows.length) {
        return (
            <div className="space-y-4">
                <p className="text-sm text-gray-600 m-0!">
                    No suggestions were generated.
                    {progress?.skipped ? ` ${progress.skipped} item(s) already had every selected field filled.` : ''}
                </p>
                <div className="flex justify-end">
                    <button
                        type="button"
                        onClick={onDiscard}
                        className="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
                    >
                        Close
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm text-gray-600 m-0!">
                    <strong>{rows.length}</strong> suggestion{rows.length === 1 ? '' : 's'} ready
                    {progress?.skipped ? `, ${progress.skipped} skipped` : ''}
                    {progress?.failed ? `, ${progress.failed} failed` : ''}.
                </p>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => setAll(true)}
                        className="px-2.5 py-1 text-xs font-medium text-blue-700 bg-blue-50 rounded hover:bg-blue-100 cursor-pointer"
                    >
                        Accept All
                    </button>
                    <button
                        type="button"
                        onClick={() => setAll(false)}
                        className="px-2.5 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded hover:bg-gray-200 cursor-pointer"
                    >
                        Ignore All
                    </button>
                </div>
            </div>

            <div className="max-h-[50vh] overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100">
                {rows.map(row => (
                    <div
                        key={row.key}
                        className={`px-3 py-2.5 ${row.accepted ? '' : 'bg-gray-50 opacity-60'}`}
                    >
                        <div className="flex items-start gap-3">
                            <input
                                type="checkbox"
                                className="w-4 h-4 m-0! mt-1 border-gray-300 text-blue-600 rounded cursor-pointer shrink-0"
                                checked={row.accepted}
                                onChange={() => toggleRow(row.key)}
                            />
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2 mb-1">
                                    <span className="text-[11px] font-semibold text-gray-500">#{row.id}</span>
                                    <span className="px-1.5 py-0.5 text-[10px] font-semibold bg-blue-50 text-blue-700 rounded">
                                        {FIELD_LABELS[row.field] || row.field}
                                    </span>
                                </div>
                                {row.current && (
                                    <p className="text-xs text-gray-400 line-through m-0! mb-1 truncate font-mono">
                                        {row.current}
                                    </p>
                                )}
                                {isFilename ? (
                                    <input
                                        type="text"
                                        value={row.suggested}
                                        onChange={(e) => editRow(row.key, e.target.value)}
                                        disabled={!row.accepted}
                                        className="w-full px-2 py-1.5 text-sm font-mono border border-gray-200 rounded focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400 focus:outline-none disabled:bg-transparent"
                                    />
                                ) : (
                                    <textarea
                                        rows={row.field === 'description' ? 3 : 2}
                                        value={row.suggested}
                                        onChange={(e) => editRow(row.key, e.target.value)}
                                        disabled={!row.accepted}
                                        className="w-full px-2 py-1.5 text-sm border border-gray-200 rounded resize-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400 focus:outline-none disabled:bg-transparent"
                                    />
                                )}
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    {progress?.has_failed && (
                        <button
                            type="button"
                            onClick={onRetry}
                            className="px-3 py-2 text-sm font-medium text-amber-700 border border-amber-200 rounded-md hover:bg-amber-50 cursor-pointer"
                        >
                            Retry {progress.failed} failed
                        </button>
                    )}
                </div>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={onDiscard}
                        disabled={applying}
                        className="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 disabled:opacity-50 cursor-pointer"
                    >
                        Discard
                    </button>
                    <button
                        type="button"
                        onClick={handleApply}
                        disabled={applying || !acceptedCount}
                        className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50 cursor-pointer"
                    >
                        {applying
                            ? 'Applying…'
                            : isFilename
                                ? `Rename ${acceptedCount} File${acceptedCount === 1 ? '' : 's'}`
                                : `Apply ${acceptedCount} Selected`}
                    </button>
                </div>
            </div>
        </div>
    );
}
