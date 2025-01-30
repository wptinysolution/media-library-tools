import React, { useState } from "react";

import { Divider, Input, Modal, Layout, Typography, Progress, Space } from 'antd';

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";
import {getMedia, singleUpDateApi} from "../../Utils/Data";

const {  Content } = Layout;

const { Title, Paragraph } = Typography;

function BulkModal() {

    const [stateValue, dispatch] = useStateValue();

    const [IsButtonDisabled, setIsButtonDisabled] = useState(true);

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

        setIsButtonDisabled( ! stateValue.bulkSubmitData.ids.length || ! data.file_name.length > 0 );

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
        let newName;

        if (stateValue.bulkSubmitData.type === 'bulkRenameByPostTitle') {
            newName = 'bulkRenameByPostTitle';
        } else if (stateValue.bulkSubmitData.type === 'bulkRenameBySKU') {
            newName = 'bulkRenameBySKU'; // Assuming 'product_sku' is the correct field in 'prams'
        } else {
            newName = prams.data.file_name;
        }
        // Simulate the renaming operation using an asynchronous function (e.g., API call)
        const response = await singleUpDateApi( { newname: newName, ID: id });
        // Recur with the rest of the IDs in the list
        await new Promise(resolve => setTimeout(resolve, 300));

        if( prams.ids.length && response.status ){
            await renameIdsRecursively( { ...prams, ids: prams.ids.slice(1) } );
        }
        return response;
    }

    const handleBulkModalOk = async () => {
        setIsButtonDisabled( true );
        const response = await renameIdsRecursively( stateValue.bulkSubmitData );
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

    const handleBulkModalCancel = () => {
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...stateValue.bulkSubmitData,
                isModalOpen: false,
            },
        });
    };

    const isTheButtonDisabled = () => {
        if( ['bulkRenameBySKU', 'bulkRenameByPostTitle'].includes(stateValue.bulkSubmitData.type) ){
            setIsButtonDisabled( false );
        } else {
            const isDisable = ! stateValue.bulkSubmitData.ids.length || ! stateValue.bulkSubmitData.data.file_name.length;
            setIsButtonDisabled( isDisable );
        }
    };

    return (
        <Modal
            maskClosable={false}
            title={`Bulk Rename`}
            open={ stateValue.bulkSubmitData.isModalOpen }
            onOk={handleBulkModalOk}
            onCancel={handleBulkModalCancel}
            okButtonProps={{ disabled: IsButtonDisabled }}
            cancelButtonProps={{ disabled: IsButtonDisabled }}
            okText="Rename"
            afterOpenChange={ isTheButtonDisabled }
        >
            <Divider />
            <Content>
                {
                    stateValue.bulkSubmitData.type === 'bulkRenameByPostTitle' ? (
                        <>
                            <Title style={{ marginTop: '0px', marginBottom: '15px' }} level={5}>
                                Are You Sure Bulk Rename Based on Associated Post Title?
                            </Title>
                        </>
                    ) : stateValue.bulkSubmitData.type === 'bulkRenameBySKU' ? (
                        <>
                            <Title style={{ marginTop: '0px', marginBottom: '15px' }} level={5}>
                                Are You Sure Bulk Rename Based on Product SKU?
                            </Title>
                        </>
                    ) : (
                        <>
                            <Title style={{ marginTop: '0px', marginBottom: '15px' }} level={5}>File name </Title>
                            <Paragraph type="secondary" style={{ fontSize: '14px', color: '#ff0000' }}>
                                Prefix and suffix will not apply for here
                            </Paragraph>
                            <Input
                                style={{
                                    height: '40px',
                                    marginBottom: '15px'
                                }}
                                onChange={balkModalDataChange}
                                name={`file_name`}
                                value={stateValue.bulkSubmitData.data.file_name}
                                placeholder={`File Name`}
                            />

                            { !stateValue.bulkSubmitData.ids.length && (
                                <Paragraph type="secondary" style={{ fontSize: '14px', color: '#ff0000' }}>
                                    No Item selected for rename
                                </Paragraph>
                            )}
                            { !stateValue.bulkSubmitData.data.file_name.length && (
                                <Paragraph type="secondary" style={{ fontSize: '14px', color: '#ff0000' }}>
                                    Empty value not allowed.
                                </Paragraph>
                            )}
                        </>
                    )
                }
                <Divider />
            </Content>
            { stateValue.bulkSubmitData.progressBar >= 0 && <> <Title level={5}> Progress:  </Title> <Progress showInfo={true} percent={stateValue.bulkSubmitData.progressBar} /> </> }
            <Divider />

        </Modal>
    )
}
export default BulkModal;

