import React, {useEffect, useState} from "react";

import {Divider, Modal, List, Layout, Button, Spin, Space} from 'antd';

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

import {getDirList, rescanDirList} from "../../Utils/Data";

const {  Content } = Layout;

function DirectoryModal() {

    const [stateValue, dispatch] = useStateValue();

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

    const handleDirRescan = async ( dir = 'all' ) => {
        await dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                scanDir: dir,
            },
        });
        await rescanDirList( {  dir : dir } );
    };

    const handleDirIgnore = async ( dir = 'all' ) => {
        await dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                scanDir: dir,
            },
        });
        await rescanDirList( {  dir : dir } );
    };
    console.log( stateValue.generalData.scanRabbisDirList )
    return (
        <Modal
            style={{
                maxWidth: "950px"
            }}
            width="100%"
            title={`Directory List`}
            open={ stateValue.generalData.isDirModalOpen }
            onCancel={handleDirModalCancel}
            footer={[
                <Button key="rescan" onClick={ () => handleDirRescan() }>
                    Re-Scan Directory List { 'all' === stateValue.generalData.scanDir && <Spin size="small" /> }
                </Button>,
                <Button key="submit" type="primary"  onClick={handleDirModalOk}> Continue  </Button>,
            ]}
        >
            <Divider />
            <Content>
                <List
                    itemLayout="horizontal"
                    dataSource={ Object.entries( stateValue.generalData.scanRabbisDirList ) }
                    renderItem={ ( [key, item], index) => (
                        <List.Item key={key}>
                            <List.Item.Meta
                                title={ key }
                                description={ `${ item.total_items == 0 ? 'This directory will be scanned again according to the schedule.' : `Found ${item.total_items} items, And Checked ${item.counted} items.` }` }
                            />
                            <Space>
                                <Button style={ { padding: '0 15px' } } key="rescan" onClick={ () => handleDirRescan( key ) }>
                                    Re-Scan { key === stateValue.generalData.scanDir && <Spin size="small" /> }
                                </Button>
                                <Button style={ { padding: '0 15px' } } key="ignore" onClick={ () => handleDirIgnore( key ) }>
                                    Ignore Scan { key === stateValue.generalData.scanDir && <Spin size="small" /> }
                                </Button>
                            </Space>
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