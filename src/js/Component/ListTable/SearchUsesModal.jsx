import React, { useState } from "react";

import {Divider, Modal, Button} from 'antd';

import DownloadCSV from "../ExportImport/DownloadCSV";

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

function SearchUsesModal() {

    const [stateValue, dispatch] = useStateValue();
    const [IsButtonDisabled, setIsButtonDisabled] = useState(true);

    const searchUses = stateValue.searchUses;

    /**
     * @param event
     */
    const handleBulkModalOk = () => {
        console.log( 'bulkExportData', searchUses );
    };

    /**
     * @param event
     */
    const handleBulkModalCancel = () => {
        dispatch({
            type: Types.SEARCH_USES,
            searchUses: {
                ...searchUses,
                isModalOpen: false,
            },
        });
    };

    return (
        <Modal
            title={ `Download CSV` }
            open={ searchUses.isModalOpen }
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
            Lorem ipsum dolor sit amet, consectetur adipisicing elit. Maiores necessitatibus reiciendis rerum sint? Error esse, est exercitationem expedita harum omnis quaerat quis quo sequi, sint sunt suscipit velit veritatis voluptatem.
            <Divider />
        </Modal>
    )
}
export default SearchUsesModal;