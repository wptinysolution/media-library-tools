import { useMemo, useState } from 'react';
import { useStore } from '@/js/Utils/store';
import { bulkAiApply, bulkAiReset, getMedia, notifications } from '@/js/Utils/Data';
import type { BulkAiField } from '@/js/Utils/Data';
import Modal from '@/js/Component/Common/Modal';
import { useBulkAiJob } from '@/js/Component/BulkAi/useBulkAiJob';
import BulkAiReview from '@/js/Component/BulkAi/BulkAiReview';

/** Fields offered in the Media Table. Filenames belong to the Rename table. */
const FIELD_OPTIONS: { key: BulkAiField; label: string }[] = [
    { key: 'title', label: 'Title' },
    { key: 'alt_text', label: 'Alt Text' },
    { key: 'caption', label: 'Caption' },
    { key: 'description', label: 'Description' },
];

const DEFAULT_FIELDS: BulkAiField[] = ['title', 'alt_text'];

/**
 * Selection size above which the run must be confirmed. Not a cap — larger runs
 * are allowed, but each item is a request billed by the site's own AI provider,
 * so a big one should be deliberate rather than a mis-click.
 */
const CONFIRM_THRESHOLD = 50;

interface BulkAiModalProps {
    isOpen: boolean;
    onClose: () => void;
    ids: number[];
    /**
     * `metadata` offers the four text fields; `filename` runs a filename-only job
     * for the Rename table, where the field set is fixed and applying performs a
     * real rename through the existing rename implementation.
     */
    variant?: 'metadata' | 'filename';
}

/**
 * Drives the bulk AI flow: pick fields, run the job, review the suggestions,
 * then apply the approved ones. Nothing is written until Apply is pressed.
 */
