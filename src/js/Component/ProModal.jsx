import React from "react";

import { Divider, Button, Modal, List, Layout, Typography } from 'antd';

import {
    CheckSquareOutlined
} from '@ant-design/icons';

import {useStateValue} from "../Utils/StateProvider";

import * as Types from "../Utils/actionType";

const {  Content } = Layout;

const { Title, Paragraph  } = Typography;

function ProModal() {

    const [stateValue, dispatch] = useStateValue();

    const handleBulkModalCancel = () => {
        dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                openProModal: false,
            },
        })
    }

    const data = [
        {
            title: 'All Free Features',
            desc: 'All features available in the free version are included.',
        },
        {
            title: 'Bulk Renaming File Based on Associated Post Title',
            desc: 'Automatic renaming of media files bulk mode.',
        },
        {
            title: 'Renaming File Prior to Uploading Based on Attached Posts Title',
            desc: 'Automatic renaming of media files prior to uploading based on attached posts.',
        },
        {
            title: 'Auto Rename Based on Custom Name',
            desc: 'Implement automatic renaming of media files based on custom text.',
        },
        {
            title: 'Bulk Add Alt Text, Caption, and Description Based on Associated Post Title',
            desc: 'Add Alt Text, Caption, and Description Based on Associated Post Title Bulk mode.',
        },
        {
            title: 'Assign Post Title to Alt Text, Caption, and Description',
            desc: 'Assign Upon Image Upload.',
        },
        {
            title: 'Bulk Delete Unnecessary / Rubbish File',
            desc: 'Easily mass delete unnecessary files, optimizing storage space and simplifying clutter management with bulk deletion.',
        },
        {
            title: 'Delete Unnecessary / Rubbish File Single Items',
            desc: 'Efficiently remove unneeded files individually, streamlining your storage and organization.',
        },
        {
            title: 'Ignore Important File. Take file safe mode never delete',
            desc: 'Stay worry-free while decluttering with Bulk mode, ensuring vital files are untouched. Safely ignore and remove unnecessary files with ease',
        }
    ];

    return (
        <Modal
            style={{
                maxWidth: "650px"
            }}
            width="100%"
            title={ <Title level={5} style={{ margin:'0', fontSize: '18px', color:'#ff0000'}}> You have to buy pro version for this features. </Title> }
            open={ stateValue.generalData.openProModal }
            onCancel={handleBulkModalCancel}
            footer={[
                <Button key="rescan" onClick={ handleBulkModalCancel }> Cancel </Button>,
                <Button key="prourl" type="primary">
                    <a className={'ant-btn'} target={`_blank`} href={tsmltParams.proLink}>Get Pro Version</a>
                </Button>
            ]}
        >
            <Content style={{ height: "550px", position:'relative', 'overflowY': 'auto' }}>
                <Paragraph type="secondary" style={{ fontSize: '13px', color:'#333'}}>
                    Pro Feature offers a range of enhanced functionalities and benefits...
                </Paragraph >
                <Divider style={{  margin: '5px 0' }}/>
                <List
                    itemLayout="horizontal"
                    dataSource={data}
                    renderItem={(item, index) => (
                        <List.Item key={index} style={{ padding: '5px 0' }} >
                            <List.Item.Meta
                                avatar={<CheckSquareOutlined style={{ fontSize: '40px', color: '#1677ff' }} />}
                                title={<span style={{ color:'#1677ff', fontSize: '15px' }}> { item.title } </span>}
                                description={<span style={{ color:'#333' }}> { item.desc } </span>}
                            />
                        </List.Item>
                    )}
                />
                <Paragraph type="secondary" style={{ fontSize: '14px', color:'#ff0000'}}>
                    Support our development efforts for the WordPress community by purchasing the Pro version, enabling us to create more innovative products.
                </Paragraph >
            </Content>
            <Divider style={{  margin: '10px 0' }}/>
        </Modal>
    )
}
export default ProModal;