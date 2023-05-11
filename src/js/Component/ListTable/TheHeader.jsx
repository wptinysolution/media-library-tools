import React, {useContext, useEffect, useRef, useState} from "react";

import {TheAppContext, TheMediaTableContext} from "../../Utils/TheContext";

import {  Input, Select, Layout, Button, Space } from 'antd';


import {
    headerStyle,
    selectStyle,
    bulkOprions
} from '../../Utils/UtilData'

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

const { Header } = Layout;

function TheHeader() {

    const [stateValue, dispatch] = useStateValue();

    // const {
    //     postQuery,
    //     formEdited,
    //     setFiltering,
    //     filtering,
    //     handleBulkSubmit,
    //     handleChangeBulkType,
    //     handleColumnEditMode,
    // } = useContext( TheMediaTableContext );

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

    return (
        <Header style={headerStyle}>
            <Space wrap>
                <Select
                    size="large"
                    defaultValue={``}
                    style={selectStyle}
                    options={ bulkOprions }
                />

                {/*<Select*/}
                {/*    size="large"*/}
                {/*    defaultValue={``}*/}
                {/*    style={selectStyle}*/}
                {/*    onChange={handleChangeBulkType}*/}
                {/*    options={*/}
                {/*        postQuery.filtering && 'trash' == postQuery.status ? [...bulkOprions.filter(item => 'trash' !== item.value)] : [...bulkOprions.filter(item => 'inherit' !== item.value)]*/}
                {/*    }*/}
                {/*/>*/}

                <Button
                    type="primary"
                    size="large"
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