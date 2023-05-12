import React from 'react';

import { useStateValue } from '../Utils/StateProvider';

import Loader from '../Utils/Loader';

import {
    Form,
    Input,
    Layout,
    Button,
    Divider,
    Checkbox,
    Typography
} from 'antd';

const { Title, Text } = Typography;

const { Content } = Layout;

import { columnList } from '../Utils/UtilData'

import * as Types from "../Utils/actionType";

const CheckboxGroup = Checkbox.Group;

const columns = columnList.map( ( currentValue) => {
    return {
        label: currentValue.title,
        value: currentValue.key
    }
} );

const plainOptions = columnList.map( ( currentValue) => {
    return currentValue.key;
} );


function Settings() {

   const [stateValue, dispatch] = useStateValue();

    const isCheckedDiff = Object.keys( plainOptions ).length === Object.keys( stateValue.options.media_table_column ).length;

    const onChangeColumnList = (list) => {
        dispatch({
            type: Types.UPDATE_OPTIONS,
            options : {
                ...stateValue.options,
                media_table_column: list,
            }
        });

    };

    const onCheckAllColumn = (e) => {
        dispatch({
            type: Types.UPDATE_OPTIONS,
            options : {
                ...stateValue.options,
                media_table_column: e.target.checked ? plainOptions : [],
            }
        });
    };

    const defaultAltText = (e) => {
        dispatch({
            type: Types.UPDATE_OPTIONS,
            options : {
                ...stateValue.options,
                default_alt_text: stateValue.options.default_alt_text !== e.target.value ? e.target.value : '',
            }
        });

    }

    const onChangeOthersFileList = (list) => {
        dispatch({
            type: Types.UPDATE_OPTIONS,
            options : {
                ...stateValue.options,
                others_file_support: list,
            }
        });

    };

    return (
        <Form
            labelCol={{
                span: 5,
                offset: 0,
                style:{
                    textAlign: 'left',
                }
            }}
            wrapperCol={{ span: 12 }}
            layout="horizontal"
            style={{
                maxWidth: 900,
                padding: '15px',
                position: 'relative',
                height: '100%'
            }}
        >

            { stateValue.options.isLoading ? <Loader/> :
                <Content style={{
                    padding: '15px',
                    background: 'rgb(255 255 255 / 35%)',
                    borderRadius: '5px',
                    boxShadow: 'rgb(0 0 0 / 1%) 0px 0 20px',
                }}>
                    <Form.Item label={<Title level={5} style={{ margin:0, fontSize:'14px' }}> Media Table Column </Title>} >
                        <Checkbox indeterminate={ ! isCheckedDiff } onChange={onCheckAllColumn} checked={isCheckedDiff}>Check all </Checkbox>
                        <Divider />
                        <CheckboxGroup options={columns} value={stateValue.options.media_table_column} onChange={onChangeColumnList} />
                    </Form.Item>
                    <Divider />
                    <Form.Item label={<Title level={5} style={{ margin:0, fontSize:'14px' }}> Image Default Alt Text </Title>} >

                        <Checkbox
                            onChange={defaultAltText}
                            name={`default_alt_text`}
                            value={`none`}
                            checked={ 'none' === stateValue.options.default_alt_text }>
                            None
                        </Checkbox>

                        <Checkbox
                            onChange={defaultAltText}
                            name={`default_alt_text`}
                            value={`image_name_to_alt`}
                            checked={ 'image_name_to_alt' === stateValue.options.default_alt_text }>
                            Image name use As alt text
                        </Checkbox>
                        <Checkbox
                            onChange={defaultAltText}
                            name={`default_alt_text`}
                            value={`custom_text_to_alt`}
                            checked={ 'custom_text_to_alt' === stateValue.options.default_alt_text } >
                            Custom text
                        </Checkbox>
                        { 'custom_text_to_alt' === stateValue.options.default_alt_text &&
                            <>
                                <Divider />
                                <Input
                                    type="primary"
                                    size="large"
                                    onChange={
                                        (event) => dispatch({
                                            type: Types.UPDATE_OPTIONS,
                                            options : {
                                                ...stateValue.options,
                                                media_default_alt: event.target.value,
                                            }
                                        })
                                    }
                                    value={stateValue.options.media_default_alt}
                                />
                                <Text
                                    type="secondary"
                                >
                                    Alt Text Will add automatically when upload Media file
                                </Text>
                            </>
                        }

                    </Form.Item>
                    <Form.Item label={<Title level={5} style={{ margin:0, fontSize:'14px' }}> Others File Support </Title>} >
                        <CheckboxGroup options={
                            [
                                {
                                    label: 'SVG',
                                    value: 'svg'
                                }
                            ]
                        } value={stateValue.options.others_file_support} onChange={ onChangeOthersFileList } />
                    </Form.Item>

                </Content>
            }
            <Button
                type="primary"
                size="large"
                style={{
                    position: 'absolute',
                    bottom: '10px'
                }}
                onClick={ () => dispatch({
                    ...stateValue,
                    type: Types.UPDATE_OPTIONS,
                    saveType: Types.UPDATE_OPTIONS,
                }) } >
                Save Settings
            </Button>
        </Form>

    );
};

export default Settings;