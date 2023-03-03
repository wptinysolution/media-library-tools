import React, {useContext, useRef, useState} from 'react';
import {TheAppContext, TheMediaTableContext} from "../Utils/TheContext";
import {
    Form,
    Layout,
    InputNumber,
    Typography
} from 'antd';
const { Title } = Typography;
const {
    Content,
    Footer
} = Layout;

function Settings() {
    const {
        dateList,
        termsList,
        optionsData,
        setOptionsData,
        handleUpdateOption
    } = useContext( TheAppContext );

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
            }}
        >
            <Content  className="tttme-App">

                <Form.Item label={<Title level={5} style={{
                    margin:0
                }}> Items Per page </Title>} style={{
                        marginBottom:'10px',
                        padding: '15px',
                        background: 'rgb(255 255 255 / 35%)',
                        borderRadius: '5px',
                        boxShadow: 'rgb(0 0 0 / 1%) 0px 0 20px',
                    }}>
                        <InputNumber
                            type="primary"
                            size="large"
                            style={{
                                width: '80px',
                                marginLeft: '80px',
                                background: '#f7fafc',
                                borderRadius: '4px',
                                border: '1px solid #d9e3ed'
                            }}
                            onBlur={ handleUpdateOption }
                            onChange={
                                (event) => setOptionsData({
                                    ...optionsData,
                                    media_per_page : event.target.value,
                                })
                            }
                            value={optionsData.media_per_page}
                        />
                    </Form.Item>
                <Form.Item label={<Title level={5} style={{
                    margin:0
                }}> Items Per page </Title>} style={{
                    marginBottom:'10px',
                    padding: '15px',
                    background: 'rgb(255 255 255 / 35%)',
                    borderRadius: '5px',
                    boxShadow: 'rgb(0 0 0 / 1%) 0px 0 20px',
                }}>
                        <InputNumber
                            type="primary"
                            size="large"
                            style={{
                                width: '80px',
                                marginLeft: '80px',
                                background: '#f7fafc',
                                borderRadius: '4px',
                                border: '1px solid #d9e3ed'
                            }}
                            onBlur={ handleUpdateOption }
                            onChange={
                                (event) => setOptionsData({
                                    ...optionsData,
                                    media_per_page : event.target.value,
                                })
                            }
                            value={optionsData.media_per_page}
                        />
                    </Form.Item>
            </Content>
        </Form>

    );
};

export default Settings;