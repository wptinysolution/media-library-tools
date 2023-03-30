import React, {useContext, useEffect, useRef, useState} from 'react';
import {TheAppContext, TheMediaTableContext} from "../Utils/TheContext";

import {
    Checkbox,
    Divider,
    Form,
    Layout,
    Typography, Button, Input
} from 'antd';

const { Title, Text } = Typography;
const {
    Content
} = Layout;

import {
    columnList
} from '../Utils/UtilData'


const CheckboxGroup = Checkbox.Group;

const columns = columnList.map( ( currentValue) => {
    let data = {
        label: currentValue.title,
        value: currentValue.key
    }
    return data;
} );

const plainOptions = columnList.map( ( currentValue) => {
    return currentValue.key;
} );


function Settings() {
    const {
        optionsData,
        setOptionsData,
        handleUpdateOption
    } = useContext( TheAppContext );

    const defaultCheckedList = plainOptions.filter( ( currentValue) => {
        if( ! optionsData.media_table_column ){
            return true;
        }
        return optionsData.media_table_column.includes( `${currentValue}` );
    } );

    const isCheckedDiff = Object.keys(defaultCheckedList).length === Object.keys(plainOptions).length;
    const [checkedList, setCheckedList] = useState( defaultCheckedList );
    const [indeterminate, setIndeterminate] = useState( ! isCheckedDiff );
    const [checkAll, setCheckAll] = useState( isCheckedDiff );

    // const [ defaulrAlt, setDefaulrAlt ] = useState( false );

    useEffect(() => {
        setCheckedList( defaultCheckedList );
    }, [optionsData] );

    const onChange = (list) => {
        setCheckedList(list);
        setIndeterminate(!!list.length && list.length < plainOptions.length);
        setCheckAll(list.length === plainOptions.length);
        setOptionsData((prevState) => {
            return {
                ...prevState,
                media_table_column: list,
            };
        });
    };

    const onCheckAllChange = (e) => {
        setCheckedList(e.target.checked ? plainOptions : []);
        setIndeterminate(false);
        setCheckAll(e.target.checked);
        setOptionsData({
            ...optionsData,
            media_table_column: e.target.checked ? plainOptions : [],
        })
    };

    const defaultAltText = (e) => {
        // setDefaulrAlt(e.target.value)
        setOptionsData({
            ...optionsData,
            default_alt_text: e.target.value,
        })
        // defaulrAlt,

    }
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
            <Content >
                <Form.Item label={<Title level={5} style={{ margin:0, fontSize:'14px' }}> Media Table Column </Title>} style={{
                        marginBottom:'10px',
                        padding: '15px',
                        background: 'rgb(255 255 255 / 35%)',
                        borderRadius: '5px',
                        boxShadow: 'rgb(0 0 0 / 1%) 0px 0 20px',
                    }}>
                    <Checkbox indeterminate={indeterminate} onChange={onCheckAllChange} checked={checkAll}>
                        Check all
                    </Checkbox>
                    <Divider />
                    <CheckboxGroup options={columns} value={checkedList} onChange={onChange} />
                </Form.Item>
                <Divider />
                <Form.Item label={<Title level={5} style={{ margin:0, fontSize:'14px' }}> Image Default Alt Text </Title>}>
                    <Checkbox onChange={defaultAltText} name={`default_alt_text`} value={`image_name_to_alt`} checked={ 'image_name_to_alt' === optionsData.default_alt_text }> Image name use As default alt text </Checkbox>
                    <Checkbox onChange={defaultAltText} name={`default_alt_text`} value={`custom_text_to_alt`} checked={ 'custom_text_to_alt' === optionsData.default_alt_text } > Custom Alt text </Checkbox>
                    { 'custom_text_to_alt' === optionsData.default_alt_text &&
                        <>
                            <Divider />
                            <Input
                                type="primary"
                                size="large"
                                onChange={
                                    (event) => setOptionsData({
                                        ...optionsData,
                                        media_default_alt: event.target.value,
                                    })
                                }
                                value={optionsData.media_default_alt}
                            />
                            <Text type="secondary"> Alt Text Will add automatically when upload image</Text>
                        </>
                    }

                </Form.Item>

            </Content>
            <Button
                type="primary"
                size="large"
                style={{
                    position: 'absolute',
                    bottom: '10px'
                }}
                onClick={ handleUpdateOption}
            > Save Settings </Button>
            {/*{ console.log( optionsData ) }*/}
        </Form>

    );
};

export default Settings;