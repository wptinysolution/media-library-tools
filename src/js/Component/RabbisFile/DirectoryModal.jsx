import React from "react";

import {Divider, Input, Modal, List , Layout, Typography, Button} from 'antd';

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

const {  Content } = Layout;

const { Title } = Typography;

const { TextArea } = Input;

function DirectoryModal() {

    const [stateValue, dispatch] = useStateValue();

    const handleDirModalOk = () => {
        dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                isDirModalOpen: false,
            },
        });
    };

    const handleDirModalCancel = () => {
        dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                isDirModalOpen: false
            },
        });
    };

    const handleDirModalRescan = () => {

    };


    return (
        <Modal
            title={`Directory List`}
            open={ stateValue.generalData.isDirModalOpen }
            footer={[
                <Button key="rescan" onClick={handleDirModalRescan}> Re-Scan  </Button>,
                <Button key="back" onClick={handleDirModalCancel}> Cancel  </Button>,
                <Button key="submit" type="primary"  onClick={handleDirModalOk}> Submit </Button>,
            ]}
        >
            <Divider />
            <Content>
                <List
                    itemLayout="horizontal"
                    dataSource={ Object.entries( stateValue.generalData.scanRabbisDirList ) }
                    renderItem={ ( [key, item], index) => (
                        <List.Item>
                            {   console.log( item.total_items ) }
                            <List.Item.Meta
                                title={ key }
                                description={ `Found ${item.total_items} items, And Checked ${item.counted} items` }
                            />
                        </List.Item>
                    ) }
                />
            </Content>
            <Divider />
        </Modal>
    )
}
export default DirectoryModal;