import React, { useRef} from "react";

import {Typography, Layout, Button, Space, Input, Select} from 'antd';

import {bulkOprions, defaultBulkSubmitData, headerStyle, selectStyle} from "../../Utils/UtilData";

import { useStateValue } from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

const { Header } = Layout;

const { Title } = Typography;

function RenamerMainHeader() {

    const [ stateValue, dispatch ] = useStateValue();

    const inputRef = useRef(null);

    const sharedProps = {
        ref: inputRef,
    };

    const handleChangeBulkType = (value) => {
        const data = 'bulkrename' === value ? stateValue.bulkSubmitData.data : defaultBulkSubmitData.data;
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...stateValue.bulkSubmitData,
                type: value,
                data,
            },
        });
    };

    const handleBulkSubmit = () => {
        if ( ! tsmltParams.hasExtended ){
            dispatch({
                type: Types.GENERAL_DATA,
                generalData: {
                    ...stateValue.generalData,
                    openProModal: true,
                },
            });
            return;
        }
        switch( stateValue.bulkSubmitData.type ){
            case 'bulkrename':
                dispatch({
                    ...stateValue,
                    type: Types.BULK_SUBMIT,
                    saveType: Types.BULK_SUBMIT,
                });
                break;
            case 'bulkrenameModal':
                dispatch({
                    ...stateValue,
                    type: Types.BULK_SUBMIT,
                    saveType: null,
                    bulkSubmitData: {
                        ...stateValue.bulkSubmitData,
                        isModalOpen : true,
                    },
                });
                break;
            default:
        }

    };

    const options = [
        { value: 'default', label: 'Bulk Action' },
        { value: 'rename', label: 'Bulk Rename' },
    ];

    return (
        <Header style={{...headerStyle, height: 'inherit'}}>

            <Space >
                <Select
                    size="large"
                    defaultValue={`default`}
                    style={selectStyle}
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
                        width: '180px'
                    }}
                    type="primary"
                    size="large"
                    onClick={
                        () => dispatch({
                            type: Types.UPDATE_RENAMER_MEDIA,
                            rename:{
                                ...stateValue.rename,
                                formEdited : ! stateValue.rename.formEdited,
                            }
                        })
                    }
                    ghost={ ! stateValue.rename.formEdited }>  { stateValue.rename.formEdited ? 'Disable Edit Mode' : 'Enable Edit Mode' }
                </Button>
                <Button
                    type="text"
                    size="large"
                    onClick={
                        () => {
                            inputRef.current.focus({
                                cursor: 'start',
                            });
                        }
                    }
                >
                    Items Per page
                </Button>
                <Input
                    {...sharedProps}
                    type="primary"
                    size="large"
                    style={{ width: '50px' }}
                    onBlur={ (event) => dispatch({
                            ...stateValue,
                            type: Types.UPDATE_OPTIONS,
                            saveType: Types.UPDATE_OPTIONS,
                        })
                    }
                    onChange={
                        (event) => dispatch({
                            ...stateValue,
                            type: Types.UPDATE_OPTIONS,
                            options : {
                                ...stateValue.options,
                                media_per_page: event.target.value,
                            }
                        })
                    }
                    value={stateValue.options.media_per_page}
                />
                <Title level={5} style={{
                    margin:'0 15px',
                    color: 'red'
                }}> Renamer Note: Before making any changes to the "File Name," it is highly recommended to take a backup. Renaming the file will also modify file URL. If you have hardcoded the file URL anywhere, please ensure to update it with the new URL after renaming. </Title>
            </Space>
        </Header>
    );
}

export default RenamerMainHeader;