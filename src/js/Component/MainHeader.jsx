import React from "react";

import { Menu, Layout } from 'antd';

import {
    EditOutlined,
    LikeOutlined,
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

import {Link, useLocation} from "react-router-dom";

function MainHeader() {

    let { pathname } = useLocation();

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
            key: '/',
            label: <Link to={`/`}> Media Settings </Link>,
            icon: <SettingOutlined style={iconStyle} />,
            style: menuItemStyle
        },
        {
            key: '/mediaTable',
            label: <Link to={`/mediaTable`}> Media Table </Link>,
            icon: <UnorderedListOutlined style={iconStyle} />,
            style: menuItemStyle,
        },
        {
            key: '/mediaRename',
            label: <Link to={`/mediaRename`}> Media Rename </Link>,
            icon: <EditOutlined style={iconStyle} />,
            style: menuItemStyle,
        },
        {
            key: '/exportImport',
            label: <Link to={`/exportImport`}> CSV Import</Link>,
            icon: <ExportOutlined style={iconStyle} />,
            style: menuItemStyle,
        },
        {
            key: '/rubbishFile',
            label: <Link to={`/rubbishFile`}> Rubbish files </Link>,
            icon: <DeleteOutlined style={iconStyle} />,
            style: menuItemStyle,
        },
        {
            key: '/plugins',
            label: <Link to={`/plugins`}> Useful Plugins </Link>,
            icon: <LikeOutlined />,
            style: menuItemStyle,
        },
        {
            key: '/support',
            label: <Link to={`/support`}> Get Support </Link>,
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
                defaultSelectedKeys={[ pathname ]}
                items={menuItems}
                onSelect={ ({ item, key, keyPath, selectedKeys, domEvent }) => {
                   if( '/rubbishFile' == key ){
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
                   // localStorage.setItem( "mlts_current_menu", key );
                } }
            />
        </Header>
    );
}

export default MainHeader;