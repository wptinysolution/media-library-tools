import React, { useState } from "react";

import {Divider, Input, Modal, Select, Layout, Typography, Form, Checkbox, Progress} from 'antd';

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

import {getMedia, singleUpDateApi} from "../../Utils/Data";

const {  Content } = Layout;

const { Title, Text } = Typography;

const { TextArea } = Input;

const CheckboxGroup = Checkbox.Group;

function BulkModal() {

    const [stateValue, dispatch] = useStateValue();
    const [IsButtonDisabled, setIsButtonDisabled] = useState(true);

    const bulkSubmitData = stateValue.bulkSubmitData;
    /**
     * @param event
     */
    const balkModalDataChange = ( event ) => {
        const data = {
            ...bulkSubmitData.data,
            [event.target.name] : event.target.value
        }
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...bulkSubmitData,
                data
            },
        });
        const changeDetected = Object.values( data ).some(value => value !== '');
        const isDisable = ! stateValue.bulkSubmitData.ids.length || ! changeDetected;
        setIsButtonDisabled( isDisable );
    };
    /**
     * Button Disable
     */
    const isTheButtonDisabled = () => {
        let changeDetected = false;
        if( 'bulkEditPostTitle' === stateValue.bulkSubmitData.type ){
            changeDetected = stateValue.bulkSubmitData.will_attached_post_title.length;
        } else {
            changeDetected = Object.values(stateValue.bulkSubmitData.data).some(value => value !== '');
        }
        const isDisable = ! stateValue.bulkSubmitData.ids.length || ! changeDetected;
        setIsButtonDisabled( isDisable );
    };
    /**
     *
     * @param prams
     * @returns {Promise<axios.AxiosResponse<*>>}
     */
    const addDataRecursively = async ( prams ) => {
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...stateValue.bulkSubmitData,
                progressBar: Math.floor( 100 * ( stateValue.bulkSubmitData.progressTotal - prams.ids.length ) / stateValue.bulkSubmitData.progressTotal ),
            },
        });
        if ( prams.ids.length === 0) {
            // Base case: All renaming operations are completed
            return;
        }
        const id = prams.ids[0];
        // Simulate the renaming operation using an asynchronous function (e.g., API call)
        const response = await singleUpDateApi( { bulkEditPostTitle: stateValue.bulkSubmitData.will_attached_post_title, ID: id });
        // Recur with the rest of the IDs in the list
        if( prams.ids.length && response.status ){
            await addDataRecursively( { ...prams, ids: prams.ids.slice(1) } );
        }
        return response;
    }
    /**
     * @returns {Promise<void>}
     */
    const mediaHandleBulkModalOk = async () => {
        setIsButtonDisabled( true );
        const response = await addDataRecursively( stateValue.bulkSubmitData );
        if( 200 === response?.status ){
            // Close the modal after 2 seconds
            setTimeout(() => {
                dispatch({
                    type: Types.BULK_SUBMIT,
                    bulkSubmitData: {
                        ...stateValue.bulkSubmitData,
                        isModalOpen: false,
                    },
                });
            }, 1000);
            const response = await getMedia( stateValue.mediaData.postQuery );
            await dispatch({
                type: Types.GET_MEDIA_LIST,
                mediaData: {
                    ...stateValue.mediaData,
                    ...response,
                    isLoading: false
                },
            });
            setIsButtonDisabled( false );
        }
    };
    /**
     * @param event
     */
    const handleBulkModalOk = () => {
        if( 'bulkEditPostTitle' === stateValue.bulkSubmitData.type ){
            //console.log( stateValue.bulkSubmitData )
            mediaHandleBulkModalOk();
        }else {
            dispatch({
                ...stateValue,
                type: Types.BULK_SUBMIT,
                saveType: Types.BULK_SUBMIT
            });
        }
    };
    /**
     * searchUses
     * @param event
     */
    const handleBulkModalCancel = () => {
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...bulkSubmitData,
                isModalOpen: false,
            },
        });
    };
    /**
     * @param event
     */
    const onChangeAddTextByPostTitle = ( list ) => {
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...bulkSubmitData,
                will_attached_post_title: list
            },
        });
        const isDisable = ! list.length;
        setIsButtonDisabled( isDisable );
    };

    const filteredOptions = stateValue.generalData.termsList.filter( ( item ) => ! stateValue.bulkSubmitData.post_categories.includes( item.value ) );

    return (
        <Modal
            title={ 'bulkEditPostTitle' === stateValue.bulkSubmitData.type ? `Bulk Assign` : `Bulk Edit` }
            open={ bulkSubmitData.isModalOpen }
            onOk={handleBulkModalOk}
            onCancel={handleBulkModalCancel}
            okButtonProps={{ disabled: IsButtonDisabled }}
            okText="Done"
            style={{
                maxWidth: "650px"
            }}
            width="100%"

            afterOpenChange={ isTheButtonDisabled }
        >
            <Divider />
            { 'bulkEditPostTitle' === stateValue.bulkSubmitData.type ?
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
                                            label: 'File Title Based on Attached Post',
                                            value: 'post_title'
                                        },
                                        {
                                            label: 'Alt Text Based on Attached Post',
                                            value: 'alt_text'
                                        },
                                        {
                                            label: 'Caption Based on Attached Post',
                                            value: 'caption'
                                        },
                                        {
                                            label: 'Description Based on Attached Post',
                                            value: 'post_description'
                                        },

                                    ]
                                }
                                onChange={ onChangeAddTextByPostTitle }
                            />

                        </Form.Item>
                    </Form>
                    { stateValue.bulkSubmitData.progressBar >= 0 && <> <Title level={5}> Progress:  </Title> <Progress showInfo={true} percent={stateValue.bulkSubmitData.progressBar} /> </> }
                </Content>
                :
                <Content>
                    <Title style={{marginTop:'0px'}} level={5}> Title </Title>
                    <TextArea
                        onChange={ balkModalDataChange }
                        name={`post_title`}
                        value={bulkSubmitData.data.post_title}
                        placeholder={`Title`}
                    />
                    <Title style={{marginTop:'10px'}} level={5}> Alt Text </Title>
                    <TextArea
                        onChange={balkModalDataChange}
                        name={`alt_text`}
                        value={bulkSubmitData.data.alt_text}
                        placeholder={`Alt text`}
                    />
                    <Title style={{marginTop:'10px'}} level={5}> Caption </Title>
                    <TextArea
                        onChange={balkModalDataChange}
                        name={`caption`}
                        value={bulkSubmitData.data.caption}
                        placeholder={`Caption`}
                    />
                    <Title style={{marginTop:'10px'}} level={5}> Description </Title>
                    <TextArea
                        onChange={balkModalDataChange}
                        name={`post_description`}
                        value={bulkSubmitData.data.post_description}
                        placeholder={`Description`}
                    />
                </Content>
                }
            <Divider />
        </Modal>
    )
}
export default BulkModal;