export default function BulkAiModal({ isOpen, onClose, ids, variant = 'metadata' }: BulkAiModalProps) {
    const { mediaData, setMediaData } = useStore();
    const { progress, results, loadingResults, start, cancel, retry, setResults } = useBulkAiJob();

    const isFilename = 'filename' === variant;

    const [fields, setFields] = useState<BulkAiField[]>(isFilename ? ['filename'] : DEFAULT_FIELDS);
    // Filenames always exist, so "only missing" would skip every item — a
    // filename run is therefore always an overwrite of the suggestion, though
    // nothing is renamed until the user applies it.
    const [mode, setMode] = useState<'missing' | 'overwrite'>(isFilename ? 'overwrite' : 'missing');
    const [applying, setApplying] = useState(false);
    // Set when a large run needs explicit confirmation before it starts.
    const [confirming, setConfirming] = useState(false);

    const isRunning = progress?.status === 'running';
    const isFinished = !!progress && ['completed', 'partial', 'cancelled'].includes(progress.status);
    const hasResults = Object.keys(results).length > 0;

    // Which stage the modal is showing. Review wins once there is something to
    // review, so a cancelled run still surfaces what it managed to collect.
    const stage: 'setup' | 'confirm' | 'progress' | 'review' = useMemo(() => {
        if (isRunning) return 'progress';
        if (isFinished && (hasResults || loadingResults)) return 'review';
        if (confirming) return 'confirm';
        return 'setup';
    }, [isRunning, isFinished, hasResults, loadingResults, confirming]);

    const toggleField = (field: BulkAiField) => {
        setFields(current =>
            current.includes(field) ? current.filter(f => f !== field) : [...current, field]
        );
    };

    const handleStart = async () => {
        if (!fields.length) {
            notifications(false, 'Select at least one field to generate.');
            return;
        }
        if (ids.length > CONFIRM_THRESHOLD) {
            setConfirming(true);
            return;
        }
        await start(ids, fields, mode);
    };

    /** Start the run the user just confirmed. */
    const handleConfirmedStart = async () => {
        setConfirming(false);
        await start(ids, fields, mode);
    };

    /**
     * Close and drop the pending confirmation, so reopening starts at the setup
     * step rather than on a stale confirmation for a different selection.
     */
    const handleClose = () => {
        setConfirming(false);
        onClose();
    };

    const handleApply = async (approved: Record<number, Partial<Record<BulkAiField, string>>>) => {
        const count = Object.keys(approved).length;
        if (!count) {
            notifications(false, 'No suggestions were accepted.');
            return;
        }

        setApplying(true);
        try {
            const result = await bulkAiApply({ items: approved });
            const parts: string[] = [];
            if (result.renamed) {
                parts.push(`${result.renamed} file${result.renamed === 1 ? '' : 's'} renamed`);
            }
            if (result.applied) {
                parts.push(`${result.applied} item${result.applied === 1 ? '' : 's'} updated`);
            }
            if (result.failed) {
                parts.push(`${result.failed} failed`);
            }
            notifications(!result.failed, (parts.join(', ') || 'Nothing to apply') + '.');

            await bulkAiReset();
            setResults({});

            // Refetch the current page so applied values appear without a reload.
            // Renames change file URLs and generated thumbnails, so the table data
            // must come from the server rather than being patched locally.
            try {
                const refreshed = await getMedia(mediaData.postQuery);
                setMediaData({ ...refreshed, isLoading: false });
            } catch {
                notifications(false, 'Saved, but the table could not be refreshed. Reload to see the changes.');
            }

            onClose();
        } catch {
            notifications(false, 'The suggestions could not be applied.');
        } finally {
            setApplying(false);
        }
    };

    const handleDiscard = async () => {
        await bulkAiReset();
        setResults({});
        onClose();
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={isRunning ? () => undefined : handleClose}
            title={isFilename ? 'Suggest Filename with AI' : 'Generate with AI'}
        >
            <div className="p-5 space-y-5">

                {stage === 'setup' && (
                    <>
                        <p className="text-sm text-gray-600 m-0!">
                            Generating for <strong>{ids.length}</strong> selected item{ids.length === 1 ? '' : 's'}.
                            Your Content Language and Custom Instructions settings are applied automatically.
                        </p>

                        {isFilename ? (
                            <div className="flex items-start gap-2 px-3 py-2.5 text-[13px] text-amber-800 bg-amber-50 border border-amber-200 rounded-md">
                                <svg className="w-4 h-4 mt-0.5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span>
                                    Filenames are only <strong>suggested</strong>. No file is renamed until you review
                                    and apply. Back up before applying — file URLs will change.
                                </span>
                            </div>
                        ) : (
                            <>
                                <div>
                                    <span className="block text-sm font-semibold text-gray-700 mb-2">Fields to generate</span>
                                    <div className="flex flex-wrap gap-4">
                                        {FIELD_OPTIONS.map(({ key, label }) => (
                                            <label key={key} className="inline-flex items-center gap-2 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    className="w-4 h-4 m-0! border-gray-300 text-blue-600 rounded cursor-pointer"
                                                    checked={fields.includes(key)}
                                                    onChange={() => toggleField(key)}
                                                />
                                                <span className="text-sm text-gray-900">{label}</span>
                                            </label>
                                        ))}
                                    </div>
                                </div>

                                <div>
                                    <span className="block text-sm font-semibold text-gray-700 mb-2">Mode</span>
                                    <label className="flex items-start gap-2 cursor-pointer mb-2">
                                        <input
                                            type="radio"
                                            name="bulk_ai_mode"
                                            className="w-4 h-4 m-0! mt-0.5 cursor-pointer"
                                            checked={mode === 'missing'}
                                            onChange={() => setMode('missing')}
                                        />
                                        <span className="text-sm text-gray-900">
                                            Only missing fields
                                            <span className="block text-xs text-gray-500">
                                                Skips fields that already have a value — fewer AI requests.
                                            </span>
                                        </span>
                                    </label>
                                    <label className="flex items-start gap-2 cursor-pointer">
                                        <input
                                            type="radio"
                                            name="bulk_ai_mode"
                                            className="w-4 h-4 m-0! mt-0.5 cursor-pointer"
                                            checked={mode === 'overwrite'}
                                            onChange={() => setMode('overwrite')}
                                        />
                                        <span className="text-sm text-gray-900">
                                            Overwrite existing fields
                                            <span className="block text-xs text-gray-500">
                                                Generates a new value for every selected field.
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </>
                        )}

                        <div className="flex items-center justify-end gap-2 pt-1">
                            <button
                                type="button"
                                onClick={handleClose}
                                className="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 cursor-pointer"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={handleStart}
                                disabled={!fields.length}
                                className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50 cursor-pointer"
                            >
                                Generate
                            </button>
                        </div>
                    </>
                )}

                {stage === 'confirm' && (
                    <>
                        <div className="flex items-start gap-2 px-3 py-2.5 text-[13px] text-amber-800 bg-amber-50 border border-amber-200 rounded-md">
                            <svg className="w-4 h-4 mt-0.5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span>
                                This is a large run. Each item is one request billed by your own AI
                                provider, so please confirm before starting.
                            </span>
                        </div>

                        <dl className="text-sm text-gray-700 space-y-1.5 m-0!">
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Media items</dt>
                                <dd className="font-semibold m-0!">{ids.length}</dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Fields per item</dt>
                                <dd className="font-semibold m-0!">{fields.length}</dd>
                            </div>
                            <div className="flex justify-between gap-4 pt-1.5 border-t border-gray-100">
                                <dt className="text-gray-500">Approximate AI requests</dt>
                                <dd className="font-semibold m-0!">up to {ids.length}</dd>
                            </div>
                        </dl>

                        <p className="text-xs text-gray-500 m-0!">
                            All selected fields are generated in a single request per item.
                            {mode === 'missing' && ' Items whose fields are already filled are skipped, so the real number is usually lower.'}
                            {' '}You can stop the run at any time, and nothing is saved until you review and apply.
                        </p>

                        <div className="flex items-center justify-end gap-2 pt-1">
                            <button
                                type="button"
                                onClick={() => setConfirming(false)}
                                className="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 cursor-pointer"
                            >
                                Back
                            </button>
                            <button
                                type="button"
                                onClick={handleConfirmedStart}
                                className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer"
                            >
                                Generate {ids.length} Items
                            </button>
                        </div>
                    </>
                )}

                {stage === 'progress' && progress && (
                    <>
                        <p className="text-sm font-medium text-gray-800 m-0!">Generating AI metadata…</p>

                        <div>
                            <div className="flex items-center justify-between text-sm text-gray-600 mb-1">
                                <span>{progress.processed} / {progress.total} completed</span>
                                <span>{progress.percent}%</span>
                            </div>
                            <div className="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div
                                    className="h-full bg-blue-600 transition-all duration-300"
                                    style={{ width: `${progress.percent}%` }}
                                />
                            </div>
                        </div>

                        <div className="flex flex-wrap gap-x-5 gap-y-1 text-xs text-gray-500">
                            <span>Generated: <strong className="text-gray-700">{progress.succeeded}</strong></span>
                            <span>Skipped: <strong className="text-gray-700">{progress.skipped}</strong></span>
                            <span>Failed: <strong className="text-gray-700">{progress.failed}</strong></span>
                            <span>Remaining: <strong className="text-gray-700">{progress.remaining}</strong></span>
                        </div>

                        <p className="text-xs text-gray-400 m-0!">
                            This continues on the server — you can close this tab and come back.
                        </p>

                        <div className="flex items-center justify-end pt-1">
                            <button
                                type="button"
                                onClick={cancel}
                                className="px-4 py-2 text-sm font-medium text-red-600 border border-red-200 rounded-md hover:bg-red-50 cursor-pointer"
                            >
                                Stop
                            </button>
                        </div>
                    </>
                )}

                {stage === 'review' && (
                    <BulkAiReview
                        results={results}
                        loading={loadingResults}
                        progress={progress}
                        applying={applying}
                        isFilename={isFilename}
                        onApply={handleApply}
                        onRetry={retry}
                        onDiscard={handleDiscard}
                    />
                )}
            </div>
        </Modal>
    );
}
