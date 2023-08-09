import React, {useEffect, useRef, useState} from "react";

import {Typography, Layout, Button, Space, Select} from 'antd';

import {bulkOprions, defaultBulkSubmitData, headerStyle, selectStyle} from "../../Utils/UtilData";

import { useStateValue } from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

import {getDirList, rubbishBulkDeleteAction, rubbishBulkIgnoreAction} from "../../Utils/Data";

const { Header } = Layout;

const { Title } = Typography;

function RubbishHeader() {

    const [ stateValue, dispatch ] = useStateValue();
    // paged
    const inputRef = useRef(null);

    const sharedProps = {
        ref: inputRef,
    };

    const handleDirForModal = async () => {
        if( ! stateValue.generalData.isDirModalOpen ){
            return;
        }
        const responseDate = await getDirList();
        const preparedDate =  await JSON.parse( responseDate.data );
        await dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                scanRubbishDirList: preparedDate.dirList,
                scanDirNextSchedule: preparedDate.nextSchedule,
                scanRubbishDirLoading: false,
            },
        });
        console.log( 'getDirList' )
    };

    const openDirModal = () => {
        dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                isDirModalOpen: true
            },
        });
    };

    const handleChangeBulkType = (value) => {
        dispatch({
            type: Types.BALK_RUBBISH,
            bulkRubbishData: {
                ...stateValue.bulkRubbishData,
                type: value
            },
        });
    };


    const handleFilterApply = (value) => {
        dispatch({
            type: Types.RUBBISH_MEDIA,
            rubbishMedia: {
                ...stateValue.rubbishMedia,
                isLoading: true,
                postQuery:{
                    ...stateValue.rubbishMedia.postQuery,
                    fileStatus: value,
                    paged: 1,
                }
            },
        });
    };

    const handleBulkSubmit = async () => {
        if ( ! tsmltParams.hasExtended ){
            await dispatch({
                type: Types.GENERAL_DATA,
                generalData: {
                    ...stateValue.generalData,
                    openProModal: true,
                },
            });
            return;
        }

        switch( stateValue.bulkRubbishData.type ){
            case 'delete':
                await rubbishBulkDeleteAction( stateValue.bulkRubbishData );
                break;
            case 'ignore':
                await rubbishBulkIgnoreAction( stateValue.bulkRubbishData );
                break;
            default:
        }

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

        await dispatch({
            type: Types.BALK_RUBBISH,
            bulkRubbishData: {
                ...stateValue.bulkRubbishData,
                bulkChecked : false,
                ids: [],
                files: []
            },
        });


    };

    const options = [
        { value: 'default', label: 'Bulk Action' },
        { value: 'delete', label: 'Delete' },
        { value: 'ignore', label: 'Ignore' },
    ];

    useEffect(() => {
        handleDirForModal();
    }, [ stateValue.generalData.isDirModalOpen ] );

    return (
        <Header style={{...headerStyle, height: 'inherit'}}>
            <Space>
                <Select
                    style={{ width: '150px' }}
                    size="large"
                    defaultValue={`default`}
                    onChange={handleChangeBulkType}
                    options={ options }
                />
                <Button
                    type="primary"
                    size="large"
                    onClick={handleBulkSubmit}
                > Bulk Actions </Button>
                <Button
                    type="text"
                    size="large"
                    onClick={() => {
                        inputRef.current.focus({
                            cursor: 'start',
                        });
                    }}
                >
                    Filter Items
                </Button>
                <Select
                    {...sharedProps}
                    size="large"
                    allowClear = {true}
                    placeholder={'Show'}
                    defaultValue={ stateValue.rubbishMedia.postQuery.fileStatus }
                    style={selectStyle}
                    options={ [
                        { value: 'show', label: 'Default' },
                        { value: 'ignore', label: 'Ignored Filte' }
                    ] }
                    onChange={handleFilterApply}
                />

                <Button
                    style={{
                        width: '200px'
                    }}
                    type="primary"
                    size="large"
                    onClick={openDirModal}
                    ghost={ ! stateValue.generalData.isDirModalOpen }>
                    { `Directory List` }
                </Button>
                <Title level={5} style={{
                    margin:'0 15px',
                    color: 'red'
                }}> Rubbish File Note : A "Rubbish File" refers to a file that exists within a directory but is not included in the media library or database. </Title>
            </Space>
        </Header>
    );
}

export default RubbishHeader;