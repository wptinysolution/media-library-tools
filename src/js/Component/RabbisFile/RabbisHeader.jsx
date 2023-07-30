import React, {useEffect, useState} from "react";

import {Typography, Layout, Button, Space, Select} from 'antd';

import {bulkOprions, defaultBulkSubmitData, headerStyle, selectStyle} from "../../Utils/UtilData";

import { useStateValue } from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";
import {getDirList} from "../../Utils/Data";

const { Header } = Layout;

const { Title } = Typography;

function RabbisHeader() {

    const [ stateValue, dispatch ] = useStateValue();

    const handleDirForModal = async () => {
        if( ! stateValue.generalData.isDirModalOpen ){
            return;
        }
        const responseDate = await getDirList();
        const preparedDate =  await JSON.parse( responseDate.data );
        await dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                scanRabbisDirList: preparedDate,
                scanRabbisDirLoading: false,
            },
        });
        console.log( 'getDirList' )
    };

    const openDirModal = () => {
        dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                isDirModalOpen: true
            },
        });
    };

    const handleChangeBulkType = (value) => {

    };

    const handleBulkSubmit = ( value ) => {
        switch( stateValue.bulkSubmitData.type ){
            case 'delete':
                break;
            case 'ignore':
                break;
            default:
        }

    };

    const options = [
        { value: 'delete', label: 'Delete' },
        { value: 'ignore', label: 'Ignore' },
        { value: 'restore', label: 'Restore' },
    ];

    useEffect(() => {
        handleDirForModal();
    }, [ stateValue.generalData.isDirModalOpen ] );

    return (
        <Header style={{...headerStyle, height: 'inherit'}}>
            <Space>
                <Select
                    style={{
                        width: '150px'
                    }}
                    size="large"
                    defaultValue={`ignore`}
                    onChange={handleChangeBulkType}
                    options={ options }
                />
                <Button
                    type="primary"
                    size="large"
                    onClick={handleBulkSubmit}
                > Bulk Apply </Button>
                <Button
                    style={{
                        width: '200px'
                    }}
                    type="primary"
                    size="large"
                    onClick={openDirModal}
                    ghost={ ! stateValue.generalData.isDirModalOpen }>
                    { `Directory List` }
                </Button>
                <Title level={5} style={{
                    margin:'0 15px',
                    color: 'red'
                }}> Rabbis File Note : A "Rabbis File" refers to a file that exists within a directory but is not included in the media library or database. </Title>
            </Space>
        </Header>
    );
}

export default RabbisHeader;