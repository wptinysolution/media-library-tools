import React, { useEffect, useState } from "react";
import { Divider, Progress, Layout, Typography } from 'antd';
import { useStateValue } from "../../Utils/StateProvider";
import * as Types from "../../Utils/actionType";
import { getAttachmentPageByPage } from "../../Utils/Data";

const { Title, Text } = Typography;
const { Content } = Layout;

function ExportImportInfo() {
    const [stateValue, dispatch] = useStateValue();
    const [percent, setPercent] = useState(0);
    const [mediaFiles, setMediaFiles] = useState([]);

    const isExport = stateValue.exportImport.isExport && !stateValue.exportImport.isImport;
    const isImport = stateValue.exportImport.isImport && !stateValue.exportImport.isExport;

    const getMediaRecursively = async (totalPage, mediaFiles) => {
        const countPercent = Math.floor(100 * (stateValue.exportImport.totalPage - totalPage) / stateValue.exportImport.totalPage);
        setPercent( ( prevState ) => countPercent );

        if (totalPage <= 0) {
            // await dispatch({
            //     type: Types.EXPORT_IMPORT,
            //     exportImport: {
            //         ...stateValue.exportImport,
            //         percent: countPercent, // Use the updated mediaFiles value
            //     },
            // });
            // Base case: All recursion is completed
            return {
                mediaFiles,
                percent : countPercent
            };
        }

        const totalPagesRemaining = totalPage - 1;
        const paged = stateValue.exportImport.totalPage - totalPagesRemaining;

        const response = await getAttachmentPageByPage({ paged });

        // console.log( Object.values(response) );
        // Create a new array by merging the response into mediaFiles
        const updatedMediaFiles = [...mediaFiles, ...response ];

        // Continue the recursion with the updated mediaFiles
        return getMediaRecursively(totalPagesRemaining, updatedMediaFiles);
    };

    const getTheMediaRecursively = async () => {
        const finalMediaFiles = await getMediaRecursively(stateValue.exportImport.totalPage, mediaFiles);
        // Once the recursion is complete, update the state with the final mediaFiles
        // setMediaFiles(finalMediaFiles);
        await dispatch({
            type: Types.EXPORT_IMPORT,
            exportImport: {
                ...stateValue.exportImport,
                ...finalMediaFiles
            },
        });
    };

    useEffect(() => {
        getTheMediaRecursively();
    }, []);

    console.log( stateValue.exportImport );

    return (
        <Content style={{
            maxWidth: '700px',
            marginLeft: 'auto',
            marginRight: 'auto',
            width: '100%'
        }}>
            <Title level={3}>
                {isExport && 'Export File to a CSV file'}
                {isImport && 'Import File from a CSV file'}
            </Title>
            <Text type="secondary" >
                {isExport && 'This tool allows you to generate and download a CSV file containing a list of all media file.'}
                {isImport && 'This tool allows you to import (or merge) Media data to your media library from a CSV.'}
            </Text>
            <Divider />
            <Progress
                className={`progressbar-height`}
                style={{
                    height: '30px'
                }}
                showInfo={true} percent={percent}
            />
            <Divider />
        </Content>
    )
}

export default ExportImportInfo;
