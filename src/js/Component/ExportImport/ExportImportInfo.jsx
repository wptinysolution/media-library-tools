import React from "react";

import { Divider, Progress, Layout, Typography} from 'antd';

const { Title, Text } = Typography;

const { Content } = Layout;

import {useStateValue} from "../../Utils/StateProvider";

function ExportImportInfo() {

    const [stateValue, dispatch] = useStateValue();

    const isExport = stateValue.exportImport.isExport && ! stateValue.exportImport.isImport;
    const isImport = stateValue.exportImport.isImport && ! stateValue.exportImport.isExport ;
    return (
            <Content style={ {
                maxWidth: '700px',
                marginLeft: 'auto',
                marginRight: 'auto'
            } }>
                <Title level={3}>
                    { isExport && 'Export File to a CSV file' }
                    { isImport && 'Import File from a CSV file' }
                </Title>
                <Text type="secondary" >
                    { isExport && 'This tool allows you to generate and download a CSV file containing a list of all media file.' }
                    { isImport && 'This tool allows you to import (or merge) Media data to your media library from a CSV.' }
                </Text>
                <Divider />
                <Progress
                    className={ `progressbar-height` }
                    style={ {
                        height: '30px'
                    } }
                    showInfo={true} percent={ stateValue.exportImport.percent }
                />
                <Divider />
            </Content>
    )
}
export default ExportImportInfo;