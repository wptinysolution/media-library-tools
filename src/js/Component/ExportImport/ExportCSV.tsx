import { useEffect, useState } from 'react';
import { useStore } from '@/js/Utils/store';
import Papa from 'papaparse';

export const IMPORT_HISTORY_KEY = 'tsmlt_csv_import_history';
const HISTORY_KEY = 'tsmlt_csv_export_history';
const MAX_HISTORY = 20;

interface ExportRecord {
    id: string;
    filename: string;
    rows: number;
    date: string; // ISO string
}

const loadHistory = (): ExportRecord[] => {
    try {
        return JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]');
    } catch {
        return [];
    }
};

const saveHistory = (records: ExportRecord[]) => {
    localStorage.setItem(HISTORY_KEY, JSON.stringify(records.slice(-MAX_HISTORY)));
};

export const triggerCsvDownload = (filename: string, content: string) => {
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

export const redownloadCsv = (id: string, filename: string): boolean => {
    try {
        const content = sessionStorage.getItem(`tsmlt_csv_${id}`);
        if (!content) return false;
        triggerCsvDownload(filename, content);
        return true;
    } catch {
        return false;
    }
};

// `categories` arrives as a JSON string of {id, name} objects. Export the names as a
// comma-separated list so an exported file can be re-imported without translation.
export const parseGroupNames = (categories: unknown): string => {
    try {
        const parsed = JSON.parse(String(categories)) as Array<{ name?: string }>;
        return parsed.map(item => item.name).filter(Boolean).join(', ');
    } catch {
        return '';
    }
};

const escapeValues = (obj: Record<string, unknown>): Record<string, unknown> => {
    const escaped: Record<string, unknown> = {};
    for (const key in obj) {
        const value = obj[key];
        if (typeof value === 'object' && value !== null) {
            escaped[key] = JSON.stringify(value);
        } else {
            escaped[key] = value;
        }
    }
    return escaped;
};

function ExportCSV() {
    const { exportImport, bulkExport } = useStore();
    const [csvData, setCsvData] = useState('');
    const filteredData = exportImport?.mediaFiles || [];
    const selectedKeys = bulkExport.selectedKeys;

    const generateCSVStructure = () => {
        if (filteredData.length < 1) return;
        const updatedData = filteredData.map(item => {
            const flatMeta = (item.custom_meta as Record<string, unknown>) || {};
            const fullRow: Record<string, unknown> = {
                ID: item.ID,
                slug: item.slug,
                url: item.url,
                title: item.title,
                caption: item.caption,
                description: item.description,
                alt_text: item.alt_text,
                // Comma-separated group names — the same format the CSV importer parses.
                groups: parseGroupNames(item.categories),
                // Native attachment fields, exported raw so they round-trip on import.
                post_parent: item.post_parent ?? 0,
                menu_order: item.menu_order ?? 0,
                ...flatMeta,
            };
            const finalKeys = Array.from(new Set(['ID', 'slug', ...selectedKeys]));
            const filteredRow: Record<string, unknown> = {};
            finalKeys.forEach(key => {
                if (key in fullRow) filteredRow[key] = fullRow[key];
            });
            return escapeValues(filteredRow);
        });
        const csv = Papa.unparse(updatedData, { quotes: true });
        setCsvData(csv);
    };

    useEffect(() => {
        generateCSVStructure();
    }, [selectedKeys]);

    const downloadCSV = () => {
        const filename = `export-media-file-${window.location.hostname}.csv`;
        triggerCsvDownload(filename, csvData);

        // Record this export in history and cache content for re-download.
        const id = Date.now().toString();
        const record: ExportRecord = { id, filename, rows: filteredData.length, date: new Date().toISOString() };
        saveHistory([...loadHistory(), record]);
        try { sessionStorage.setItem(`tsmlt_csv_${id}`, csvData); } catch { /* quota exceeded */ }
    };

    return (
        <>
            {csvData && (
                <button
                    type="button"
                    className="px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer transition-colors"
                    onClick={downloadCSV}
                >
                    Download CSV
                </button>
            )}
        </>
    );
}

export interface ImportRecord {
    id: string;
    sessionId: string; // key into sessionStorage for re-import
    filename: string;
    rows: number;
    succeeded: number;
    date: string;
}

export const loadImportHistory = (): ImportRecord[] => {
    try {
        return JSON.parse(localStorage.getItem(IMPORT_HISTORY_KEY) || '[]');
    } catch {
        return [];
    }
};

export const saveImportHistory = (records: ImportRecord[]) => {
    localStorage.setItem(IMPORT_HISTORY_KEY, JSON.stringify(records.slice(-MAX_HISTORY)));
};

export { loadHistory, saveHistory };
export type { ExportRecord };
export default ExportCSV;
