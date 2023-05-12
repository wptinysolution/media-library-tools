import React, { useRef} from "react";

import {  Input, Select, Layout, Button, Space } from 'antd';

import {
    headerStyle,
    selectStyle,
    bulkOprions,
    defaultBulkSubmitData
} from '../../Utils/UtilData'

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

const { Header } = Layout;

import { submitBulkMediaAction } from "../../Utils/Data";

function TheHeader() {

    const [stateValue, dispatch] = useStateValue();

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


    const handleBulkSubmit = async (event) => {
        let response = false;
        switch( stateValue.bulkSubmitData.type ){
            case 'trash':
            case 'inherit':
            case 'update':
            case 'delete':
                response = await submitBulkMediaAction( stateValue.bulkSubmitData );
                await dispatch({
                    type: Types.BULK_SUBMIT,
                    bulkSubmitData: defaultBulkSubmitData,
                });
                console.log( 'submitBulkMediaAction' );
                break;
            case 'bulkedit':
                dispatch({
                    type: Types.BULK_SUBMIT,
                    bulkSubmitData: {
                        ...stateValue.bulkSubmitData,
                        isModalOpen : true,
                    },
                });
                break;
            default:
        }

        if( 200 === parseInt( response.status ) && response.data.updated ){
            await dispatch({
                type: Types.GET_MEDIA_LIST,
                mediaData: {
                    ...stateValue.mediaData,
                    postQuery: {
                        ...stateValue.mediaData.postQuery,
                        isUpdate: ! stateValue.mediaData.postQuery.isUpdate,
                    },
                },
            });
        }

    };

    const postQuery =  stateValue.mediaData.postQuery;

    return (
        <Header style={headerStyle}>
            <Space wrap>
                <Select
                    size="large"
                    defaultValue={``}
                    style={selectStyle}
                    onChange={handleChangeBulkType}
                    options={
                        postQuery.filtering && 'trash' == postQuery.status ? [...bulkOprions.filter(item => 'trash' !== item.value)] : [...bulkOprions.filter(item => 'inherit' !== item.value)]
                    }
                />
                <Button
                    type="primary"
                    size="large"
                    onClick={handleBulkSubmit}
                > Apply </Button>
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

                <Button
                    style={{  width: '180px' }}
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
                        width: '50px'
                    }}
                    onBlur={ (event) => dispatch({
                        ...stateValue,
                        type: Types.UPDATE_OPTIONS,
                        saveType: Types.UPDATE_OPTIONS,
                    }) }
                    onChange={
                        (event) => dispatch({
                            type: Types.UPDATE_OPTIONS,
                            options : {
                                ...stateValue.options,
                                media_per_page: event.target.value,
                            }
                        })
                    }
                    value={stateValue.options.media_per_page}
                />
            </Space>
        </Header>
    );
}

export default TheHeader;