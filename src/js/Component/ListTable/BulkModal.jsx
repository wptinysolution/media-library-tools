import React from "react";

import { Divider, Input, Modal, Select, Layout, Typography } from 'antd';

import {useStateValue} from "../../Utils/StateProvider";
import {defaultBulkSubmitData} from "../../Utils/UtilData";
import * as Types from "../../Utils/actionType";

import { submitBulkMediaAction } from "../../Utils/Data";

const {  Content } = Layout;

const { Title } = Typography;

const { TextArea } = Input;

function BulkModal() {

    const [stateValue, dispatch] = useStateValue();

    const bulkSubmitdata = stateValue.bulkSubmitData;

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

    const handleBulkModalOk = async ( event ) => {
        const response = await submitBulkMediaAction( stateValue.bulkSubmitData );
        console.log( 'submitBulkMediaAction' );
        if( 200 === parseInt( response.status ) && response.data.updated ){
            await dispatch({
                type: Types.GET_MEDIA_LIST,
                mediaData: {
                    ...stateValue.mediaData,
                    postQuery: {
                        ...stateValue.mediaData.postQuery,
                        isUpdate: ! stateValue.mediaData.postQuery.isUpdate,
                    },
                },
            });

            await dispatch({
                type: Types.BULK_SUBMIT,
                bulkSubmitData: {
                    ...defaultBulkSubmitData,
                    type: 'bulkedit',
                },
            });
        }
    };

    const handleBulkModalCancel = (event) => {
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...bulkSubmitdata,
                isModalOpen: false,
            },
        });
    };

    // stateValue.bulkSubmitData.post_categories
    const filteredOptions = stateValue.generalData.termsList.filter( ( item ) => ! stateValue.bulkSubmitData.post_categories.includes( item.value ) );

    return (
        <Modal
            title={`Bulk Edit`}
            open={ bulkSubmitdata.isModalOpen }
            onOk={handleBulkModalOk}
            onCancel={handleBulkModalCancel}
        >
            <Divider />
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
            <Divider />
        </Modal>
    )
}
export default BulkModal;