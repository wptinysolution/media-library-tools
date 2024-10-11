import React, { useEffect, useState } from 'react';

import {useStateValue} from "../../Utils/StateProvider";
import MainHeader from "../MainHeader";

import {Checkbox, Col, Divider, Flex, Layout, Row, Typography} from 'antd';
const { Title, Text } = Typography;

const { Content } = Layout;

const allSizes = {
    '11': 'Apple',
    '22': 'Pear',
    '33': 'Orange'
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

    const onChange = (e, item) => {
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
                    <Col span={8}>
                        <Title level={5} style={{ margin:0 }}> Disable Registered Image Size </Title>
                    </Col>
                    <Col span={8}>
                        <Title level={5} style={{ margin:0 }}> Image Size </Title>
                        <br/>
                        <Flex gap={'small'} vertical={true}>
                        {
                            Object.keys(allSizes).map((item, index) => {
                                return (
                                    <Checkbox
                                        key={index}
                                        checked={ checkedList.includes(item) }
                                        onChange={ (e) => onChange(e, item) }
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