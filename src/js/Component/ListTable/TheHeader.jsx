import React, {useEffect, useRef} from "react";

import {  Input, Select, Layout, Button, Space } from 'antd';

import { SearchOutlined } from '@ant-design/icons';

import {
    headerStyle,
    selectStyle,
    bulkOprions,
    defaultBulkSubmitData
} from '../../Utils/UtilData'

import {useStateValue} from "../../Utils/StateProvider";

import {useSearchDebounce} from "../../Utils/Hooks";

import * as Types from "../../Utils/actionType";

import {notifications} from "../../Utils/Data";

const { Header } = Layout;

function TheHeader() {

    const [stateValue, dispatch] = useStateValue();

    const [search, setSearch] = useSearchDebounce();

    // paged
    const inputRef = useRef(null);

    const sharedProps = {
        ref: inputRef,
    };

    const handleSelectChange = (value, fieldName) => {
        dispatch({
            type: Types.GET_MEDIA_LIST,
            mediaData: {
                ...stateValue.mediaData,
                isLoading: true,
                postQuery : {
                    ...stateValue.mediaData.postQuery,
                    filtering : true,
                    paged: 1,
                    [fieldName]: value
                }
            },
        });

        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: defaultBulkSubmitData,
        });

    };

    const handleChangeBulkType = (value) => {
        const data = 'bulkedit' === value ? stateValue.bulkSubmitData.data : defaultBulkSubmitData.data;
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...stateValue.bulkSubmitData,
                type: value,
                data,
            },
        });
    };

    const handleBulkSubmit = () => {

        if ( 'bulkEditPostTitle' == stateValue.bulkSubmitData.type && ! tsmltParams.hasExtended ){
            dispatch({
                type: Types.GENERAL_DATA,
                generalData: {
                    ...stateValue.generalData,
                    openProModal: true,
                },
            });
            return;
        }

        if( ! stateValue.bulkSubmitData.ids.length ){
            notifications( false, 'No checkboxes are checked. Please select at least one item.' );
            return;
        }

        switch( stateValue.bulkSubmitData.type ){
            case 'searchUses':
                break;
            case 'csv_export':
                dispatch({
                    ...stateValue,
                    type: Types.EXPORT_CSV,
                    saveType: Types.EXPORT_CSV,
                    bulkExport: {
                        ...stateValue.bulkExport,
                        isModalOpen : true,
                    },
                });
                break;
            case 'trash':
            case 'inherit':
            case 'update':
            case 'delete':
                dispatch({
                    ...stateValue,
                    type: Types.BULK_SUBMIT,
                    saveType: Types.BULK_SUBMIT,
                });
                break;
            case 'bulkedit':
            case 'bulkEditPostTitle':
                dispatch({
                    ...stateValue,
                    type: Types.BULK_SUBMIT,
                    saveType: null,
                    bulkSubmitData: {
                        ...stateValue.bulkSubmitData,
                        isModalOpen : true,
                    },
                });
                break;
            default:
                notifications( false, 'No Actions are selected. Please select one.' );
        }

    };

    const upDateQuery = async () => {
        if( stateValue.mediaData.postQuery.searchKeyWords === search ){
            return;
        }
        await dispatch({
            type: Types.GET_MEDIA_LIST,
            mediaData: {
                ...stateValue.mediaData,
                postQuery : {
                    ...stateValue.mediaData.postQuery,
                    searchKeyWords : search
                }
            },
        });
        console.log( search )
    };

    const postQuery =  stateValue.mediaData.postQuery;

    useEffect(() => {
        upDateQuery();
    }, [ search ]);

    return (
        <Header style={headerStyle}>
            <Space wrap>
                <Select
                    allowClear={true}
                    size="large"
                    placeholder={'Bulk Apply'}
                    style={ { ...selectStyle, minWidth: '280px', width: 'inherit' } }
                    onChange={handleChangeBulkType}
                    options={
                        postQuery.filtering && 'trash' == postQuery.status ? [...bulkOprions.filter(item => 'trash' !== item.value)] : [...bulkOprions.filter(item => 'inherit' !== item.value)]
                    }
                />

                <Button
                    type="primary"
                    size="large"
                    onClick={handleBulkSubmit}
                > Bulk Apply </Button>

                <Select
                    size="large"
                    allowClear = {true}
                    placeholder={'Status'}
                    style={selectStyle}
                    defaultValue={ stateValue.mediaData.postQuery.status || null }
                    options={[
                        {
                            value: 'trash',
                            label: 'Trash',
                        },
                    ]}
                    onChange={(value) => handleSelectChange(value, 'status')}
                />

                <Select
                    size="large"
                    allowClear = { true }
                    placeholder={ 'All dates' }
                    style={ selectStyle }
                    defaultValue={ stateValue.mediaData.postQuery.date || null  }
                    options={ stateValue.generalData.dateList }
                    onChange={(value) => handleSelectChange(value, 'date')}
                />

                <Select
                    allowClear = {true}
                    size="large"
                    placeholder={'Categories'}
                    style={selectStyle}
                    options={stateValue.generalData.termsList}
                    defaultValue={ stateValue.mediaData.postQuery.categories || null }
                    onChange={(value) => handleSelectChange(value, 'categories')}
                />

                <Input
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                    }}
                    type="primary"
                    size="large"
                    addonAfter={<SearchOutlined />}
                    placeholder="Keywords..."
                    onChange={(e) => setSearch(e.target.value)}
                />

                <Button
                    style={{  width: '180px', borderColor: '#d9d9d9' }}
                    type="primary"
                    size="large"
                    onClick={
                        (event) => dispatch({
                            type: Types.UPDATE_SINGLE_MEDIA,
                            singleMedia : {
                                ...stateValue.singleMedia,
                                formEdited: ! stateValue.singleMedia.formEdited,
                            }
                        })
                    }
                    ghost={ ! stateValue.singleMedia.formEdited }>  { stateValue.singleMedia.formEdited ? 'Disable Edit Mode' : 'Enable Edit Mode' }
                </Button>

                <Button
                    type="text"
                    size="large"
                    onClick={() => {
                        inputRef.current.focus({
                            cursor: 'start',
                        });
                    }}
                >
                    Items Per page
                </Button>

                <Input
                    {...sharedProps}
                    type="primary"
                    size="large"
                    style={{
                        width: '80px'
                    }}
                    onBlur={ (event) => dispatch({
                        ...stateValue,
                        type: Types.UPDATE_OPTIONS,
                        saveType: Types.UPDATE_OPTIONS,
                    }) }
                    onChange={
                        (event) => {
                            dispatch({
                                type: Types.UPDATE_OPTIONS,
                                options : {
                                    ...stateValue.options,
                                    media_per_page: event.target.value,
                                }
                            });

                        }
                    }
                    value={stateValue.options.media_per_page}
                />

            </Space>

        </Header>
    );
}

export default TheHeader;