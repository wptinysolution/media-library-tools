import React, {useEffect, useState} from "react";

import {Divider, Modal, List, Layout, Button, Spin, Space} from 'antd';

import { LoadingOutlined } from '@ant-design/icons';

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

import {getDirList, ignoreDirForScan, rescanDirList} from "../../Utils/Data";

const {  Content } = Layout;

function DirectoryModal() {

    const [stateValue, dispatch] = useStateValue();

    const [ scanDir, setScanDir ] = useState( null );

    const [ ignoreDir, setIgnoreDir ] = useState( null );

    const handleDirModalOk = () => {
        dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                isDirModalOpen: false
            },
        });
    };

    const handleDirModalCancel = () => {
        dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                isDirModalOpen: false
            },
        });
    };

    const handleDirRescan = async ( dir = 'all' ) => {
        setScanDir( dir );
        dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                scanRabbisDirLoading: true,
            },
        });
        const dirList = await rescanDirList( {  dir : dir } );
        if( dirList.data.updated ){
            await dispatch({
                type: Types.GENERAL_DATA,
                generalData: {
                    ...stateValue.generalData,
                    scanRabbisDirList: dirList.data.thedirlist,
                    scanRabbisDirLoading: false,
                },
            });
        }
        await setScanDir(null);
    };

    const handleDirIgnore = async ( dir = 'all' ) => {
        setIgnoreDir( dir );
        //await rescanDirList( {  dir : dir } );
        await ignoreDirForScan({  dir : dir });
        await setIgnoreDir(null);
    };

    const antIcon = (
        <LoadingOutlined
            style={{
                fontSize: 24,
            }}
            spin
        />
    );

    return (
        <Modal
            style={{
                maxWidth: "950px"
            }}
            width="100%"
            height="500px"
            title={`Directory List`}
            open={ stateValue.generalData.isDirModalOpen }
            onCancel={handleDirModalCancel}
            footer={[
                <Button key="rescan" onClick={ () => handleDirRescan() }>
                    Re-Scan Directory List { 'all' === scanDir && <Spin size="small" /> }
                </Button>,
                <Button key="submit" type="primary"  onClick={handleDirModalOk}> Continue  </Button>,
            ]}
        >
            <Divider />
            <Content style={{ height: "450px", position:'relative' }} >
            { stateValue.generalData.scanRabbisDirLoading ?
                <Spin indicator={antIcon} style={ {
                    position: 'absolute',
                    left: '50%',
                    top: '50%',
                    margin: 'auto',
                    transform: 'translate(-50%, -50%)'
                } } /> :
                <>
                    <List
                        itemLayout="horizontal"
                        dataSource={ Object.entries( stateValue.generalData.scanRabbisDirList ) }
                        locale = { { emptyText: 'No Data. Re scan start will after some time.' } }
                        renderItem={ ( [key, item], index) => (
                            <List.Item key={key}>
                                <List.Item.Meta
                                    title={ key }
                                    description={ `${ item.total_items == 0 ? 'This directory will be scanned again according to the schedule.' : `Found ${item.total_items} items, And Checked ${item.counted} items.` }` }
                                />
                                <Space>
                                    <Button style={ { padding: '0 15px' } } key="rescan" onClick={ () => handleDirRescan( key ) }>
                                        Re-Scan { key === scanDir && <Spin size="small" /> }
                                    </Button>
                                </Space>
                            </List.Item>
                        ) }

                    />
                </>
                }
            </Content>
            <Divider />
        </Modal>
    )
}
export default DirectoryModal;