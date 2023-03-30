import React, {useContext, useRef} from "react";

import {Typography, Layout, Button, Space, Input} from 'antd';

import { SettingOutlined, UnorderedListOutlined, EditOutlined } from '@ant-design/icons';

const { Text, Title } = Typography;


import {TheAppContext, TheMediaTableContext} from "../../Utils/TheContext";
import {headerStyle} from "../../Utils/UtilData";

const { Header } = Layout;


function RenamerMainHeader() {

    const {
        optionsData,
        setOptionsData,
        handleUpdateOption
    } = useContext( TheAppContext );

    const {
        formEdited,
        handleColumnEditMode,
    } = useContext( TheMediaTableContext );

    // paged
    const inputRef = useRef(null);

    const sharedProps = {
        ref: inputRef,
    };

    return (
        <Header style={{...headerStyle, height: 'inherit'}}>

            <Space wrap>
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
                            media_per_page: event.target.value,
                        })
                    }
                    value={optionsData.media_per_page}
                />
                <Title level={5} style={{
                    margin:'0 15px',
                    color: 'red'
                }}>Renamer note: Please Backup First before edit "File Name"</Title>
            </Space>
        </Header>
    );
}

export default RenamerMainHeader;