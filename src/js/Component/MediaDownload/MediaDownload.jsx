import React from 'react';

import {useStateValue} from "../../Utils/StateProvider";

import MainHeader from "../MainHeader";

import {
    Divider,
    Layout,
    Typography,
    Col,
    Row
} from 'antd';

const { Title, Text, Paragraph } = Typography;

import Loader from "../../Utils/Loader";

const { Content } = Layout;

/**
 *
 * @returns {JSX.Element}
 * @constructor
 */
function MediaDownload() {

    const [ stateValue, dispatch ] = useStateValue();

    return (
        <>
            <MainHeader/>
            { stateValue.generalData.isLoading ? <Loader/> :
                <Content style={{
                    padding: '25px',
                    background: 'rgb(255 255 255 / 35%)',
                    borderRadius: '5px',
                    boxShadow: 'rgb(0 0 0 / 1%) 0px 0 20px',
                }}>
                    <Title level={3} style={{ margin:0 }}>Button For Download Media File </Title>
                    <Divider />
                    <Row gutter={16}>
                        <Col className="gutter-row" span={6}>
                            <Paragraph style={{ margin:0, fontSize:'16px' }} > Download By Id: </Paragraph>
                        </Col>
                        <Col className="gutter-row" span={18}>
                            <Paragraph copyable={{ text: "[tsmlt_download_button id='11393'/]" }} >  <Text type="secondary" code style={{ fontSize: '20px' }} > [tsmlt_download_button id='11393' /] </Text> </Paragraph>
                        </Col>
                    </Row>
                    <Divider />
                    <Row gutter={16}>
                        <Col className="gutter-row" span={6}>
                            <Paragraph style={{ margin:0, fontSize:'16px' }} > Download By URL: </Paragraph>
                        </Col>
                        <Col className="gutter-row" span={18}>
                            <Paragraph copyable={{ text: "[tsmlt_download_button url='http://examole.local/volleyball_1756704187918.webp' /]" }} >  <Text type="secondary" code style={{ fontSize: '20px' }} > [tsmlt_download_button url='http://examole.local/volleyball_1756704187918.webp' /]</Text> </Paragraph>
                        </Col>
                    </Row>
                </Content>
            }
        </>
    );
}

export default MediaDownload;