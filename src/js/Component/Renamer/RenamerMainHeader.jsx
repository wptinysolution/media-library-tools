import React, { useRef} from "react";

import { Typography, Layout, Button, Space, Input } from 'antd';

import { headerStyle } from "../../Utils/UtilData";

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

    return (
        <Header style={{...headerStyle, height: 'inherit'}}>

            <Space wrap>
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
                }}> Renamer note: Please take a Backup First before edit "File Name"</Title>
            </Space>
        </Header>
    );
}

export default RenamerMainHeader;