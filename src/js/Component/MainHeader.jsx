import React, { useContext } from "react";

import { Menu, Layout } from 'antd';

import { SettingOutlined, UnorderedListOutlined, EditOutlined } from '@ant-design/icons';

import {TheAppContext, TheMediaTableContext} from "../Utils/TheContext";

const { Header } = Layout;


function MainHeader() {
    const {
        selectedMenu,
        setSelectedMenu
    } = useContext( TheAppContext );

    const menuItemStyle = {
        borderRadius: 0,
        paddingInline: '10px',
    }

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
                defaultSelectedKeys={[selectedMenu]}
                items={[
                    {
                        key: 'mediatable',
                        label: 'Media Table',
                        icon: <UnorderedListOutlined />,
                        style: menuItemStyle,
                    },
                    {
                        key: 'settings',
                        label: 'Media Settings',
                        icon: <SettingOutlined />,
                        style: menuItemStyle
                    },
                ]}
                onSelect={ ({ item, key, keyPath, selectedKeys, domEvent }) => {
                    setSelectedMenu( key );
                } }
            />
        </Header>
    );
}

export default MainHeader;