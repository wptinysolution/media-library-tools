import React, { useEffect, useState } from "react";
import { Divider, Progress, Layout, Typography } from 'antd';
import { useStateValue } from "../../Utils/StateProvider";
import * as Types from "../../Utils/actionType";
//import { getAttachmentPageByPage } from "../../Utils/Data";

const { Title, Text } = Typography;
const { Content } = Layout;

function ExportInfo() {

    const [stateValue, dispatch] = useStateValue();

    const [percent, setPercent] = useState(stateValue.exportImport.percent );

    const [totalPagesRemaining, seTotalPagesRemaining] = useState( stateValue.exportImport.totalPage );

    const [currentState, setCurrentState] = useState({} );

    const getMediaRecursively = async () => {
        // const countPercent = Math.floor(100 * (stateValue.exportImport.totalPage - currentState.pagesRemaining ) / stateValue.exportImport.totalPage);
        // setPercent( ( prevState ) => countPercent );
        //
        // if ( totalPagesRemaining <= 0 ) {
        //     const theLastState = {
        //         ...currentState,
        //         exportedMediaFiles : currentState.exportedMediaFiles,
        //         countPercent : countPercent
        //     };
        //     await setCurrentState( theLastState );
        //     await dispatch({
        //         type: Types.EXPORT_IMPORT,
        //         exportImport: {
        //             ...stateValue.exportImport,
        //             mediaFiles : currentState.exportedMediaFiles,
        //             percent: countPercent
        //         },
        //     });
        //     // console.log( theLastState );
        //     // await setMediaFiles( exportedMediaFiles );
        //     await localStorage.removeItem( "mlt_exported_history" );
        //     await localStorage.setItem( "mlt_exported_history", JSON.stringify(theLastState) );
        //     return;
        // }
        //
        // const pagesRemaining = totalPagesRemaining - 1;
        // const paged = stateValue.exportImport.totalPage - pagesRemaining;
        //
        // const response = await getAttachmentPageByPage({ paged });
        // //console.log(  currentState.exportedMediaFiles );
        // // Create a new array by merging the response into mediaFiles.
        // const exportedMediaFiles = [...currentState.exportedMediaFiles, ...response ];
        // // Continue the recursion with the updated mediaFiles.
        //
        // const theState = {
        //     ...currentState,
        //     exportedMediaFiles,
        //     pagesRemaining,
        //     countPercent
        // };
        // setCurrentState( theState );

        // await setMediaFiles( exportedMediaFiles );
        // await localStorage.removeItem( "mlt_exported_history" );
        // await localStorage.removeItem( "mlt_import_remaining_history" );
        // await localStorage.setItem( "mlt_exported_history", JSON.stringify(theState) );

        // console.log( theState );
        //  setTimeout( () => {
        //     seTotalPagesRemaining( pagesRemaining );
        // }, 1000);
    };

    // const setDefaultState = (e) => {
    //     const export_remaining = localStorage.getItem( "mlt_exported_history");
    //     const remaining = export_remaining ? JSON.parse( export_remaining ) : {};
    //     if( 100 > remaining.countPercent ) {
    //         setCurrentState(prevState => ({
    //             ...prevState,
    //             totalPage: stateValue.exportImport.totalPage,
    //             exportedMediaFiles: stateValue.exportImport.mediaFiles,
    //             pagesRemaining: remaining?.pagesRemaining ? remaining.pagesRemaining : 0,
    //             countPercent: remaining?.countPercent ? remaining.countPercent : 0
    //         }))
    //     }
    // };

    // useEffect(() => {
    //     setDefaultState();
    // }, []);

    // useEffect(() => {
    //    getMediaRecursively();
    // }, [ totalPagesRemaining ]);

   // console.log( stateValue.exportImport );

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

//export default ExportInfo;
