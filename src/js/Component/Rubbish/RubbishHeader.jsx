import React, {useEffect, useRef } from "react";

import {Typography, Layout, Button, Space, Select, Input} from 'antd';

import {  headerStyle, selectStyle} from "../../Utils/UtilData";

import { useStateValue } from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

import { getDirList } from "../../Utils/Data";

import RubbishConfirmationModal from "./RubbishConfirmationModal";

const { Header } = Layout;

const { Title } = Typography;

function RubbishHeader() {

    const [ stateValue, dispatch ] = useStateValue();
    // paged
    const inputRef = useRef(null);
    const selectRef = useRef(null);

    const sharedProps = {
        ref: inputRef,
    };

    const selectProps = {
        ref: selectRef,
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
        let message = '' ;
        switch( stateValue.bulkRubbishData.type ){
            case 'delete':
                message = 'Delete ?'
                break;
            case 'ignore':
                message = 'Ignore ?'
                break;
            default:
        }

        dispatch({
            type: Types.BALK_RUBBISH,
            bulkRubbishData: {
                ...stateValue.bulkRubbishData,
                isModalOpen: true,
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
            <Title level={5} style={{
                border: '1px solid #f0f0f0',
                padding: '10px 15px',
                margin: '10px 0',
                fontSize:'13px',
                color: 'red'
            }}>
                Rubbish File Note: A "Rubbish File" refers to a file that exists within a directory but is not included in the media library or database.
                Before making any changes it is highly recommended to take a backup.
            </Title>

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
                    style={ { ...selectStyle, width: 150 } }
                    options={ [
                        { value: 'show', label: 'Default' },
                        { value: 'ignore', label: 'Ignored Filte' }
                    ] }
                    onChange={handleFilterApply}
                />

                <Button
                    style={{
                        width: '150px'
                    }}
                    type="primary"
                    size="large"
                    onClick={openDirModal}
                    ghost={ ! stateValue.generalData.isDirModalOpen }>
                    { `Directory List` }
                </Button>
                <Button
                    type="text"
                    size="large"
                    onClick={() => {
                        selectRef.current.focus({
                            cursor: 'start',
                        });
                    }}
                >
                    Items Per page
                </Button>

                <Input
                    {...selectProps}
                    type="primary"
                    size="large"
                    style={{
                        width: '50px'
                    }}
                    onBlur={ async (event) => {
                        await dispatch({
                            ...stateValue,
                            type: Types.UPDATE_OPTIONS,
                            saveType: Types.UPDATE_OPTIONS,
                        });
                        await dispatch({
                            type: Types.RUBBISH_MEDIA,
                            rubbishMedia: {
                                ...stateValue.rubbishMedia,
                                isLoading: true,
                            },
                        });
                    }  }
                    onChange={
                        (event) => {
                            dispatch({
                                type: Types.UPDATE_OPTIONS,
                                options : {
                                    ...stateValue.options,
                                    rubbish_per_page: event.target.value,
                                }
                            });
                        }
                    }
                    value={stateValue.options.rubbish_per_page}
                />

            </Space>
            <RubbishConfirmationModal/>
        </Header>
    );
}

export default RubbishHeader;