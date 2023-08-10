import React, { useState } from "react";

import { Divider, Input, Modal, Layout, Typography, Progress } from 'antd';

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";
import {getMedia, singleUpDateApi} from "../../Utils/Data";

const {  Content } = Layout;

const { Title } = Typography;

function BulkModal() {

    const [stateValue, dispatch] = useStateValue();

    const [buttonDisabled, setButtonDisabled] = useState(false);

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

    const renameIdsRecursively = async ( prams ) => {
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...bulkSubmitdata,
                progressBar : 100 * ( bulkSubmitdata.progressTotal - prams.ids.length ) / bulkSubmitdata.progressTotal,
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
        const response = await renameIdsRecursively( bulkSubmitdata );
        if( 200 === response.status ){
            await dispatch({
                type: Types.BULK_SUBMIT,
                bulkSubmitData: {
                    ...bulkSubmitdata,
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
                ...bulkSubmitdata,
                isModalOpen: false,
            },
        });
    };

    return (
        <Modal
            title={`Bulk Rename`}
            open={ bulkSubmitdata.isModalOpen }
            onOk={handleBulkModalOk}
            onCancel={handleBulkModalCancel}
            okButtonProps={{ disabled: buttonDisabled }}
            cancelButtonProps={{ disabled: buttonDisabled }}
        >
            <Divider />
            <Content>
                { bulkSubmitdata.progressBar > 0 && <Progress showInfo={true} percent={bulkSubmitdata.progressBar} /> }
                <Title style={{marginTop:'0px', marginBottom:'15px'}} level={5}> Bulk Rename </Title>
                <Input
                    style={{
                        height: '40px'
                    }}
                    onChange={ balkModalDataChange }
                    name={`file_name`}
                    value={bulkSubmitdata.data.file_name}
                    placeholder={`File Name`}
                />
            </Content>
            <Divider />

        </Modal>
    )
}
export default BulkModal;