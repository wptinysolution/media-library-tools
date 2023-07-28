import React from "react";

import {Typography, Layout, Button, Space, Select} from 'antd';

import {bulkOprions, defaultBulkSubmitData, headerStyle, selectStyle} from "../../Utils/UtilData";

import { useStateValue } from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

const { Header } = Layout;

const { Title } = Typography;

function RabbisHeader() {

    const [ stateValue, dispatch ] = useStateValue();

    const handleDirModal = () => {
        dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                isDirModalOpen: true,
                scanDir: null
            },
        });
    };

    const handleChangeBulkType = (value) => {
        console.log( value )
    };

    const handleBulkSubmit = ( value ) => {
        console.log( value )
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

    return (
        <Header style={{...headerStyle, height: 'inherit'}}>
            <Space wrap>
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
                    onClick={handleDirModal}
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