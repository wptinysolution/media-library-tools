import React, { useState } from "react";

import {Divider, Input, Modal, Select, Layout, Typography, Form, Checkbox, Progress, Button} from 'antd';

import DownloadCSV from "../ExportImport/DownloadCSV";

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

function BulkModalForCSV() {

    const [stateValue, dispatch] = useStateValue();
    const [IsButtonDisabled, setIsButtonDisabled] = useState(true);

    const bulkExportData = stateValue.bulkExport;

    console.log( bulkExportData );

    /**
     * @param event
     */
    const handleBulkModalOk = () => {
        console.log( bulkExportData );
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
                <Button key="rescan"> Cancel </Button>,
            ]}
            style={{
                maxWidth: "650px"
            }}
            width="100%"
        >
            <Divider />
                <DownloadCSV/>
                Lorem ipsum dolor sit amet, consectetur adipisicing elit. Aperiam architecto corporis cum, earum eveniet molestiae nam, nihil possimus quod sed suscipit totam velit voluptas! Aspernatur autem dolorem expedita libero voluptatibus!
            <Divider />
        </Modal>
    )
}
export default BulkModalForCSV;