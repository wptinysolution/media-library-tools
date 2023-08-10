import React from "react";

import { Divider, Input, Modal, Layout, Typography } from 'antd';

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

const {  Content } = Layout;

const { Title } = Typography;

function BulkModal() {

    const [stateValue, dispatch] = useStateValue();

    const bulkSubmitdata = stateValue.bulkSubmitData;

    const balkModalDataChange = ( event ) => {
        const data = {
            ...bulkSubmitdata.data,
            [event.target.name] : event.target.value
        }
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...bulkSubmitdata,
                data
            },
        });
    };

    const handleBulkModalOk = () => {
        dispatch({
            ...stateValue,
            type: Types.BULK_SUBMIT,
            saveType: Types.BULK_SUBMIT
        });
    };

    const handleBulkModalCancel = () => {
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...bulkSubmitdata,
                isModalOpen: false,
            },
        });
    };

    console.log( bulkSubmitdata.data )

    return (
        <Modal
            title={`Bulk Rename`}
            open={ bulkSubmitdata.isModalOpen }
            onOk={handleBulkModalOk}
            onCancel={handleBulkModalCancel}
        >
            <Divider />
            <Content>
                <Title style={{marginTop:'0px'}} level={5}> File Name </Title>
                <Input
                    onChange={ balkModalDataChange }
                    name={`file_name`}
                    value={bulkSubmitdata.data.post_title}
                    placeholder={`File Name`}
                />
            </Content>
            <Divider />
        </Modal>
    )
}
export default BulkModal;