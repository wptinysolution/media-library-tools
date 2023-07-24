import React from "react";

import { Divider, Input, Modal, Select, Layout, Typography } from 'antd';

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
                isDirModalOpen: false,
            },
        });
    };
    return (
        <Modal
            title={`Directory List`}
            open={ stateValue.generalData.isDirModalOpen }
            onOk={handleDirModalOk}
            onCancel={handleDirModalCancel}
        >
            <Divider />
            <Content>
                Lorem ipsum dolor sit amet, consectetur adipisicing elit. Amet dolores earum eveniet ipsam non perferendis quae? Architecto asperiores, assumenda deserunt dolores, dolorum excepturi fugit natus nisi odit tempora ullam, voluptate!
            </Content>
            <Divider />
        </Modal>
    )
}
export default DirectoryModal;