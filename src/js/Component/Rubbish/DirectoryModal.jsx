import React, {useEffect, useState} from "react";

import {Divider, Modal, List, Layout, Button, Spin, Space, Typography} from 'antd';

const { Title } = Typography;

import { LoadingOutlined } from '@ant-design/icons';

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

import { rescanDir } from "../../Utils/Data";

const {  Content } = Layout;

function DirectoryModal() {

    const [stateValue, dispatch] = useStateValue();

    const [ scanDir, setScanDir ] = useState( null );

    const handleNextSchedule = () => {
        dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
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

    /**
     *
     * @param dir
     * @returns {Promise<void>}
     */
    const handleDirRescan = async ( dir = 'all' ) => {
        setScanDir( dir );
        dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                scanRubbishDirLoading: true,
            },
        });
        const dirList = await rescanDir( {  dir : dir } );

        await dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                scanRubbishDirList: dirList.data.thedirlist,
                scanRubbishDirLoading: false,
            },
        });

        await setScanDir(null);
    };
    /**
     *
     * @type {JSX.Element}
     */
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
                    Re-Scan And Get Directory List { 'all' === scanDir && <Spin size="small" /> }
                </Button>,
                <Button key="NextSchedule" type="primary"  onClick={ () => handleDirRescan( 'NextSchedule' ) }> Execute Next Schedule </Button>
            ]}
        >

            <Divider />
            <Content style={{ height: "450px", position:'relative', 'overflowY': 'auto', padding:'0 15px' }} >
            { stateValue.generalData.scanRubbishDirLoading ?
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
                        dataSource={ Object.entries( stateValue.generalData.scanRubbishDirList ) }
                        locale = { { emptyText: <Title level={5} style={{ margin:'0 15px', color: 'red' }}>
                                No Directory found. Directory will search in next schedule be patience till then. <br/>
                                Next Schedule {stateValue.generalData.scanDirNextSchedule}
                        </Title> } }
                        renderItem={ ( [key, item], index) => (
                            <List.Item key={key}>
                                <List.Item.Meta
                                    title={ key }
                                    description={ `${ item.total_items == 0 ? `This directory will be scanned again according to the schedule.` : `Scanned ${item.counted} items of ${item.total_items} items` }` }
                                />
                                <Space>
                                    <Button style={ { padding: '0 15px' } } key="rescan" onClick={ () => handleDirRescan( key ) }>
                                        Re- Execute In Schedule  { key === scanDir && <Spin size="small" /> }
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