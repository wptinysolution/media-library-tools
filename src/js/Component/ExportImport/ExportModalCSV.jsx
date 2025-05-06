import React, { useState, useEffect } from "react";

import {Divider, Modal, Button, Checkbox} from 'antd';

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";
import {initialState} from "../../Utils/reducer";
import ExportCSV from "./ExportCSV";

function ExportModalCSV(props) {

    const [stateValue, dispatch] = useStateValue();

    const bulkExportData = stateValue.exportImport;
    const defaultKeys = initialState.bulkExport.selectedKeys;

    const [selectedKeys, setSelectedKeys] = useState(defaultKeys);

    const filteredData = bulkExportData?.mediaFiles || [];

    const REQUIRED_KEYS = ['ID', 'slug'];

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

    /**
     * @param event
     */
    const handleBulkModalCancel = () => {
        dispatch({
            type: Types.EXPORT_CSV,
            bulkExport: {
                ...bulkExportData,
                isModalOpen: false,
            },
        });
    };
    useEffect(() => {
        dispatch({
            type: Types.EXPORT_CSV,
            bulkExport: {
                ...bulkExportData,
                selectedKeys: selectedKeys
            },
        });
    }, [selectedKeys]);

    const keys = getSelectedKeysWithMeta();
    return (
        <Modal
            title={`Download CSV`}
            open={props.isModalOpen}
            onCancel={handleBulkModalCancel}
            footer={[
                <Button key="cancel" onClick={handleBulkModalCancel}> Cancel </Button>,
                <ExportCSV key="download"/>
            ]}
            style={{
                maxWidth: "650px"
            }}
            width="100%"
        >
            <hr/>
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
                    style={{width: '100%'}}
                    onChange={setSelectedKeys}
                >
                    {keys.map((key) => (
                        <div key={key} style={{marginBottom: 8}}>
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
        </Modal>
    )
}

export default ExportModalCSV;