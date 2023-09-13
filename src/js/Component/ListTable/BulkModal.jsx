import React from "react";

import {Divider, Input, Modal, Select, Layout, Typography, Form, Checkbox} from 'antd';

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

const {  Content } = Layout;

const { Title, Text } = Typography;

const { TextArea } = Input;

const CheckboxGroup = Checkbox.Group;

function BulkModal() {

    const [stateValue, dispatch] = useStateValue();

    const bulkSubmitdata = stateValue.bulkSubmitData;
    /**
     * @param event
     */
    const balkModalDataChange = ( event ) => {
        const data = {
            ...bulkSubmitdata.data,
            [event.target.name] : event.target.value
        }
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...bulkSubmitdata,
                data
            },
        });
    };
    /**
     * @param event
     */
    const handleBulkModalOk = () => {
        dispatch({
            ...stateValue,
            type: Types.BULK_SUBMIT,
            saveType: Types.BULK_SUBMIT
        });
    };
    /**
     * @param event
     */
    const handleBulkModalCancel = () => {
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...bulkSubmitdata,
                isModalOpen: false,
            },
        });
    };
    /**
     * @param event
     */
    const onChangeAddTextByPostTitle = ( list ) => {
        console.log( list )
        // const data = {
        //     ...bulkSubmitdata.data,
        //     [event.target.name] : event.target.value
        // }
        // dispatch({
        //     type: Types.BULK_SUBMIT,
        //     bulkSubmitData: {
        //         ...bulkSubmitdata,
        //         data
        //     },
        // });
    };


    const filteredOptions = stateValue.generalData.termsList.filter( ( item ) => ! stateValue.bulkSubmitData.post_categories.includes( item.value ) );

    return (
        <Modal
            title={ 'yes' === stateValue.bulkSubmitData.edit_by_attached_post_title ? `Bulk Assign` : `Bulk Edit` }
            open={ bulkSubmitdata.isModalOpen }
            onOk={handleBulkModalOk}
            onCancel={handleBulkModalCancel}
            okText="Done"
            style={{
                maxWidth: "650px"
            }}
            width="100%"
        >
            <Divider />
            { 'yes' === stateValue.bulkSubmitData.edit_by_attached_post_title ?
                <Content>
                    <Text >
                        Are you certain about performing a bulk assignment based on the associated post title?
                    </Text>
                    <Divider style={{  margin: '20px 0' }}/>
                    <Form
                        labelCol={{
                            span:7,
                            offset: 0,
                            style:{
                                textAlign: 'left',
                            }
                        }}
                        layout="horizontal"
                        style={{
                            height: '100%'
                        }}
                    >
                        <Form.Item label={<Title level={5} style={{ margin:0, fontSize:'14px' }}> Select the checkbox</Title>} >
                            <CheckboxGroup
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: '10px'
                                }}
                                options={
                                [
                                    {
                                        label: 'File Title Based on Associated Post Title',
                                        value: 'post_title'
                                    },
                                    {
                                        label: 'Alt Text Based on Associated Post Title',
                                        value: 'alt_text'
                                    },
                                    {
                                        label: 'Caption Based on Associated Post Title',
                                        value: 'caption'
                                    },
                                    {
                                        label: 'Description Based on Associated Post Title',
                                        value: 'post_description'
                                    },

                                ]
                            } onChange={ onChangeAddTextByPostTitle } />

                        </Form.Item>
                    </Form>

                </Content>
                :
                <Content>
                    <Title style={{marginTop:'0px'}} level={5}> Title </Title>
                    <TextArea
                        onChange={ balkModalDataChange }
                        name={`post_title`}
                        value={bulkSubmitdata.data.post_title}
                        placeholder={`Title`}
                    />
                    <Title style={{marginTop:'10px'}} level={5}> Alt Text </Title>
                    <TextArea
                        onChange={balkModalDataChange}
                        name={`alt_text`}
                        value={bulkSubmitdata.data.alt_text}
                        placeholder={`Alt text`}
                    />
                    <Title style={{marginTop:'10px'}} level={5}> Caption </Title>
                    <TextArea
                        onChange={balkModalDataChange}
                        name={`caption`}
                        value={bulkSubmitdata.data.caption}
                        placeholder={`Caption`}
                    />
                    <Title style={{marginTop:'10px'}} level={5}> Description </Title>
                    <TextArea
                        onChange={balkModalDataChange}
                        name={`post_description`}
                        value={bulkSubmitdata.data.post_description}
                        placeholder={`Description`}
                    />
                    <Title style={{marginTop:'10px'}} level={5}> Categories </Title>
                    <Select
                        onChange={
                            (value) => dispatch({
                                type: Types.BULK_SUBMIT,
                                bulkSubmitData: {
                                    ...stateValue.bulkSubmitData,
                                    'post_categories': value
                                },
                            })
                        }
                        allowClear = {true}
                        placeholder={'Categories'}
                        size="large"
                        mode="multiple"
                        style={{
                            width: '100%',
                        }}
                        options={ filteredOptions }
                    />
                </Content>
                }
            <Divider />
        </Modal>
    )
}
export default BulkModal;