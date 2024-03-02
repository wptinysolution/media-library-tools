import React from "react";

import { Menu, Layout } from 'antd';

import {
    EditOutlined,
    ExportOutlined,
    DeleteOutlined,
    SettingOutlined,
    ContactsOutlined,
    UnorderedListOutlined
} from '@ant-design/icons';

import {useStateValue} from "../Utils/StateProvider";

import * as Types from "../Utils/actionType";

import {clearSchedule} from "../Utils/Data";

const { Header } = Layout;

function MainHeader() {

    const [stateValue, dispatch] = useStateValue();

    const menuItemStyle = {
        borderRadius: 0,
        paddingInline: '25px',
        display: 'inline-flex',
        alignItems: 'center',
        fontSize:'15px'
    }
    const iconStyle = {
        fontSize: '18px',
    }
    const menuItems = [
        {
            key: 'settings',
            label: 'Media Settings',
            icon: <SettingOutlined style={iconStyle} />,
            style: menuItemStyle
        },
        {
            key: 'mediaTable',
            label: 'Media Table',
            icon: <UnorderedListOutlined style={iconStyle} />,
            style: menuItemStyle,
        },
        {
            key: 'mediaRename',
            label: 'Media Rename',
            icon: <EditOutlined style={iconStyle} />,
            style: menuItemStyle,
        },
        {
            key: 'exportImport',
            label: 'CSV Import',
            icon: <ExportOutlined style={iconStyle} />,
            style: menuItemStyle,
        },
        {
            key: 'rubbishFile',
            label: 'Rubbish file',
            icon: <DeleteOutlined style={iconStyle} />,
            style: menuItemStyle,
        },
        {
            key: 'needSupport',
            label: 'Get Support',
            icon: <ContactsOutlined style={iconStyle} />,
            style: menuItemStyle,
        }
    ];

    return (

        <Header className="header" style={{
            paddingInline: 0,
            height: '65px',
        }}>
            <Menu
                style={{
                    borderRadius: '0px',
                    height: '100%',
                    display: 'flex',
                    flex: 1,
                }}
                theme="dark"
                mode="horizontal"
                defaultSelectedKeys={[stateValue.generalData.selectedMenu]}
                items={menuItems}
                onSelect={ ({ item, key, keyPath, selectedKeys, domEvent }) => {
                   if( 'rubbishFile' == key ){
                       clearSchedule()
                   }
                    dispatch({
                        type: Types.BULK_SUBMIT,
                        bulkSubmitData:{
                            ...stateValue.bulkSubmitData,
                            bulkChecked : false,
                            ids: []
                        }
                    });
                    dispatch({
                        type: Types.GENERAL_DATA,
                        generalData:{
                            ...stateValue.generalData,
                            selectedMenu : key
                        }
                    });
                    localStorage.setItem( "mlts_current_menu", key );
                } }
            />
        </Header>
    );
}

export default MainHeader;