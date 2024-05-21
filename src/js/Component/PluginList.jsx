import React, { useEffect, useState } from 'react';

import Loader from '../Utils/Loader';
import { Avatar, Card, Anchor } from 'antd';
const { Meta } = Card;
import {
    Button,
    Layout,
    Typography
} from 'antd';

const { Title, Paragraph  } = Typography;

import {getPluginList} from "../Utils/Data";

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
                            key={index}
                            style={{
                                maxWidth:'calc( ( 100% - calc( 15px * 1 ) ) / 2)',
                                flex: '0 0 calc(( 100% - calc( 15px * 1 ) ) / 2)',
                                alignSelf: 'flex-start'
                            }}
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
                                avatar={<a target={`_blank`} className="thickbox open-plugin-details-modal" href={plugin.TB_iframe}>
                                    <Avatar size={130} src={plugin?.icons['2x']}/>
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


    );
};

export default PluginList;