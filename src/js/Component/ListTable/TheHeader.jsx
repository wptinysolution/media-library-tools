import React, {useContext, useRef} from "react";

import {TheAppContext, TheMediaTableContext} from "../../Utils/TheContext";

import {  Input, Select, Layout, Button, Space } from 'antd';

import {
    headerStyle,
    selectStyle,
    bulkOprions
} from '../../Utils/UtilData'

const { Header } = Layout;

function TheHeader() {
    const {
        dateList,
        termsList,
        optionsData,
        setOptionsData,
        handleUpdateOption
    } = useContext( TheAppContext );
    const {
        postQuery,
        formEdited,
        setFiltering,
        filtering,
        handleFilterData,
        handleBulkSubmit,
        handleChangeBulkType,
        handleColumnEditMode,
    } = useContext( TheMediaTableContext );
    // paged
    const inputRef = useRef(null);

    const sharedProps = {
        ref: inputRef,
    };

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
                    allowClear = {true}
                    placeholder={'Status'}
                    size="large"
                    style={selectStyle}
                    onChange={(value) =>
                        setFiltering({
                            ...filtering,
                            status: value,
                        })
                    }
                    options={[
                        {
                            value: 'trash',
                            label: 'Trash',
                        },

                    ]}
                />
                <Select
                    size="large"
                    allowClear = {true}
                    placeholder={'Date'}
                    style={selectStyle}
                    onChange={ (value) => setFiltering({
                        ...filtering,
                        date: value,
                    }) }
                    options={dateList}
                />
                <Select
                    allowClear = {true}
                    size="large"
                    placeholder={'Categories'}
                    style={selectStyle}
                    onChange={(value) => setFiltering({
                        ...filtering,
                        categories: value,
                    })
                    }
                    options={termsList}
                />
                <Button
                    type="primary"
                    size="large"
                    onClick={handleFilterData}
                > Filter </Button>
                <Button
                    style={{
                        width: '180px'
                    }}
                    type="primary"
                    size="large"
                    onClick={ () => handleColumnEditMode() }
                    ghost={ ! formEdited }>  { formEdited ? 'Disable Edit Mode' : 'Enable Edit Mode' }
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
                    onBlur={ handleUpdateOption }
                    onChange={
                        (event) => setOptionsData({
                            ...optionsData,
                            media_per_page : event.target.value,
                        })
                    }
                    value={optionsData.media_per_page}
                />
            </Space>
        </Header>
    );
}

export default TheHeader;