import React from "react";

import {Typography, Layout, Button, Space } from 'antd';

import { headerStyle } from "../../Utils/UtilData";

import { useStateValue } from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

const { Header } = Layout;

const { Title } = Typography;

function RabbisHeader() {

    const [ stateValue, dispatch ] = useStateValue();

    const handleDirModal = () => {
        dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                isDirModalOpen: true,
                scanDir: null
            },
        });
    };

    return (
        <Header style={{...headerStyle, height: 'inherit'}}>
            <Space wrap>
                <Button
                    style={{
                        width: '200px'
                    }}
                    type="primary"
                    size="large"
                    ghost={ 1 }>
                    { 'Delete Selected File Pro' }
                </Button>
                <Button
                    style={{
                        width: '200px'
                    }}
                    type="primary"
                    size="large"
                    onClick={handleDirModal}
                    ghost={ ! stateValue.generalData.isDirModalOpen }>
                    { `Directory List` }

                </Button>

                <Title level={5} style={{
                    margin:'0 15px',
                    color: 'red'
                }}> Rabbis File Note : A "Rabbis File" refers to a file that exists within a directory but is not included in the media library or database. </Title>
            </Space>

        </Header>
    );
}

export default RabbisHeader;