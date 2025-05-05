import React, { useEffect, useState } from 'react';
import { useStateValue } from '../../Utils/StateProvider';
import Papa from 'papaparse';

const buttonStyle = {
    gap: '5px',
    width: '200px',
    height: '70px',
    fontSize: '25px',
    cursor: 'pointer',
    border: 0,
    color: '#fff',
    borderRadius: '8px',
    background: '#1677ff',
};

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
function DownloadCSV() {
    const [stateValue] = useStateValue();
    const [csvData, setCsvData] = useState('');

    const generateCSVStructure = () => {
        const media = stateValue.mediaData?.posts || [];
        if (!media.length) return;

        const selectedIds = stateValue.bulkSubmitData?.ids || [];
        const filteredData = media.filter(item => selectedIds.includes(item.ID));

        const updatedData = filteredData.map(
            ({ ID, url, post_title, post_name, post_excerpt, post_content, alt_text, custom_meta }) => {
                const flatMeta = custom_meta || {};
                const row = {
                    ID,
                    slug: post_name,
                    url,
                    title: post_title,
                    caption: post_excerpt,
                    description: post_content,
                    alt_text,
                    ...flatMeta,
                };
                return escapeValues(row);
            }
        );

        const csv = Papa.unparse(updatedData, {
            quotes: true,
        });

        setCsvData(csv);
    };

    useEffect(() => {
        generateCSVStructure();
    }, []);

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
                <button onClick={downloadCSV} style={buttonStyle}>
                    Download CSV
                </button>
            )}
        </>
    );
}

export default DownloadCSV;