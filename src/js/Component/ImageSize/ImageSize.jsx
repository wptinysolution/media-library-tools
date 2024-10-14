import React, { useEffect, useState } from 'react';

import {useStateValue} from "../../Utils/StateProvider";
import MainHeader from "../MainHeader";

import {
    Checkbox,
    Col,
    Divider,
    Flex,
    Input,
    InputNumber,
    Layout,
    Switch,
    Row,
    Typography,
    Button
} from 'antd';
import {DeleteOutlined} from "@ant-design/icons";
import {getOptions, getRegisteredImageSizes} from "../../Utils/Data";
import * as Types from "../../Utils/actionType";
import Loader from "../../Utils/Loader";
import RegisterSize from "./RegisterSize";
import DisableSize from "./DisableSize";

const { Title, Text } = Typography;

const { Content } = Layout;

/**
 *
 * @returns {JSX.Element}
 * @constructor
 */
function ImageSize() {

    const [ stateValue, dispatch ] = useStateValue();

    return (
        <>
            <MainHeader/>
            { stateValue.generalData.isLoading  ? <Loader/> :
                <Content style={{
                    padding: '25px',
                    background: 'rgb(255 255 255 / 35%)',
                    borderRadius: '5px',
                    boxShadow: 'rgb(0 0 0 / 1%) 0px 0 20px',
                }}>
                    <Title level={3} style={{ margin:0 }}> Media Table Settings </Title>
                    <Divider />
                    { tsmltParams.hasExtended ? <RegisterSize/> : null }
                    <DisableSize/>
                    <Button
                        type="primary"
                        size="large"
                        onClick={ () => dispatch({
                            ...stateValue,
                            type: Types.UPDATE_OPTIONS,
                            saveType: Types.UPDATE_OPTIONS,
                        }) } >
                        Save Settings
                    </Button>
                </Content>
            }
        </>
    );
}

export default ImageSize;