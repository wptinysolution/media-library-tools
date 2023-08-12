import React, { useState } from "react";

import { Divider, Input, Modal, Layout, Typography, Progress, Space } from 'antd';

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";
import {getMedia, singleUpDateApi} from "../../Utils/Data";

const {  Content } = Layout;

const { Title, Paragraph } = Typography;

function BulkModal() {

    const [stateValue, dispatch] = useStateValue();

    const [buttonDisabled, setButtonDisabled] = useState(true);

    const balkModalDataChange = ( event ) => {
        const data = {
            ...stateValue.bulkSubmitData.data,
            [event.target.name] : event.target.value
        }
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...stateValue.bulkSubmitData,
                data
            },
        });

        setButtonDisabled( ! stateValue.bulkSubmitData.ids.length || ! data.file_name.length > 0 );

    };

    const renameIdsRecursively = async ( prams ) => {
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
        const response = await singleUpDateApi( { newname: prams.data.file_name, ID: id });
        // Recur with the rest of the IDs in the list
        if( prams.ids.length && response.status ){
            await renameIdsRecursively( { ...prams, ids: prams.ids.slice(1) } );
        }
        return response;
    }

    const handleBulkModalOk = async () => {
        setButtonDisabled( true );
        const response = await renameIdsRecursively( stateValue.bulkSubmitData );
        if( 200 === response?.status ){
            await dispatch({
                type: Types.BULK_SUBMIT,
                bulkSubmitData: {
                    ...stateValue.bulkSubmitData,
                    isModalOpen: false,
                },
            });
            const response = await getMedia( stateValue.mediaData.postQuery );
            await dispatch({
                type: Types.GET_MEDIA_LIST,
                mediaData: {
                    ...stateValue.mediaData,
                    ...response,
                    isLoading: false
                },
            });
            setButtonDisabled( false );
        }
    };

    const handleBulkModalCancel = () => {
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...stateValue.bulkSubmitData,
                isModalOpen: false,
            },
        });
    };

    return (
        <Modal
            maskClosable={false}
            title={`Bulk Rename`}
            open={ stateValue.bulkSubmitData.isModalOpen }
            onOk={handleBulkModalOk}
            onCancel={handleBulkModalCancel}
            okButtonProps={{ disabled: buttonDisabled }}
            cancelButtonProps={{ disabled: buttonDisabled }}
            okText="Rename"
            afterOpenChange={ () => setButtonDisabled( ! stateValue.bulkSubmitData.ids.length || ! stateValue.bulkSubmitData.data.file_name.length ) }
        >
            <Divider />
            <Content>
                <Title style={{marginTop:'0px', marginBottom:'15px'}} level={5}> File name</Title>
                <Input
                    style={{
                        height: '40px',
                        marginBottom:'15px'
                    }}
                    onChange={ balkModalDataChange }
                    name={`file_name`}
                    value={stateValue.bulkSubmitData.data.file_name}
                    placeholder={`File Name`}
                />
                { ! stateValue.bulkSubmitData.ids.length && <Paragraph type="secondary" style={{ fontSize: '14px', color:'#ff0000'}}>
                    No Item selected for rename
                </Paragraph > }
                { ! stateValue.bulkSubmitData.data.file_name.length && <Paragraph type="secondary" style={{ fontSize: '14px', color:'#ff0000'}}>
                    Empty value Not allowed.
                </Paragraph > }
                <Divider />
            </Content>
            { stateValue.bulkSubmitData.progressBar >= 0 && <> <Title level={5}> Progress:  </Title> <Progress showInfo={true} percent={stateValue.bulkSubmitData.progressBar} /> </> }
            <Divider />

        </Modal>
    )
}
export default BulkModal;