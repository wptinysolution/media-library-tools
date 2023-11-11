import React from "react";

import {Divider, Modal, Layout, Typography, Checkbox } from 'antd';

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

import { localStoreData, localRetrieveData } from "../../Utils/UtilData";

const { Content } = Layout;

const { Title, Paragraph  } = Typography;

function RubbishNotice() {

    const [stateValue, dispatch] = useStateValue();

    const handleNoticeModalHide = () => {
        dispatch({
            type: Types.RUBBISH_MEDIA,
            rubbishMedia: {
                ...stateValue.rubbishMedia,
                showRubbishNotice: false
            },
        });
    };


    return (
        <Modal
            style={{
                maxWidth: "950px"
            }}
            width="100%"
            height="600px"
            title={`Notice`}
            open={ stateValue.rubbishMedia.showRubbishNotice }
            onCancel={handleNoticeModalHide}
            footer={ null }
        >
            <Divider />
            <Content style={{ height: "500px", position:'relative', 'overflowY': 'auto', padding:'0' }} >
                <Title level={5} style={{ margin:'0 0 15px 0', fontSize: '15px', color:'red'}}>
                    Important Notice: Prioritize data safety. Always back up files before deletion to avoid irreversible loss.
                </Title>
                <Divider style={{  margin: '10px 0'  }}/>
                <Title level={5} style={{ margin:'0 0 15px 0', fontSize: '15px', color:'green'}}> What is Rubbish File? </Title>
                <Paragraph type="secondary" style={{ fontSize: '14px', color:'#333'}}>
                    "Rubbish File" is a file that physically exists within a directory but is excluded from being indexed or included in the media library or database of an application or system.
                </Paragraph >
                <Divider style={{  margin: '10px 0'  }}/>
                <Title level={5} style={{ margin:'0 0 15px 0', fontSize: '15px', color:'green'}}> Why Need Delete Rubbish File? </Title>
                <Paragraph type="secondary" style={{ fontSize: '14px', color:'#333'}}>
                    <strong>Freeing up storage space</strong>: Delete rubbish files to create more available storage space on your device.
                </Paragraph >
                <Paragraph type="secondary" style={{ fontSize: '14px', color:'#333'}}>
                    <strong>Improving performance</strong>: Removing unnecessary files can lead to a faster and more efficient system.
                </Paragraph >
                <Paragraph type="secondary" style={{ fontSize: '14px', color:'#333'}}>
                    <strong>Enhancing security and organization</strong> : Deleting rubbish files can help keep your data secure and your file system organized.
                </Paragraph >
                <Divider style={{  margin: '10px 0' }}/>
                <Title level={5} style={{ margin:'0 0 15px 0', fontSize: '15px', color:'green'}}> Delete File Can be Restore ? </Title>
                <Paragraph type="secondary" style={{ fontSize: '14px', color:'#333'}}>
                   No. You can't get back that file.
                    <span> That's Why Before deleting any file search this file in you media library
                    And Re-check the url and be sure before deleting.</span>
                </Paragraph >
                <Paragraph type="secondary" style={{ fontSize: '14px', color:'#333'}}>
                    Thank you for your cooperation and understanding.
                </Paragraph >
                <Divider style={{  margin: '10px 0'  }}/>
                <Paragraph type="secondary" style={{ fontSize: '14px', color:'#333'}}>
                    <Checkbox onChange={ ( event ) => event.target.checked ? localStoreData("showRubbishNotice", 'disable') : localStorage.removeItem("showRubbishNotice") }> <span style={{color:'green'}}> Hide Notice For Today </span> </Checkbox>
                </Paragraph >

            </Content>
        </Modal>
    )
}
export default RubbishNotice;