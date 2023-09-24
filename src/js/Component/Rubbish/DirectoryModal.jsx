import React, {useEffect, useState} from "react";

import {Divider, Modal, List, Progress, Layout, Button, Spin, Space, Typography} from 'antd';

const { Title } = Typography;

import { LoadingOutlined } from '@ant-design/icons';

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

import {actionClearSchedule, rescanDir, searchFileBySingleDir} from "../../Utils/Data";

const {  Content } = Layout;

function DirectoryModal() {

    const [stateValue, dispatch] = useStateValue();

    const [ scanDir, setScanDir ] = useState( null );

    const [ buttonSpain, setButtonSpain ] = useState( null );

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

    const searchFileBySingleDirRecursively = async ( prams ) => {
        setButtonSpain( 'bulkScan' );
        await dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...stateValue.bulkSubmitData,
                progressBar: Math.floor( 100 * ( stateValue.bulkSubmitData.progressTotal - prams.length ) / stateValue.bulkSubmitData.progressTotal ),
            },
        });
        if ( prams.length <= 0) {
            setTimeout(async () => {
                setButtonSpain( null );
            }, 1000 );
            // Base case: All renaming operations are completed
            return;
        }

        let dirKey = prams[0];

        const dirlist = Object.entries( stateValue.generalData.scanRubbishDirList );

        // Find the object with the matching directory path
        let matchingObject = null;
        let rescanSameDir = false;
        for (const entry of dirlist) {
            if (entry[0] === dirKey) {
                matchingObject = entry[1];
                rescanSameDir = matchingObject.total_items > matchingObject.counted;
                break; // Stop searching once a match is found
            }
        }
        setScanDir( dirKey );
       // console.log( dirKey,  rescanSameDir, matchingObject )
        //let thePrams = rescanSameDir ? dirKey : prams.slice(1);
        // Simulate the renaming operation using an asynchronous function (e.g., API call)
        const response = await searchFileBySingleDir( { directory: dirKey } );

        // // Recur with the rest of the IDs in the list
        if( prams.length && 200 === response.status ){
            setTimeout(async () => {
                const perser =  response.data ;
               // console.log( perser )
                await dispatch({
                    type: Types.GENERAL_DATA,
                    generalData: {
                        ...stateValue.generalData,
                        scanRubbishDirList: perser.dirlist ,
                        scanRubbishDirLoading: false,
                    },
                });
                let thePrams =  'nextDir' === perser.nextDir ? prams.slice(1) : prams ;
                console.log( thePrams )
                await searchFileBySingleDirRecursively( thePrams );
            }, 1000 );

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
        setButtonSpain( 'bulkScan' );
        const dirlist = Object.entries( stateValue.generalData.scanRubbishDirList );
        // Get object keys from the data
        const objectKeys = getObjectKeys( dirlist );
        const response = await searchFileBySingleDirRecursively( objectKeys );
        // // Recur with the rest of the IDs in the list
        if( 200 === response.status ){
            setTimeout(async () => {
                setScanDir( null );
            }, 1000 );

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
            className={'rubbish-scan-directory-modal'}
            width="100%"
            height="500px"
            title={`Directory List`}
            open={ stateValue.generalData.isDirModalOpen }
            onCancel={handleDirModalCancel}
            footer={[
                <Button
                    style={ {
                        display: 'inline-flex',
                        gap: '10px',
                        alignItems: 'center'
                    } }
                    key="rescanManually"
                    onClick={ () => handleDirScanManually() }
                    type= { 'bulkScan' === buttonSpain ? "primary" : 'default' }
                >
                    Search Immediately { 'bulkScan' === buttonSpain && <Spin size="small" /> }
                </Button>,
                <Button
                    style={ {
                        display: 'inline-flex',
                        gap: '10px',
                        alignItems: 'center'
                    } }
                    key="rescan" onClick={ () => handleDirRescan( 'all' ) }
                    type= { 'all' === scanDir ? "primary" : 'default' }
                >
                        Re-Search Directory { 'all' === scanDir && <Spin size="small" /> }
                </Button>,
                <Button key="NextSchedule" type="primary"  onClick={ () => handleaClearSchedule() }> Execute Schedule </Button>
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
                                    <Button
                                        style={ {
                                            padding: '0 15px',
                                            display: 'inline-flex',
                                            gap: '10px',
                                            alignItems: 'center'
                                        } }
                                        key="rescan"
                                        onClick={ () => handleDirRescan( key ) }
                                        type= { key === scanDir ? "primary" : 'default' }
                                    >
                                        Re- Execute In Schedule  { key === scanDir && <Spin size="small" /> }
                                    </Button>
                                </Space>
                            </List.Item>
                        ) }

                    />
                </>
                }
            </Content>
            { stateValue.bulkSubmitData.progressBar > 0 && <> <Title level={5}> Progress:  </Title> <Progress showInfo={true} percent={stateValue.bulkSubmitData.progressBar} /> </> }
            <Divider />
        </Modal>
    )
}
export default DirectoryModal;