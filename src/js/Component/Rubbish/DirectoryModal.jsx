import React, {useEffect, useState} from "react";

import {Divider, Modal, List, Progress, Layout, Button, Spin, Space, Typography} from 'antd';

const { Title } = Typography;

import { LoadingOutlined } from '@ant-design/icons';

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

import {actionClearSchedule, rescanDir} from "../../Utils/Data";

const {  Content } = Layout;

function DirectoryModal() {

    const [stateValue, dispatch] = useStateValue();

    const [ scanDir, setScanDir ] = useState( null );

    const [ progressBar, setProgressBar ] = useState( -1 );

    const [ progressTotal, setProgressTotal ] = useState( 0 );

    const [ scaned, setScaned ] = useState( 0 );

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

        return dirList;
    };

    const handleaClearSchedule = async () => {
        dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                scanRubbishDirLoading: true,
            },
        });
        const ClearSchedule = await actionClearSchedule();
        await dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                scanRubbishDirList: ClearSchedule.data.dirlist,
                scanRubbishDirLoading: false,
            },
        });

    };

    const scanManuallyRecursively = async ( prams ) => {
        // setProgressBar( ( prevState ) => {
        //     Math.floor( 100 * ( progressTotal - prams.ids.length ) / progressTotal )
        // } );

        if ( Object.entries( stateValue.generalData.scanRubbishDirList ).length === 0) {
            // Base case: All renaming operations are completed
            return;
        }
        if ( prams.length === 0) {
            // Base case: All renaming operations are completed
            return;
        }
        const dirKey = prams[0];
        // Simulate the renaming operation using an asynchronous function (e.g., API call)
        const response = await handleDirRescan( dirKey );

        console.log( dirKey )

        // // Recur with the rest of the IDs in the list
        if( prams.length && response.status ){
            await scanManuallyRecursively(  prams.slice(1) );
        }
        return response;
    }

    const getObjectKeys = (arr) => {
        const keys = [];
        arr.forEach( ( item ) => {
            keys.push( item[0] );
        });
        return keys;
    }
    /**
     *
     * @param dir
     * @returns {Promise<void>}
     */
    const handleDirScanManually = async () => {
        const dirlist = Object.entries( stateValue.generalData.scanRubbishDirList );
        // Get object keys from the data
        const objectKeys = getObjectKeys( dirlist );

        setProgressTotal( objectKeys.length );

        const response = await scanManuallyRecursively( objectKeys );
        if( 200 === response?.status ){

        }
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
                <Button key="rescanManually" onClick={ () => handleDirScanManually() }>
                   Scan Manually { 'all' === scanDir && <Spin size="small" /> }
                </Button>,
                <Button key="rescan" onClick={ () => handleDirRescan() }>
                    Re-Scan And Get Directory List { 'all' === scanDir && <Spin size="small" /> }
                </Button>,
                <Button key="NextSchedule" type="primary"  onClick={ () => handleaClearSchedule() }> Execute Next Schedule </Button>
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
                                Directory will search in next schedule be patience till then. <br/>
                                Next Schedule {stateValue.generalData.scanDirNextSchedule}
                        </Title> } }
                        renderItem={ ( [key, item], index) => (
                            <List.Item key={key}>
                                <List.Item.Meta
                                    title={ key }
                                    description={ item.total_items == 0 ? `This directory will be scanned again according to the schedule.` : <span style={ { color: '#1677ff' } }> Scanned {item.counted} items of {item.total_items} items </span> }
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
            { progressBar >= 0 && <> <Title level={5}> Progress:  </Title> <Progress showInfo={true} percent={progressBar} /> </> }
            <Divider />
        </Modal>
    )
}
export default DirectoryModal;