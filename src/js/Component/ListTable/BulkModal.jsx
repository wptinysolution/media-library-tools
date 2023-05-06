import React, { useContext } from "react";

import { Divider, Input, Modal, Select, Layout, Typography } from 'antd';

import { TheAppContext, TheMediaTableContext } from '../../Utils/TheContext';
const {  Content } = Layout;

const { Title } = Typography;

const { TextArea } = Input;

function BulkModal() {
    const { termsList } = useContext( TheAppContext );
    const {
        bulkSubmitdata,
        isBulkModalOpen,
        handleBulkModalOk,
        balkModalDataChange,
        handleBulkModalCancel,
        setbulkSubmitdata
    } = useContext( TheMediaTableContext );

    return (
        <Modal
            title={`Bulk Edit`}
            open={isBulkModalOpen}
            onOk={handleBulkModalOk}
            onCancel={handleBulkModalCancel}
        >
            <Divider />
            <Content>
                <Title style={{marginTop:'0px'}} level={5}> Title </Title>
                <TextArea
                    onChange={ balkModalDataChange }
                    name={`post_title`}
                    value={bulkSubmitdata.data.post_title}
                    placeholder={`Title`}
                />
                <Title style={{marginTop:'10px'}} level={5}> Alt Text </Title>
                <TextArea
                    onChange={balkModalDataChange}
                    name={`alt_text`}
                    value={bulkSubmitdata.data.alt_text}
                    placeholder={`Alt text`}
                />
                <Title style={{marginTop:'10px'}} level={5}> Caption </Title>
                <TextArea
                    onChange={balkModalDataChange}
                    name={`caption`}
                    value={bulkSubmitdata.data.caption}
                    placeholder={`Caption`}
                />
                <Title style={{marginTop:'10px'}} level={5}> Description </Title>
                <TextArea
                    onChange={balkModalDataChange}
                    name={`post_description`}
                    value={bulkSubmitdata.data.post_description}
                    placeholder={`Description`}
                />
                <Title style={{marginTop:'10px'}} level={5}> Categories </Title>
                <Select
                    onChange={ (value) => setbulkSubmitdata({
                        ...bulkSubmitdata,
                        'post_categories': value
                    }) }
                    allowClear = {true}
                    placeholder={'Categories'}
                    size="large"
                    mode="multiple"
                    style={{
                        width: '100%',
                    }}
                    showArrow
                    options={termsList}
                />

            </Content>
            <Divider />
        </Modal>
    )
}
export default BulkModal;