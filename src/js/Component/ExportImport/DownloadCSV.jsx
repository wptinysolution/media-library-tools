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
    const media = stateValue.mediaData?.posts || [];
    const selectedIds = stateValue.bulkSubmitData?.ids || [];

    const filteredData = media.filter(item => selectedIds.includes(item.ID));

    function getSelectedKeysWithMeta() {
        const item = filteredData[0];
        const selectedKeys = [
            'ID', 'post_name', 'url', 'post_title', 'post_excerpt', 'post_content', 'alt_text'
        ];
        const keys = [];
        // Loop through selected keys and add them to the list
        selectedKeys.forEach((key) => {
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
    console.log('getAllKeysFromFirstItem', getSelectedKeysWithMeta() );

    const keys = getSelectedKeysWithMeta();

    return (
        <>
            <div style={{
                maxHeight: '300px',
                overflowY: 'auto',
                border: '1px solid #d9d9d9',
                borderRadius: '8px',
                padding: '12px',
                background: '#fafafa',
            }}>
                <Checkbox.Group
                    style={{ width: '100%' }}
                >
                    {keys.map((key) => (
                        <div key={key} style={{ marginBottom: 8 }}>
                            <Checkbox value={key}>{key}</Checkbox>
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