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

const { Title, Text } = Typography;

const { Content } = Layout;

/**
 *
 * @returns {JSX.Element}
 * @constructor
 */
function DisableSize() {

    const [ stateValue, dispatch ] = useStateValue();

    const checkedList =  stateValue.options?.deregistered_image_sizes || [];
    const sizes = stateValue.generalData?.allImageSizes || [];
    /**
     * @returns {Promise<void>}
     */
    const getTheSizes = async () => {
        const response = await getRegisteredImageSizes();
        await dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                allImageSizes: response.data,
            },
        })
    }
    /**
     * @param e
     * @param item
     */
    const onCheckbox = (e, item) => {
        let val = e.target.checked ? [...checkedList, item] : checkedList.filter(i => i !== item) ;
        val = [...new Set(val)];
        dispatch({
            type: Types.UPDATE_OPTIONS,
            options : {
                ...stateValue.options,
                deregistered_image_sizes: val,
            }
        });

    };

    useEffect(() => {
        getTheSizes();
    }, [] );

    return (
        <>
            <Row>
                <Col span={6}>
                    <Title level={5} style={{ margin:0 }}> Disable Registered Image Size </Title>
                </Col>
                <Col span={14}>
                    <Title level={5} style={{ margin:0 }}> Image Size </Title>
                    <br/>
                    <Flex gap={'small'} vertical={true}>
                        {
                            Object.keys(sizes).map((item, index) => {
                                return (
                                    <div  key={index} >
                                        { sizes[item].length > 0 ?
                                            <Checkbox
                                                key={index}
                                                checked={ checkedList.includes( sizes[item] ) }
                                                onChange={ (e) => onCheckbox(e, sizes[item] ) }
                                            >
                                                {sizes[item]}
                                            </Checkbox> : null
                                        }
                                    </div>

                                );
                            })
                        }
                    </Flex>
                    <br/>
                    <Text style={{ color:'red'}}>
                        The selected image size will be deregistered and the image will not be generated. This will save server space. Please select only unnecessary image sizes.
                    </Text>
                </Col>
            </Row>
        </>
    );
}

export default DisableSize;