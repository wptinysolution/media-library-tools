import React, { useState } from "react";

import {Divider, Input, Modal, Select, Layout, Typography, Form, Checkbox, Progress, Button} from 'antd';

import DownloadCSV from "../ExportImport/DownloadCSV";

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

function BulkModalForCSV() {

    const [stateValue, dispatch] = useStateValue();
    const [IsButtonDisabled, setIsButtonDisabled] = useState(true);

    const bulkExportData = stateValue.bulkExport;

    console.log( 'bulkExportData kk', bulkExportData );

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

    return (
        <Modal
            title={ `Download CSV` }
            open={ bulkExportData.isModalOpen }
            onOk={handleBulkModalOk}
            onCancel={handleBulkModalCancel}
            okButtonProps={{ disabled: IsButtonDisabled }}
            okText="Done"
            footer={[
                <Button key="cancel" onClick={ handleBulkModalCancel }> Cancel </Button>,
            ]}
            style={{
                maxWidth: "650px"
            }}
            width="100%"
        >
            <Divider />
            <DownloadCSV/>
            <Divider />
        </Modal>
    )
}
export default BulkModalForCSV;