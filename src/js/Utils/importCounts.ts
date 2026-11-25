/**
 * CSV import: rows by outcome, from the per-row results the import returns.
 * A row is imported when its status is "uploaded"; imported rows that carry a
 * message (for example a requested file rename that failed while the other
 * fields were saved) are also counted as having a warning.
 */
export interface ImportRowOutcome {
    status: string;
    message?: string;
}

export interface ImportCounts {
    /** Rows imported, with or without a warning. */
    imported: number;
    /** Imported rows with a warning. */
    warnings: number;
    /** Rows not imported. */
    failed: number;
}

export const importCounts = (rows: ImportRowOutcome[]): ImportCounts => {
    const imported = rows.filter((row) => row.status === 'uploaded');
    return {
        imported: imported.length,
        warnings: imported.filter((row) => !!row.message).length,
        failed: rows.length - imported.length,
    };
};
