import React, { useState } from "react";

import { Divider, Input, Modal, Layout, Typography, Progress } from 'antd';

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";
import {getMedia, singleDeleteApi, singleIgnoreApi, singleUpDateApi} from "../../Utils/Data";

const {  Content } = Layout;

const { Title } = Typography;

function RubbishConfirmationModal() {

    const [stateValue, dispatch] = useStateValue();

    const [buttonDisabled, setButtonDisabled] = useState(false);

    const rubbishBulkActionRecursively = async ( prams ) => {

        dispatch({
            type: Types.BALK_RUBBISH,
            bulkRubbishData: {
                ...stateValue.bulkRubbishData,
                progressBar : 100 * ( stateValue.bulkRubbishData.progressTotal - prams.files.length ) / stateValue.bulkRubbishData.progressTotal,
            },
        });
        if ( prams.files.length === 0) {
            // Base case: All renaming operations are completed
            return;
        }
        const file = prams.files[0];
        let response;
        // // Simulate the renaming operation using an asynchronous function (e.g., API call)
        if(  'ignore' == stateValue.bulkRubbishData.type ){
            response = await singleIgnoreApi( { file_path: file.path });
        } else if ( 'delete' == stateValue.bulkRubbishData.type ){
            response = await singleDeleteApi( { file_path: file.path });
        }

        // // Recur with the rest of the IDs in the list
        if( prams.ids.length && response.status ){
            await rubbishBulkActionRecursively( { ...prams, files: prams.files.slice(1) } );
        }
        return response;
    }


    const handleBulkModalOk = async () => {
        setButtonDisabled( true );
        const response = await rubbishBulkActionRecursively( stateValue.bulkRubbishData );
        if( 200 === response.status ){
            await dispatch({
                type: Types.BALK_RUBBISH,
                bulkRubbishData: {
                    ...stateValue.bulkRubbishData,
                    bulkChecked: false,
                    progressBar : false,
                    progressTotal : 0,
                    isModalOpen: false,
                    files: [],
                    ids: []
                },
            });

            await dispatch({
                type: Types.RUBBISH_MEDIA,
                rubbishMedia: {
                    ...stateValue.rubbishMedia,
                    postQuery:{
                        ...stateValue.rubbishMedia.postQuery,
                        isQueryUpdate: ! stateValue.rubbishMedia.postQuery.isQueryUpdate
                    }
                },
            });

            setButtonDisabled( false );
        }

    };


    const handleBulkModalCancel = () => {
        dispatch({
            type: Types.BALK_RUBBISH,
            bulkRubbishData: {
                ...stateValue.bulkRubbishData,
                isModalOpen: false,
            },
        });
    };

    return (
        <Modal
            title={`Bulk Action`}
            open={ stateValue.bulkRubbishData.isModalOpen }
            onOk={handleBulkModalOk}
            onCancel={handleBulkModalCancel}
            okButtonProps={{ disabled: buttonDisabled }}
            cancelButtonProps={{ disabled: buttonDisabled }}
        >
            <Divider />
            <Content>
                <Title style={{marginTop:'0px', marginBottom:'15px'}} level={5}> Are You Confirm { 'ignore' == stateValue.bulkRubbishData.type ? 'To Ignore' : 'To Delete' }? </Title>
                { stateValue.bulkRubbishData.progressBar > 0 && <Progress showInfo={true} percent={stateValue.bulkRubbishData.progressBar} /> }
            </Content>
            <Divider />

        </Modal>
    )
}
export default RubbishConfirmationModal;