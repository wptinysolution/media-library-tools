import React, { useState } from 'react';

import {useStateValue} from "../../Utils/StateProvider";

import {
    Tooltip,
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

import * as Types from "../../Utils/actionType";

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
    let sizes  = stateValue.options?.custom_image_sizes || defaultSize;
    const [ deleteIconColor, setDeleteIconColor] = useState( 'var(--tsmlt-admin-color-secondary)' );
    sizes = sizes.length > 0 ? sizes : defaultSize;
    /**
     * @returns {Promise<void>}
     */
    const registerImageSize = (index, key, value) => {
        if ( ! tsmltParams.hasExtended ){
            dispatch({
                type: Types.GENERAL_DATA,
                generalData: {
                    ...stateValue.generalData,
                    openProModal: true,
                },
            });
            return;
        }
        let val = 'sizeKey' === key ? value.replace(/\s+/g, '_') : value ;
        const updatedSizes = sizes.map((size, i) => {
            return i === index ? { ...size, [key]: val } : size;
        } );
        dispatch({
            type: Types.UPDATE_OPTIONS,
            options : {
                ...stateValue.options,
                custom_image_sizes: updatedSizes,
            }
        });
    }
    /**
     * Add New Image Size
     */
    const addNewImageSize = () => {
        if ( ! tsmltParams.hasExtended ){
            dispatch({
                type: Types.GENERAL_DATA,
                generalData: {
                    ...stateValue.generalData,
                    openProModal: true,
                },
            });
            return;
        }
        const validSizes = sizes.filter( size => size?.sizeKey );
        dispatch({
            type: Types.UPDATE_OPTIONS,
            options : {
                ...stateValue.options,
                custom_image_sizes: [ ...validSizes, ...defaultSize],
            }
        });
    }

    /**
     * Add New Image Size
     */
    const deleteImageSize = ( sizeKey ) => {
        if ( ! tsmltParams.hasExtended ){
            dispatch({
                type: Types.GENERAL_DATA,
                generalData: {
                    ...stateValue.generalData,
                    openProModal: true,
                },
            });
            return;
        }
        const validSizes = sizes.filter( size => sizeKey !== size?.sizeKey );
        dispatch({
            type: Types.UPDATE_OPTIONS,
            options : {
                ...stateValue.options,
                custom_image_sizes: validSizes,
            }
        });
    }

    return (
        <>
            <Row>
                <Col span={6}>
                    <Title level={5} style={{ margin:0 }}> Register New Image Size { ! tsmltParams.hasExtended && <span style={ { color: '#ff0000', fontWeight: 'bold' } }> - PRO</span> } </Title>
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
                                        <Tooltip title={`tsmlt_${sizes[index]?.sizeKey || ''}`} >
                                            <Input
                                                disabled={false}
                                                style={{
                                                    width: 300,
                                                }}
                                                value={ sizes[index]?.sizeKey || null }
                                                addonBefore={`Size Key: tsmlt_`}
                                                placeholder="size-name"
                                                onChange={ (event) => registerImageSize( index, 'sizeKey', event.target.value ) }
                                            />
                                        </Tooltip>

                                        <Text
                                            copyable={{
                                                text: `tsmlt_${sizes[index]?.sizeKey || ''}`,
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
                                                right: '10px',
                                                margin: 'auto',
                                                height: '20px',
                                                color: deleteIconColor
                                            }}
                                            onClick={(event) => deleteImageSize( sizes[index]?.sizeKey )}
                                            onMouseEnter={() => setDeleteIconColor('var(--tsmlt-admin-color-secondary-hover)')}
                                            onMouseLeave={() => setDeleteIconColor('var(--tsmlt-admin-color-secondary)')}
                                        />
                                    </Flex>
                                    <Divider style={{margin:'10px 0'}}/>
                                </Content>
                            );
                        })
                    }

                    <Button
                        onClick={ () => addNewImageSize() }
                        type="primary"> Add New Size
                    </Button>
                </Col>
            </Row>
         <Divider />
        </>
    );
}

export default RegisterSize;