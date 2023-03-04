import React, {useContext, useEffect, useRef, useState} from 'react';
import {TheAppContext, TheMediaTableContext} from "../Utils/TheContext";

import {
    Checkbox,
    Divider,
    Form,
    Layout,
    Typography, Button
} from 'antd';

const { Title } = Typography;
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
                <Form.Item label={<Title level={5} style={{ margin:0 }}> Media Table Column </Title>} style={{
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
        </Form>

    );
};

export default Settings;