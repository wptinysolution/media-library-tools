import { useState, useEffect } from 'react';
import { getEmptyDirectories, deleteEmptyDirectory, notifications } from '@/js/Utils/Data';
import { trimPath } from '@/js/Component/Rubbish/DirectoryList';

interface EmptyDirState {
    directories: string[];
    isLoading: boolean;
    deleting: Record<string, boolean>;
}

export default function EmptyDirectories() {
    const [state, setState] = useState<EmptyDirState>({
        directories: [],
        isLoading: true,
        deleting: {},
    });

    const load = async () => {
        setState(s => ({ ...s, isLoading: true }));
        const result = await getEmptyDirectories();
        setState(s => ({ ...s, directories: result.directories ?? [], isLoading: false }));
    };

    useEffect(() => {
        load();
    }, []);

    const handleDelete = async (dir: string) => {
        setState(s => ({ ...s, deleting: { ...s.deleting, [dir]: true } }));
        const response = await deleteEmptyDirectory(dir);
        notifications(response.data?.updated, response.data?.message);
        if (response.data?.updated) {
            setState(s => ({
                ...s,
                directories: s.directories.filter(d => d !== dir),
                deleting: { ...s.deleting, [dir]: false },
            }));
        } else {
            setState(s => ({ ...s, deleting: { ...s.deleting, [dir]: false } }));
        }
    };

    const handleDeleteAll = async () => {
        for (const dir of state.directories) {
            setState(s => ({ ...s, deleting: { ...s.deleting, [dir]: true } }));
            const response = await deleteEmptyDirectory(dir);
            if (response.data?.updated) {
                setState(s => ({
                    ...s,
                    directories: s.directories.filter(d => d !== dir),
                    deleting: { ...s.deleting, [dir]: false },
                }));
            } else {
                setState(s => ({ ...s, deleting: { ...s.deleting, [dir]: false } }));
            }
        }
        notifications(true, 'All empty directories processed.');
    };

    if (state.isLoading) {
        return (
            <div className="flex items-center justify-center py-8">
                <svg className="w-6 h-6 animate-spin text-blue-600" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                </svg>
            </div>
        );
    }

    if (state.directories.length === 0) {
        return (
            <div className="flex items-center gap-2 px-4 py-3 text-sm text-green-700 bg-green-50 border border-green-200 rounded-md">
                <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                </svg>
                No empty directories found. Your uploads folder is clean.
            </div>
        );
    }

    return (
        <div className="space-y-3">
            {/* Warning notice */}
            <div className="flex items-start gap-3 px-4 py-3 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-md">
                <svg className="w-4 h-4 mt-0.5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div>
                    <strong>These directories are empty and unnecessary.</strong> They contain no files and serve no purpose in your uploads folder. Deleting them keeps your file system clean and organised. This action cannot be undone.
                </div>
            </div>

            {/* Header row with count + delete all */}
            <div className="flex items-center justify-between px-1">
                <span className="text-sm text-gray-500">
                    <span className="font-semibold text-gray-800">{state.directories.length}</span> empty {state.directories.length === 1 ? 'directory' : 'directories'} found
                </span>
                <button
                    type="button"
                    onClick={handleDeleteAll}
                    disabled={Object.values(state.deleting).some(Boolean)}
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-red-600 rounded-md hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                >
                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Delete All Empty Directories
                </button>
            </div>

            {/* Directory list */}
            <div className="border border-gray-200 rounded-lg overflow-hidden bg-white divide-y divide-gray-100">
                {state.directories.map(dir => (
                    <div key={dir} className="flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition-colors">
                        <div className="min-w-0 flex-1 mr-4">
                            <div className="flex items-center gap-2">
                                {/* Folder icon */}
                                <svg className="w-4 h-4 shrink-0 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                                </svg>
                                <span className="text-sm text-gray-800 font-medium truncate" title={dir}>
                                    {trimPath(dir)}
                                </span>
                            </div>
                            <p className="text-xs text-gray-400 mt-0.5 ml-6">
                                Empty — contains no files or subdirectories
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={() => handleDelete(dir)}
                            disabled={!!state.deleting[dir]}
                            className="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 transition-colors disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                        >
                            {state.deleting[dir] ? (
                                <svg className="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                            ) : (
                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            )}
                            {state.deleting[dir] ? 'Deleting…' : 'Delete'}
                        </button>
                    </div>
                ))}
            </div>
        </div>
    );
}
