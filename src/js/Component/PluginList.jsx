import React, { useEffect, useState } from 'react';

import Loader from '../Utils/Loader';
import { Avatar, Card } from 'antd';
const { Meta } = Card;
import {
    Button,
    Layout
} from 'antd';

import {getPluginList} from "../Utils/Data";

import MainHeader from "./MainHeader";

function PluginList() {

    const [ pluginList, setPluginList ] = useState( [] );

    const getThePluginList = async () => {
        const response = await getPluginList();
        const preparedData =  await JSON.parse( response.data );
        await setPluginList( preparedData );
    }

    const decodeHTMLEntities = (text) => {
        const textArea = document.createElement('textarea');
        textArea.innerHTML = text;
        return textArea.value;
    };

    useEffect(() => {
        getThePluginList();
    }, [] );

    return (
        <>
            <MainHeader/>

            <Layout style={{
                position: 'relative',
                height:'85vh',
                padding: '30px',
                display: 'flex',
                flexWrap: 'wrap',
                flexDirection: 'row',
                gap: '15px',
                overflowY: 'auto',

            }}>
            { pluginList.length > 0 ?
                pluginList.map( ( plugin, index) => {
                    const iframeUrl = decodeHTMLEntities(plugin.TB_iframe);
                    return (
                        <Card
                            className={`plugin-list-wrapper`}
                            key={index}

                            actions={[
                                <a target={`_blank`} className="thickbox open-plugin-details-modal"
                                   href={iframeUrl}>
                                    <Button type="link" size={`large`}>Details</Button>
                                </a>,
                                <a target={`_blank`} href={`https://www.wptinysolutions.com/tiny-products/${plugin.slug}`}>
                                    <Button type="link" size={`large`}>  Visit Website </Button>
                                </a>
                            ]}
                        >
                            <Meta
                                avatar={<a style={{
                                    display: 'block',
                                    lineHeight: 0
                                }} target={`_blank`} className="thickbox open-plugin-details-modal" href={plugin.TB_iframe}>
                                    <Avatar shape="square" size={130} src={plugin?.icons['2x']}/>
                                </a>}
                                title={<a target={`_blank`} className="thickbox open-plugin-details-modal" href={plugin.TB_iframe}>
                                    <span dangerouslySetInnerHTML={{__html: plugin.plugin_name}}/>
                                </a>}
                                description={<span dangerouslySetInnerHTML={{__html: plugin.short_description}}/>}
                            />
                        </Card>
                    )}) :  <Loader/>
            }

        </Layout>
    </>
    );
};

export default PluginList;