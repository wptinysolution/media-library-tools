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

const { Title, Text } = Typography;

const { Content } = Layout;

/**
 *
 * @returns {JSX.Element}
 * @constructor
 */
function RegisterSize() {
    const [ stateValue, dispatch ] = useStateValue();
    const sizes  = stateValue.options?.customImageSizes || [] ;
    const [ deleteIconColor, setDeleteIconColor] = useState( 'var(--tsmlt-admin-color-secondary)' );
    return (
        <>
            <Row>
                <Col span={6}>
                    <Title level={5} style={{ margin:0 }}> Register New Image Size </Title>
                </Col>
                <Col span={18}>
                    {
                        Object.keys(sizes).map((item, index) => {
                            const uniqKey = sizes[item].length > 0 ? sizes[item] : '';
                            const isDisable = sizes[item].length > 0;

                            return (
                                <Content key={index}>
                                    <Flex
                                        key={index}
                                        gap={'small'}
                                        style={{ marginBottom: '10px' }}
                                        align={'center'}
                                    >
                                        <Input
                                            disabled={isDisable}
                                            style={{
                                                width: 300,
                                            }}
                                            value={uniqKey}
                                            addonBefore={'Size Key'}
                                            placeholder="size-name"
                                        />

                                        <Text
                                            copyable={{
                                                text: uniqKey || 'size-name',
                                            }}
                                        />
                                        <InputNumber
                                            style={{
                                                width: 200,
                                            }}
                                            addonBefore={'Width'}
                                            addonAfter={'px'}
                                            min={0}
                                        />
                                        <InputNumber
                                            style={{
                                                width: 200,
                                            }}
                                            addonBefore={'Height'}
                                            addonAfter={'px'}
                                            min={0}
                                        />
                                        <Switch checked={true} checkedChildren="Hard Crop" unCheckedChildren="Soft Crop" />
                                        <DeleteOutlined
                                            style={{
                                                position: 'absolute',
                                                right: '50px',
                                                margin: 'auto',
                                                height: '20px',
                                                color: deleteIconColor
                                            }}
                                            onClick={(event) => console.log('delete')}
                                            onMouseEnter={() => setDeleteIconColor('var(--tsmlt-admin-color-secondary-hover)')}
                                            onMouseLeave={() => setDeleteIconColor('var(--tsmlt-admin-color-secondary)')}
                                        />
                                    </Flex>
                                    <Divider style={{margin:'10px 0'}}/>
                                </Content>
                            );
                        })
                    }
                    <Button type="primary"> Add New Size </Button>
                </Col>
            </Row>
         <Divider />
        </>
    );
}

export default RegisterSize;