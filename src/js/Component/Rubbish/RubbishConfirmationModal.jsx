import React, { useState } from "react";

import {Divider, Modal, Layout, Typography, Progress, Spin, Space} from 'antd';

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

import {rubbishBulkDeleteApi, singleDeleteApi, singleIgnoreApi, singleShowApi} from "../../Utils/Data";

const {  Content } = Layout;

const { Title, Paragraph } = Typography;

function RubbishConfirmationModal() {

    const [stateValue, dispatch] = useStateValue();

    const [buttonDisabled, setButtonDisabled] = useState( true );

    const [theFile, setTheFile] = useState( '' );

    const [total, setTotal] = useState( 0 );

    const rubbishBulkActionRecursively = async ( prams ) => {
        let response = {};
        dispatch({
            type: Types.BALK_RUBBISH,
            bulkRubbishData: {
                ...stateValue.bulkRubbishData,
                progressBar : Math.floor( 100 * ( stateValue.bulkRubbishData.progressTotal - prams.files.length ) / stateValue.bulkRubbishData.progressTotal ),
            },
        });
        setTotal( prams.files.length );
        if ( prams.files.length === 0) {
            response.status = 200;
            // Base case: All renaming operations are completed.
            return response;
        }
        const file = prams.files[0];

        // Simulate the renaming operation using an asynchronous function (e.g., API call)
        if(  'ignore' === stateValue.bulkRubbishData.type ){
            response = await singleIgnoreApi( { file_path: file.path });
        } else if ( tsmltParams?.proVersion && 'delete' === stateValue.bulkRubbishData.type ) {
            response = await rubbishBulkDeleteApi({file_paths: prams.files});
            prams.files = [];
        } else if ( 'show' === stateValue.bulkRubbishData.type ){
            response = await singleShowApi( { file_path: file.path });
        }
        setTheFile( prevState => file.path );

        // Recur with the rest of the IDs in the list
        if( prams.ids.length && response?.status ){
            return await rubbishBulkActionRecursively( { ...prams, files: prams.files.slice(1) } );
        }
        return response;
    }

    const handleBulkModalOk = async () => {
        setButtonDisabled( true );
        const response = await rubbishBulkActionRecursively( stateValue.bulkRubbishData );
        if( 200 === response?.status ){
            setTimeout(() => {
                dispatch({
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

            }, 1000);

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
            maskClosable={false}
            title={`Bulk ${ 'ignore' === stateValue.bulkRubbishData.type ? 'Ignore' : 'Delete' } Action`}
            open={ stateValue.bulkRubbishData.isModalOpen }
            onOk={handleBulkModalOk}
            onCancel={handleBulkModalCancel}
            okButtonProps={{ disabled: buttonDisabled }}
            cancelButtonProps={{ disabled: buttonDisabled }}
            okText={ 'ignore' === stateValue.bulkRubbishData.type ? 'Ignore' : 'Delete' }
            afterOpenChange={ () => setButtonDisabled( ! stateValue.bulkRubbishData.ids.length ) }
        >
            <Divider />
            <Content>

                <Title style={{marginTop:'0px', marginBottom:'15px'}} level={5}>
                    { ! buttonDisabled ? <>
                            Are You Confirm { 'ignore' === stateValue.bulkRubbishData.type ? 'To Ignore' : 'show' === stateValue.bulkRubbishData.type ? 'To Make Deletable' : 'To Delete' }?
                        </> :
                        <Space wrap>
                            Remaining { `- ${total}` }
                        </Space>
                    }
                    </Title>

                { stateValue.bulkRubbishData.progressBar >= 0 && <Progress showInfo={true} percent={stateValue.bulkRubbishData.progressBar} /> }
                { ! stateValue.bulkRubbishData.ids.length &&
                    <Paragraph type="secondary" style={{ fontSize: '14px', color:'#ff0000'}}>
                        No Item selected { 'ignore' === stateValue.bulkRubbishData.type ? 'To Ignore' : 'To Delete' }
                    </Paragraph >
                }
                <Space
                    style={{
                        alignItems: 'self-start',
                        overflow: 'hidden',
                        maxWidth: '100%'
                    }}
                > { total && theFile.length > 0 ? <> <Spin size="small" /> { theFile } </> : '' } </Space>
            </Content>
            <Divider />

        </Modal>
    )
}
export default RubbishConfirmationModal;