import React, { useEffect, useState } from "react";

import {Divider, Progress, Layout, Typography, List, Avatar} from 'antd';

import { useStateValue } from "../../Utils/StateProvider";

import {importOneByOne } from "../../Utils/Data";

const { Title, Text } = Typography;

const { Content } = Layout;

function ImportInfo() {

    const [stateValue, dispatch] = useStateValue();

    const [percent, setPercent] = useState(0);

    const [uploadedFile, setUploadedFile] = useState([] );

    const totalMedia = stateValue.exportImport.totalPage;

    // Function to extract the file name from the URL
   const getFileNameFromURL = (url) => {
        // Use the URL constructor to parse the URL
       const urlObject = new URL(url);
       // Access the pathname and split it by '/' to get the file name
       const pathnameParts = urlObject.pathname.split('/');
       return pathnameParts[pathnameParts.length - 1];
    }

    /**
     *
     * @returns {Promise<{mediaFiles: *[], percent: number}|*>}
     * @param mediaFiles
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
           const importedItem = await importOneByOne( { media : firstObject } );
           await setUploadedFile( ( prevState ) => [
               ...prevState,
               importedItem.data
           ] );
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

            { uploadedFile.length ?
                <div
                    id="scrollableDiv"
                    style={{
                        height: 400,
                        overflow: 'auto',
                        padding: '0 16px',
                        border: '1px solid rgba(140, 140, 140, 0.35)',
                    }}
                >
                    <List
                        dataSource={uploadedFile}
                        renderItem={(item) => (
                            <List.Item key={item.id}>
                                <List.Item.Meta
                                    avatar={<Avatar src={item.url} />}
                                    title={
                                        <a
                                            target={`_blank`}
                                            href={item.url}>
                                            <Text type={ 'uploaded' === item.status ? `success` : `danger` }>
                                            { getFileNameFromURL( item.url ) }
                                            </Text>
                                        </a>
                                    }
                                    description={ 'uploaded' === item.status ? `Successfully upload` : `Upload Failed` }
                                />
                            </List.Item>
                        )}
                    />
                </div> : ''
            }
        </Content>
    )
}

export default ImportInfo;
