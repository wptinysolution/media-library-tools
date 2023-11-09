import React, { useEffect, useState } from "react";
import { Divider, Progress, Layout, Typography } from 'antd';
import { useStateValue } from "../../Utils/StateProvider";
import * as Types from "../../Utils/actionType";
import {getAttachmentPageByPage, importOneByOne} from "../../Utils/Data";

const { Title, Text } = Typography;
const { Content } = Layout;

function ImportInfo() {

    const [stateValue, dispatch] = useStateValue();

    const [percent, setPercent] = useState(0);

    const [fileUrl, setFileUrl] = useState('');

    const [uploadedFile, setUploadedFile] = useState('');

    const totalMedia = stateValue.exportImport.totalPage;
    /**
     *
     * @param files
     * @returns {Promise<{mediaFiles: *[], percent: number}|*>}
     */
    const uploadMediaRecursively = async ( mediaFiles ) => {

        const countPercent = Math.floor(100 * ( totalMedia - mediaFiles?.length ) / totalMedia );

        await setPercent( ( prevState ) => countPercent );

        if ( mediaFiles?.length <= 0) {
            // Base case: All recursion is completed
            return;
        }

        const firstObject = mediaFiles.shift();
        if( firstObject['url']?.length  ){
            await setFileUrl( ( prevState ) => firstObject['url'] );
            const importedItem = await importOneByOne( { media : firstObject } );
            await setUploadedFile( ( prevState ) => importedItem );
        }
        // Continue the recursion with the updated mediaFiles
        await uploadMediaRecursively( mediaFiles );
    };

    useEffect( () => {
         uploadMediaRecursively( stateValue.exportImport.mediaFiles );
    }, [] );

    return (
        <Content style={{
            maxWidth: '1500px',
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
            {
                fileUrl && <Text type="secondary" >
                    Executing file import : { fileUrl }
                </Text>
            }
            <br/>
            {
                uploadedFile.id && <Text type="success" >
                    Uploaded file: { console.log( uploadedFile ) }
                </Text>
            }

        </Content>
    )
}

export default ImportInfo;
