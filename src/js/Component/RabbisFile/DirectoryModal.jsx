import React, {useEffect, useState} from "react";

import {Divider, Input, Modal, List, Layout, Typography, Button, Spin} from 'antd';

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

import {getDirList, getRabbisFile, rescanDirList} from "../../Utils/Data";

const {  Content } = Layout;

const { Title } = Typography;

const { TextArea } = Input;

function DirectoryModal() {

    const [stateValue, dispatch] = useStateValue();

    const handleDirForModal = async () => {
        const responseDate = await getDirList();
        const preparedDate =  await JSON.parse( responseDate.data );
        await dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                scanRabbisDirList: preparedDate
            },
        });
    };

    const handleDirModalOk = () => {
        dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                isDirModalOpen: false,
                scanDir: null
            },
        });
    };

    const handleDirModalCancel = () => {
        dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                isDirModalOpen: false,
                scanDir: null
            },
        });
    };

    const handleDirModalRescan = async ( dir = 'all' ) => {
        await dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                scanDir: dir,
            },
        });
        await rescanDirList( {  dir : dir } );
        await handleDirForModal();
    };

    useEffect(() => {
        handleDirForModal()
    }, [ stateValue.generalData.isDirModalOpen ] );

    console.log( stateValue.generalData.scanDir )

    return (
        <Modal
            title={`Directory List`}
            open={ stateValue.generalData.isDirModalOpen }
            onCancel={handleDirModalCancel}
            footer={[
                <Button key="rescan" onClick={ () => handleDirModalRescan() }> Re-Scan { 'all' === stateValue.generalData.scanDir && <Spin size="small" /> }  </Button>,
                <Button key="submit" type="primary"  onClick={handleDirModalOk}> Continue  </Button>,
            ]}
        >
            <Divider />
            <Content>
                <List
                    itemLayout="horizontal"
                    dataSource={ Object.entries( stateValue.generalData.scanRabbisDirList ) }
                    renderItem={ ( [key, item], index) => (
                        <List.Item>
                            <List.Item.Meta
                                title={ key }
                                description={ `Found ${item.total_items} items, And Checked ${item.counted} items` }
                            />
                            <Button key="rescan" onClick={ () => handleDirModalRescan( key ) }>
                                Re-Scan { key === stateValue.generalData.scanDir && <Spin size="small" /> }
                            </Button>
                        </List.Item>
                    ) }
                    locale = {
                        {
                            emptyText: 'No Data. Re scan start will after some time.'
                        }
                    }
                />
            </Content>
            <Divider />
        </Modal>
    )
}
export default DirectoryModal;