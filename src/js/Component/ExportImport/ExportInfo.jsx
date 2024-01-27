import React, { useEffect, useState } from "react";
import { Divider, Progress, Layout, Typography } from 'antd';
import { useStateValue } from "../../Utils/StateProvider";
import * as Types from "../../Utils/actionType";
import { getAttachmentPageByPage } from "../../Utils/Data";

const { Title, Text } = Typography;
const { Content } = Layout;

function ExportInfo() {

    const [stateValue, dispatch] = useStateValue();

    const [percent, setPercent] = useState(0);

    const [totalPagesRemaining, seTotalPagesRemaining] = useState( stateValue.exportImport.totalPage );

    const [currentState, setCurrentState] = useState({
        totalPage : stateValue.exportImport.totalPage,
        exportedMediaFiles: [],
        pagesRemaining: stateValue.exportImport.totalPage,
        countPercent : 0
    } );

    const getMediaRecursively = async () => {
        const countPercent = Math.floor(100 * (stateValue.exportImport.totalPage - currentState.totalPage ) / stateValue.exportImport.totalPage);
        setPercent( ( prevState ) => countPercent );

        console.log( currentState );
        if ( totalPagesRemaining <= 0) {
            await setCurrentState(prevState => ({
                ...prevState,
                exportedMediaFiles : currentState.exportedMediaFiles,
                percent : countPercent
            }));
            return;
            // return {
            //     exportedMediaFiles : currentState.exportedMediaFiles,
            //     percent : countPercent
            // };
        }

        const pagesRemaining = totalPagesRemaining - 1;
        const paged = stateValue.exportImport.totalPage - pagesRemaining;

        const response = await getAttachmentPageByPage({ paged });
        //console.log(  currentState.exportedMediaFiles );
        // Create a new array by merging the response into mediaFiles.
        const exportedMediaFiles = [...currentState.exportedMediaFiles, ...response ];
        // Continue the recursion with the updated mediaFiles.

        const theState = {
            ...currentState,
            exportedMediaFiles,
            pagesRemaining,
            countPercent
        };
        setCurrentState(prevState => ({
            ...prevState,
            ...theState
        }));
        // await setMediaFiles( exportedMediaFiles );

        await localStorage.removeItem( "mlt_exported_history" );
        await localStorage.setItem( "mlt_exported_history", JSON.stringify(theState) );
        //console.log( theState )
        await new Promise(resolve => setTimeout( () => {

            seTotalPagesRemaining( pagesRemaining );
        }, 1000));

        //return await getMediaRecursively(totalPagesRemaining, updatedMediaFiles);
    };

   // console.log( currentState.totalPagesRemaining )
    // const getTheMediaRecursively = async () => {
    //     const finalMediaFiles = await getMediaRecursively();
    //     // Once the recursion is complete, update the state with the final mediaFiles
    //     await dispatch({
    //         type: Types.EXPORT_IMPORT,
    //         exportImport: {
    //             ...stateValue.exportImport,
    //             ...mediaFiles
    //         },
    //     });
    // };

    // useEffect(() => {
    //     getTheMediaRecursively();
    // }, []);

    useEffect(() => {
        getMediaRecursively();
    }, [ totalPagesRemaining ]);

    return (
        <Content style={{
            maxWidth: '700px',
            marginLeft: 'auto',
            marginRight: 'auto',
            width: '100%'
        }}>
            <Title level={3}>
                Export File to a CSV file
            </Title>
            <Text type="secondary" >
                This tool allows you to generate and download a CSV file containing a list of all media file.
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

export default ExportInfo;
