import React, {useEffect, useState} from "react";

import { Divider, Progress, Layout, Typography} from 'antd';

const { Title, Text } = Typography;

const { Content } = Layout;

import {useStateValue} from "../../Utils/StateProvider";
import * as Types from "../../Utils/actionType";
import {getAttachmentPageByPage} from "../../Utils/Data";

function ExportImportInfo() {

    const [stateValue, dispatch] = useStateValue();

    const [percent, setPercent] = useState(0);

    const [mediaFiles, setMediaFiles] = useState([]);

    const isExport = stateValue.exportImport.isExport && ! stateValue.exportImport.isImport;

    const isImport = stateValue.exportImport.isImport && ! stateValue.exportImport.isExport ;

    const getMediaRecursively = async ( totalPage ) => {
        const countPercent = Math.floor( 100 * ( stateValue.exportImport.totalPage - totalPage ) / stateValue.exportImport.totalPage );
        //  setPercent( countPercent );
        setPercent( countPercent );

        if ( totalPage <= 0) {
            return await dispatch({
                type: Types.EXPORT_IMPORT,
                exportImport: {
                    ...stateValue.exportImport,
                    mediaFile: mediaFiles,
                    percent: countPercent
                },
            });
            // Base case: All renaming operations are completed
        }

        const totalPagesRemaining = totalPage - 1;
        const paged = stateValue.exportImport.totalPage - totalPagesRemaining;
        // Recur with the rest of the IDs in the list
        const response = await getAttachmentPageByPage( { paged } );

        setMediaFiles( {
            ...mediaFiles
        } );

        setTimeout(async () => {
             await getMediaRecursively( totalPagesRemaining );
        }, 500);

        await dispatch({
            type: Types.EXPORT_IMPORT,
            exportImport: {
                ...stateValue.exportImport,
                percent: countPercent,
            },
        });

        return response;
    }

    const getTheMediaRecursively = async () => {
        await getMediaRecursively( stateValue.exportImport.totalPage );
    };

    useEffect(() => {
        getTheMediaRecursively();
    }, [] );

    return (
            <Content style={ {
                maxWidth: '700px',
                marginLeft: 'auto',
                marginRight: 'auto',
                width: '100%'
            } }>
                { console.log( stateValue.exportImport ) }
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
                    showInfo={true} percent={ percent }
                />
                <Divider />
            </Content>
    )
}
export default ExportImportInfo;