import React, {useEffect, useRef, useState } from "react";

import {Typography, Layout, Button, Space, Select, Input} from 'antd';

import {  headerStyle, selectStyle} from "../../Utils/UtilData";

import { useStateValue } from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

import {getDirList, getRubbishFile, getRubbishFileType} from "../../Utils/Data";

import RubbishConfirmationModal from "./RubbishConfirmationModal";

const { Header } = Layout;

const { Title } = Typography;

function RubbishHeader() {

    const [ stateValue, dispatch ] = useStateValue();

    const [ filterItems, setFilterItems ] = useState( [] );

    // paged
    const statusFilterRef = useRef(null);

    const perPageRef = useRef(null);

    const fileTypeFilterRef = useRef(null);

    const sharedProps = {
        ref: statusFilterRef,
    };

    const perPageProps = {
        ref: perPageRef,
    };

    const fileTypeFilterRefProps = {
        ref: fileTypeFilterRef,
    };

    const getTheRubbishFileType = async () => {
        const rubbishFile = await getRubbishFileType();
        const types = await rubbishFile.fileTypes.map(
           ( item ) => ( {
                value: item,
                label: item
            })
        );
        await setFilterItems( [
            { value: '', label: 'Default' },
            ...types
        ] );
    }

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

    const statusFilterApply = (value) => {

        handleChangeBulkType( 'default' );

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

    const fileTypeFilterApply = (value) => {
        dispatch({
            type: Types.RUBBISH_MEDIA,
            rubbishMedia: {
                ...stateValue.rubbishMedia,
                isLoading: true,
                postQuery:{
                    ...stateValue.rubbishMedia.postQuery,
                    filterExtension: value,
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
        dispatch({
            type: Types.BALK_RUBBISH,
            bulkRubbishData: {
                ...stateValue.bulkRubbishData,
                isModalOpen: true,
            },
        });

    };

    let options = [
        { value: 'default', label: 'Bulk Action' },
        { value: 'delete', label: 'Delete' },
        { value: 'ignore', label: 'Ignore' },
    ];

    if( 'ignore' == stateValue.rubbishMedia.postQuery.fileStatus ){
        options = [
            { value: 'default', label: 'Bulk Action' },
            { value: 'show', label: 'Make Deletable' },
        ];
    }

    useEffect(() => {
        getTheRubbishFileType();
    }, [] );

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
                    value={ stateValue.bulkRubbishData.type || `default` }
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
                        statusFilterRef.current.focus({
                            cursor: 'start',
                        });
                    }}
                >
                    Status
                </Button>
                <Select
                    {...sharedProps}
                    size="large"
                    placeholder={'Show'}
                    defaultValue={ stateValue.rubbishMedia.postQuery.fileStatus }
                    style={ { ...selectStyle, width: 150 } }
                    options={ [
                        { value: 'show', label: 'Default' },
                        { value: 'ignore', label: 'Ignored Filte' }
                    ] }
                    onChange={statusFilterApply}
                />

                <Button
                    type="text"
                    size="large"
                    onClick={() => {
                        fileTypeFilterRef.current.focus({
                            cursor: 'start',
                        });
                    }}
                >
                    Extension
                </Button>
                <Select
                    {...fileTypeFilterRefProps}
                    size="large"
                    placeholder={'PNG/JPG'}
                    defaultValue={ stateValue.rubbishMedia.postQuery.filterExtension }
                    style={ { ...selectStyle, width: 150 } }
                    options={ filterItems }
                    onChange={fileTypeFilterApply}
                />
                <Button
                    style={{
                        width: '150px',
                        borderColor: '#d9d9d9'
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
                        perPageRef.current.focus({
                            cursor: 'start',
                        });
                    }}
                >
                    Items Per page
                </Button>

                <Input
                    {...perPageProps}
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