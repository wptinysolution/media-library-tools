import React from 'react';

import {
    Layout,
    Typography
} from 'antd';
import MainHeader from "./MainHeader";

const { Content } = Layout;

const { Title, Paragraph  } = Typography;

function NeedSupport() {

    return (
        <>
            <MainHeader/>
            <Layout style={{ position: 'relative' }}>
            <Content style={{
                padding: '150px',
                background: 'rgb(255 255 255 / 35%)',
                borderRadius: '5px',
                boxShadow: 'rgb(0 0 0 / 1%) 0px 0 20px',
            }}>
                    <Title level={5} style={{ margin:'0 0 15px 0', fontSize: '20px'}}> For faster support please send detail of your issue.</Title>

                    <Paragraph type="secondary" style={{ fontSize: '18px'}}>
                        Visit Our Support Center: <a target={`_blank`} href={`https://help.wptinysolutions.com/`}> Contact Support </a>
                    </Paragraph>
                    <Paragraph type="secondary" style={{ fontSize: '18px'}}>
                        This will create a ticket. We will response form there.
                    </Paragraph >
                    <Paragraph type="secondary" style={{ fontSize: '18px'}}>
                        Check our  <a href={`https://www.wptinysolutions.com/tiny-products/`} target={`_blank`}> Plugins List </a>
                    </Paragraph>
            </Content>
        </Layout>
    </>
    );
};

export default NeedSupport;