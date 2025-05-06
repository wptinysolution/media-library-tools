import React, { useState, useEffect } from "react";

import {Divider, Modal, Button, Checkbox} from 'antd';

import DownloadCSV from "../ExportImport/DownloadCSV";

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

function BulkModalForCSV() {

    const [stateValue, dispatch] = useStateValue();
    const [IsButtonDisabled, setIsButtonDisabled] = useState(true);

    const bulkExportData = stateValue.bulkExport;
    const defaultKeys = stateValue.bulkExport.selectedKeys;

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
    /**
     * @param event
     */
    const handleBulkModalOk = () => {
        console.log( 'bulkExportData', bulkExportData );
    };

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
            open={bulkExportData.isModalOpen}
            onOk={handleBulkModalOk}
            onCancel={handleBulkModalCancel}
            okButtonProps={{disabled: IsButtonDisabled}}
            okText="Done"
            footer={[
                <Button key="cancel" onClick={handleBulkModalCancel}> Cancel </Button>,
                <DownloadCSV key="download"/>
            ]}
            style={{
                maxWidth: "650px"
            }}
            width="100%"
        >
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
            <br/>

            <Divider/>
        </Modal>
    )
}

export default BulkModalForCSV;