import React, { useEffect, useState } from 'react';
import { useStateValue } from '../../Utils/StateProvider';
import Papa from 'papaparse';
import { Checkbox } from 'antd';
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
    const defaultKeys = [
        'ID', 'post_name', 'url', 'post_title', 'post_excerpt', 'post_content', 'alt_text'
    ];
    const [selectedKeys, setSelectedKeys] = useState(defaultKeys);

    const media = stateValue.mediaData?.posts || [];
    const selectedIds = stateValue.bulkSubmitData?.ids || [];
    const REQUIRED_KEYS = ['ID', 'post_name'];
    const filteredData = media.filter(item => selectedIds.includes(item.ID));

    function getSelectedKeysWithMeta() {
        const item = filteredData[0];
        const keys = [];
        // Loop through selected keys and add them to the list
        defaultKeys.forEach((key) => {
            if (item.hasOwnProperty(key)) {
                keys.push(key);
            }
        });
        // Add custom_meta keys (flattened)
        if (item.custom_meta) {
            const meta = item.custom_meta || {};
            for (const metaKey in meta) {
                keys.push(metaKey);
            }
        }
        return keys;
    }

    const generateCSVStructure = () => {

        const updatedData = filteredData.map(item => {
            const flatMeta = item.custom_meta || {};

            const fullRow = {
                ID: item.ID,
                post_name: item.post_name,
                url: item.url,
                post_title: item.post_title,
                post_excerpt: item.post_excerpt,
                post_content: item.post_content,
                alt_text: item.alt_text,
                ...flatMeta,
            };

            const finalKeys = Array.from(new Set(['ID', 'post_name', ...selectedKeys]));
            const filteredRow = {};

            finalKeys.forEach(key => {
                if (key in fullRow) {
                    filteredRow[key] = fullRow[key];
                }
            });

            return escapeValues(filteredRow);
        });

        console.log('updatedData', updatedData );

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
    console.log('selectedKeys', selectedKeys );

    const keys = getSelectedKeysWithMeta();

    return (
        <>
            <h2> Select which data will be exported to CSV </h2>
            <div style={{
                maxHeight: '300px',
                overflowY: 'auto',
                border: '1px solid #d9d9d9',
                borderRadius: '8px',
                padding: '12px',
                background: '#fafafa',
            }}>
                <Checkbox.Group
                    value={selectedKeys}
                    style={{ width: '100%' }}
                    onChange={setSelectedKeys}
                >
                    {keys.map((key) => (
                        <div key={key} style={{ marginBottom: 8 }}>
                            <Checkbox
                                value={key}
                                disabled={REQUIRED_KEYS.includes(key)}
                            >
                                {key}
                            </Checkbox>
                        </div>
                    ))}
                </Checkbox.Group>
            </div>
            <br/>
            {csvData && (
                <button onClick={downloadCSV} style={buttonStyle}>
                    Download CSV
                </button>
            )}
        </>
    );
}

export default DownloadCSV;