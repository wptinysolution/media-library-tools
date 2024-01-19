import React, { useRef, useEffect } from "react";

import {Typography, Layout, Button, Space, Input, Select} from 'antd';

const { Title } = Typography;

import { defaultBulkSubmitData, headerStyle, selectStyle} from "../../Utils/UtilData";

import { useStateValue } from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

import BulkRanameModal from "./BulkRanameModal";

import {SearchOutlined} from "@ant-design/icons";

import {useSearchDebounce} from "../../Utils/Hooks";
import {notifications} from "../../Utils/Data";

const { Header } = Layout;

function RenamerMainHeader() {

    const [ stateValue, dispatch ] = useStateValue();

    const [search, setSearch] = useSearchDebounce();

    const inputRef = useRef(null);

    const sharedProps = {
        ref: inputRef,
    };

    const handleChangeBulkType = (value) => {
        const data = 'bulkRename' === value ? stateValue.bulkSubmitData.data : defaultBulkSubmitData.data;
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...stateValue.bulkSubmitData,
                type: value,
                data,
            },
        });
    };

    const upDateQuery = async () => {
        if( stateValue.mediaData.postQuery.searchKeyWords === search ){
            return;
        }
        await dispatch({
            type: Types.GET_MEDIA_LIST,
            mediaData: {
                ...stateValue.mediaData,
                postQuery : {
                    ...stateValue.mediaData.postQuery,
                    searchKeyWords : search
                }
            },
        });
        console.log( search )
    };

    useEffect(() => {
        upDateQuery();
    }, [ search ]);

    const handleBulkSubmit = () => {

        if ( 'bulkRenameByPostTitle' == stateValue.bulkSubmitData.type && ! tsmltParams.hasExtended ){
            dispatch({
                type: Types.GENERAL_DATA,
                generalData: {
                    ...stateValue.generalData,
                    openProModal: true,
                },
            });
            return;
        }

        if( ! stateValue.bulkSubmitData.ids.length ){
            notifications( false, 'No checkboxes are checked. Please select at least one item.' );
            return;
        }

        switch( stateValue.bulkSubmitData.type ){
            case 'bulkRename':
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
            case 'bulkRenameByPostTitle':
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
                notifications( false, 'No Actions are selected. Please select one.' );
        }

    };

    const options = [
        { value: 'bulkRename', label: 'Bulk Rename' },
        { value: 'bulkRenameByPostTitle', label: 'Bulk Rename Based on Post Title' },
    ];
    return (
        <Header style={{...headerStyle, height: 'inherit'}}>
            <Title level={5} style={{
                border: '1px solid #f0f0f0',
                padding: '10px 15px',
                margin: '0 0 10px 0px',
                fontSize:'13px',
                color: 'red',
                textAlign: 'center'
            }}> Renamer Note: Before making any changes to the "File Name," it is highly recommended to take a backup. Renaming the file will also modify file URL. If you have hardcoded the file URL anywhere, please ensure to update it with the new URL after renaming. Item Per page maximum allowed 1500 for ignoring server capacity issue.</Title>

            <Space >
                <Select
                    allowClear={true}
                    size="large"
                    placeholder={'Bulk Apply'}
                    style={{ ...selectStyle, width: '250px' }}
                    onChange={handleChangeBulkType}
                    options={ options }
                />

                <Button
                    type="primary"
                    size="large"
                    onClick={handleBulkSubmit}
                > Bulk Apply </Button>
                <Input
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                    }}
                    type="primary"
                    size="large"
                    addonAfter={<SearchOutlined />}
                    placeholder="Keywords..."
                    onChange={(e) => setSearch(e.target.value)}
                />
                <Button
                    style={{
                        width: '180px',
                        borderColor: '#d9d9d9'
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
                    Items Per page (Max-1500)
                </Button>
                <Input
                    {...sharedProps}
                    type="primary"
                    size="large"
                    style={{ width: '80px' }}
                    onBlur={ () => dispatch({
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

            </Space>
            <BulkRanameModal />
        </Header>
    );
}

export default RenamerMainHeader;