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

const { Title, Text } = Typography;

const { Content } = Layout;

const allSizes = {
    '11': 'Apple',
    '22': 'Pear',
    '33': 'Orange',
    '' : ''
};

const defaultCheckedList = [
    '22',
    '33'
];

/**
 *
 * @returns {JSX.Element}
 * @constructor
 */
function ImageSize() {
    const [checkedList, setCheckedList] = useState(defaultCheckedList);
    const [ deleteIconColor, setDeleteIconColor] = useState( 'var(--tsmlt-admin-color-secondary)' );
    const onCheckbox = (e, item) => {
        let val = e.target.checked ? [...checkedList, item] : checkedList.filter(i => i !== item) ;
        val = [...new Set(val)];
        setCheckedList(val);
    };

    return (
        <>
            <MainHeader/>
            <Content style={{
                padding: '25px',
                background: 'rgb(255 255 255 / 35%)',
                borderRadius: '5px',
                boxShadow: 'rgb(0 0 0 / 1%) 0px 0 20px',
            }}>
                <Title level={3} style={{ margin:0 }}> Media Table Settings </Title>
                <Divider />
                <Row>
                    <Col span={6}>
                        <Title level={5} style={{ margin:0 }}> Register New Image Size </Title>
                    </Col>
                    <Col span={18}>
                        {
                            Object.keys(allSizes).map((item, index) => {
                                const uniqKey = allSizes[item].length > 0 ? allSizes[item] : '';
                                const isDisable = allSizes[item].length > 0;
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
                                                text: uniqKey,
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
                <Row>
                    <Col span={6}>
                        <Title level={5} style={{ margin:0 }}> Disable Registered Image Size </Title>
                    </Col>
                    <Col span={14}>
                        <Title level={5} style={{ margin:0 }}> Image Size </Title>
                        <br/>
                        <Flex gap={'small'} vertical={true}>
                        {
                            Object.keys(allSizes).map((item, index) => {
                                return (
                                    <Checkbox
                                        key={index}
                                        checked={ checkedList.includes(item) }
                                        onChange={ (e) => onCheckbox(e, item) }
                                    >
                                        {allSizes[item]}
                                    </Checkbox>
                                );
                            })
                        }
                        </Flex>
                    </Col>
                </Row>
                </Content>
        </>
    );
}

export default ImageSize;