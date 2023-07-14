import React from "react";

import { Menu, Layout } from 'antd';

import {
    EditOutlined,
    DeleteOutlined,
    SettingOutlined,
    ContactsOutlined,
    UnorderedListOutlined
} from '@ant-design/icons';

import {useStateValue} from "../Utils/StateProvider";
import * as Types from "../Utils/actionType";

const { Header } = Layout;

function MainHeader() {

    const [stateValue, dispatch] = useStateValue();

    const menuItemStyle = {
        borderRadius: 0,
        paddingInline: '10px',
    }
    const menuItems = [
        {
            key: 'settings',
            label: 'Media Settings',
            icon: <SettingOutlined />,
            style: menuItemStyle
        },
        {
            key: 'mediaTable',
            label: 'Media Table',
            icon: <UnorderedListOutlined />,
            style: menuItemStyle,
        },
        {
            key: 'mediaRename',
            label: 'Media Rename',
            icon: <EditOutlined />,
            style: menuItemStyle,
        },
        {
            key: 'rubbishFile',
            label: 'Rubbish file',
            icon: <DeleteOutlined />,
            style: menuItemStyle,
        },
        {
            key: 'needSupport',
            label: 'Need Support',
            icon: <ContactsOutlined />,
            style: menuItemStyle,
        }
    ];

    // if ( tsmltParams.hasExtended ){
    //     menuItems.unshift({
    //         key: 'hasExtended',
    //         label: 'Licence',
    //         icon: <SettingOutlined />,
    //         style: menuItemStyle
    //     });
    // }

    return (

        <Header className="header" style={{
            paddingInline: 0,
        }}>
            <div className="logo" style={{
                height: '40px',
                margin: '10px',
                background: 'rgba(255, 255, 255, 0.2)'
            }}/>
            <Menu
                style={{
                    borderRadius: '0px',
                }}
                theme="dark"
                mode="inline"
                defaultSelectedKeys={[stateValue.generalData.selectedMenu]}
                items={menuItems}
                onSelect={ ({ item, key, keyPath, selectedKeys, domEvent }) => {
                    dispatch({
                        type: Types.GENERAL_DATA,
                        generalData:{
                            ...stateValue.generalData,
                            selectedMenu : key
                        }
                    });
                    localStorage.setItem( "current_menu", key );
                } }
            />
        </Header>
    );
}

export default MainHeader;