import React, { useContext } from "react";

import {Menu, Layout, Button, Space} from 'antd';

import { SettingOutlined, UnorderedListOutlined, EditOutlined } from '@ant-design/icons';

import {TheAppContext, TheMediaTableContext} from "../../Utils/TheContext";
import {headerStyle} from "../../Utils/UtilData";

const { Header } = Layout;


function RenamerMainHeader() {
    const formEdited = false;
    return (
        <Header style={headerStyle}>
            <Space wrap>
                <Button
                    style={{
                        width: '180px'
                    }}
                    type="primary"
                    size="large"
                    ghost={ ! formEdited }>  { formEdited ? 'Disable Edit Mode' : 'Enable Edit Mode' }
                </Button>
            </Space>
        </Header>
    );
}

export default RenamerMainHeader;