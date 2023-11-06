import React, { useEffect, useState } from "react";
import { Divider, Progress, Layout, Typography } from 'antd';
import { useStateValue } from "../../Utils/StateProvider";
import * as Types from "../../Utils/actionType";
import { getAttachmentPageByPage } from "../../Utils/Data";

const { Title, Text } = Typography;
const { Content } = Layout;

function ImportInfo() {

    const [stateValue, dispatch] = useStateValue();

    const [percent, setPercent] = useState(0);

    const [mediaFiles, setMediaFiles] = useState([]);

    const uploadMediaRecursively = async ( files ) => {
        const countPercent = Math.floor(100 * (stateValue.exportImport.totalPage - totalPage) / stateValue.exportImport.totalPage);
        setPercent( ( prevState ) => countPercent );

        if (totalPage <= 0) {
            // Base case: All recursion is completed
            return {
                mediaFiles,
                percent : countPercent
            };
        }

        const totalPagesRemaining = totalPage - 1;
        const paged = stateValue.exportImport.totalPage - totalPagesRemaining;

        // const response = await getAttachmentPageByPage({ paged });

        // console.log( Object.values(response) );
        // Create a new array by merging the response into mediaFiles
        const updatedMediaFiles = [...mediaFiles, ...response ];

        // Continue the recursion with the updated mediaFiles
        return uploadMediaRecursively(totalPagesRemaining, updatedMediaFiles);
    };

    const uploadTheMediaRecursively = async () => {
        const finalMediaFiles = await uploadMediaRecursively( fileList );
        // Once the recursion is complete, update the state with the final mediaFiles
        await dispatch({
            type: Types.EXPORT_IMPORT,
            exportImport: {
                ...stateValue.exportImport,
                ...finalMediaFiles
            },
        });
    };

    useEffect(() => {
       // uploadTheMediaRecursively();
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
                Import media from CSV file
            </Title>
            <Text type="secondary" >
               This tool allows you to import (or merge) Media data to your media library from a CSV.
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

export default ImportInfo;
