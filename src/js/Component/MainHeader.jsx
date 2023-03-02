import React, { useContext } from "react";

import { Menu, Layout } from 'antd';

import {TheContext} from "../Utils/TheContext";

const { Header } = Layout;

function MainHeader() {
    const {
        setSelectedMenu
    } = useContext( TheContext );

    return (

        <Header className="header" style={{
            paddingInline: 0,
        }}>
            <Menu
                theme="dark"
                mode="horizontal"
                defaultSelectedKeys={['mediatbale']}
                style={{
                    fontSize: '18px',
                    color: '#fff',
                    fontWeight: 600,
                }}
                items={[
                    {
                        key: 'mediatbale',
                        label: 'Media Table',
                    },

                    {
                        key: 'settings',
                        label: 'Settings',
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