import React, { useEffect, useState } from 'react';

import { Button} from 'antd';
import { useStateValue } from '../../Utils/StateProvider';
import Papa from 'papaparse';

/**
 * Escapes non-primitive values (e.g. objects, arrays) into JSON strings.
 */
const escapeValues = (obj) => {
    const escaped = {};
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

/**
 * @returns {JSX.Element}
 */
function ExportCSV() {
    const [stateValue] = useStateValue();
    const [csvData, setCsvData] = useState('');
    const bulkExportData = stateValue.exportImport;
    const selectedKeys = stateValue.bulkExport.selectedKeys;
    const filteredData = bulkExportData?.mediaFiles || [];

    const generateCSVStructure = () => {

        const updatedData = filteredData.map(item => {
            const flatMeta = item.custom_meta || {};
            const fullRow = {
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
            const filteredRow = {};

            finalKeys.forEach(key => {
                if (key in fullRow) {
                    filteredRow[key] = fullRow[key];
                }
            });

            return escapeValues(filteredRow);
        });

        const csv = Papa.unparse(updatedData, {
            quotes: true,
        });

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
                <Button type={'primary'} onClick={downloadCSV} >
                    Download CSV
                </Button>
            )}
        </>
    );
}

export default ExportCSV;