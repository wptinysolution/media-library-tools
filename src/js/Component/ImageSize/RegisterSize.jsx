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

const defaultSize = [
    {
        'sizeKey' : '',
        'width' : '',
        'height' : '',
        'hardCrop' : true,
    }
];
/**
 *
 * @returns {JSX.Element}
 * @constructor
 */
function RegisterSize() {
    const [ stateValue, dispatch ] = useStateValue();
    const sizes  = stateValue.options?.custom_image_sizes || defaultSize;
    const [ deleteIconColor, setDeleteIconColor] = useState( 'var(--tsmlt-admin-color-secondary)' );

    /**
     * @returns {Promise<void>}
     */
    const registerImageSize = (index, key, value) => {
        const updatedSizes = sizes.map((size, i) => {
            return i === index ? { ...size, [key]: value } : size;
        } );
        dispatch({
            type: Types.UPDATE_OPTIONS,
            options : {
                ...stateValue.options,
                custom_image_sizes: updatedSizes,
            }
        });
    }
    return (
        <>
            <Row>
                <Col span={6}>
                    <Title level={5} style={{ margin:0 }}> Register New Image Size </Title>
                </Col>
                <Col span={18}>
                    {
                        Object.keys(sizes).map((item, index) => {

                            return (
                                <Content key={index}>
                                    <Flex
                                        key={index}
                                        gap={'small'}
                                        style={{ marginBottom: '10px' }}
                                        align={'center'}
                                    >
                                        <Input
                                            disabled={false}
                                            style={{
                                                width: 300,
                                            }}
                                            value={ sizes[index]?.sizeKey || null }
                                            addonBefore={'Size Key'}
                                            placeholder="size-name"
                                            onChange={ (event) => registerImageSize( index, 'sizeKey', event.target.value ) }
                                        />

                                        <Text
                                            copyable={{
                                                text: sizes[index]?.sizeKey || '',
                                            }}
                                        />
                                        <InputNumber
                                            style={{
                                                width: 200,
                                            }}
                                            value={sizes[index]?.width || null }
                                            addonBefore={'Width'}
                                            addonAfter={'px'}
                                            min={0}
                                            onChange={ ( value ) => registerImageSize( index, 'width', value ) }
                                        />
                                        <InputNumber
                                            style={{
                                                width: 200,
                                            }}
                                            value={sizes[index]?.height || null }
                                            addonBefore={'Height'}
                                            addonAfter={'px'}
                                            min={0}
                                            onChange={ (value) => registerImageSize( index, 'height', value ) }
                                        />
                                        <Switch
                                            checked={ sizes[index]?.hardCrop || false }
                                            checkedChildren="Hard Crop"
                                            unCheckedChildren="Soft Crop"
                                            onChange={ ( checked ) => registerImageSize( index, 'hardCrop', checked ) }
                                        />
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