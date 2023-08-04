import React from "react";

import {Divider, Modal, Layout, Typography} from 'antd';

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

const { Content } = Layout;

const { Title, Paragraph  } = Typography;

function RubbishNotice() {

    const [stateValue, dispatch] = useStateValue();

    const handleNoticeModalHide = () => {
        dispatch({
            type: Types.RUBBISH_MEDIA,
            rubbishMedia: {
                ...stateValue.rubbishMedia,
                showRabbisNotice: false
            },
        });
    };


    return (
        <Modal
            style={{
                maxWidth: "950px"
            }}
            width="100%"
            height="500px"
            title={`Notice`}
            open={ stateValue.rubbishMedia.showRabbisNotice }
            onCancel={handleNoticeModalHide}
            onOk={handleNoticeModalHide}
        >
            <Divider />
            <Content style={{ height: "450px", position:'relative', 'overflowY': 'auto', padding:'0 15px' }} >
                <Title level={5} style={{ margin:'0 0 15px 0', fontSize: '20px', color:'red'}}>
                    Important Notice: Prioritize data safety. Always back up files before deletion to avoid irreversible loss.
                </Title>
                <Divider />
                <Title level={5} style={{ margin:'0 0 15px 0', fontSize: '20px', color:'green'}}> What is Rubbish File? </Title>
                <Paragraph type="secondary" style={{ fontSize: '18px', color:'#333'}}>
                    "Rubbish File" is a file that physically exists within a directory but is excluded from being indexed or included in the media library or database of an application or system.
                </Paragraph >
                <Divider />
                <Title level={5} style={{ margin:'0 0 15px 0', fontSize: '20px', color:'green'}}> Delete File Can be restore ? </Title>
                <Paragraph type="secondary" style={{ fontSize: '18px', color:'#333'}}>
                   No. You can't get back that file. <span> That's Why Before deleting any file search this file in you media library. And Re-check the url.</span>
                </Paragraph >
                <Paragraph type="secondary" style={{ fontSize: '18px', color:'#333'}}>
                    Thank you for your cooperation and understanding.
                </Paragraph >

            </Content>
            <Divider />
        </Modal>
    )
}
export default RubbishNotice;