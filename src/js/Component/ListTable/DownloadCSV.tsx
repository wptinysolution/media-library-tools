import { useEffect, useState } from 'react';
import { useStore } from '@/js/Utils/store';
import Papa from 'papaparse';

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

function DownloadCSV() {
    const { bulkExport, mediaData, bulkSubmitData } = useStore();
    const [csvData, setCsvData] = useState('');

    const selectedKeys = bulkExport.selectedKeys;
    const media = mediaData?.posts || [];
    const selectedIds = bulkSubmitData?.ids || [];
    const filteredData = media.filter(item => selectedIds.includes(item.ID));

    const generateCSVStructure = () => {
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
                ...flatMeta,
            };

            const finalKeys = Array.from(new Set(['ID', 'slug', ...selectedKeys]));
            const filteredRow: Record<string, unknown> = {};

            finalKeys.forEach(key => {
                if (key in fullRow) {
                    filteredRow[key] = fullRow[key];
                }
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
        const blob = new Blob([csvData], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `export-media-file-${window.location.hostname}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    return (
        <>
            {csvData && (
                <button
                    type="button"
                    onClick={downloadCSV}
                    className="px-5 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md cursor-pointer transition-colors"
                >
                    Download CSV
                </button>
            )}
        </>
    );
}

export default DownloadCSV;
