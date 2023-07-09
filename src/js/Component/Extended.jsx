import React, {useEffect} from 'react';

import {useStateValue} from '../Utils/StateProvider';

import {
    Button,
    Checkbox,
    Divider,
    Form,
    Input,
    Layout,
    Typography
} from 'antd';

import Loader from "../Utils/Loader";

import * as Types from "../Utils/actionType";

import {activateLicense, updateOptins} from "../Utils/Data";

const {Content} = Layout;

const {Title, Paragraph} = Typography;

function Extended() {

    const [stateValue, dispatch] = useStateValue();

    const handleActivateLicense = async () => {
        const response = await activateLicense( stateValue.extended );
        if( 200 === parseInt( response.status ) ){

        }
        console.log( 'handleUpdateOption' );
    }
    return (
        <Layout style={{position: 'relative'}}>
            { stateValue.extended.isLoading ? <Loader/> :
                <Content style={{
                    padding: '150px',
                    background: 'rgb(255 255 255 / 35%)',
                    borderRadius: '5px',
                    boxShadow: 'rgb(0 0 0 / 1%) 0px 0 20px',
                }}>
                    <Form
                        labelCol={{
                            span: 5,
                            offset: 0,
                            style: {
                                textAlign: 'left',
                            }
                        }}
                        wrapperCol={{span: 19}}
                        layout="horizontal"
                        style={{
                            maxWidth: 900,
                            padding: '15px',
                            height: '100%'
                        }}
                    >
                        <Title level={3} style={{ marginTop:0 }}> Add your licence code here </Title>
                        <Divider />
                        <Form.Item label={<Title level={4} style={{ margin:0, fontSize:'16px' }}> Licence key </Title>} >
                            <Input
                                type="primary"
                                size="large"
                                style={{
                                    marginBottom: '15px'
                                }}
                                onChange={
                                    (event) => dispatch({
                                        type: Types.UPDATE_EXTENSION,
                                        extended: {
                                            ...stateValue.extended,
                                            extendedKey: event.target.value,
                                        }
                                    })
                                }
                                value={stateValue.extended.extendedKey}
                            />
                            { stateValue.extended.isReadyToValidate &&
                                <Button
                                    type="primary"
                                    size="large"
                                    onClick={ handleActivateLicense }
                                    danger={stateValue.extended.isValidate}
                                >
                                { stateValue.extended.isValidate ? `Deactivate Licence` : `Active Licence` }
                                </Button>
                            }

                        </Form.Item>

                    </Form>
                </Content>
            }
            <Button
                type="primary"
                size="large"
                style={{
                    position: 'fixed',
                    bottom: '100px',
                    right: '100px'
                }}
                onClick={() => dispatch({
                    ...stateValue,
                    type: Types.UPDATE_EXTENSION,
                    saveType: Types.UPDATE_EXTENSION,
                })}>
                Save Data
            </Button>

        </Layout>

    );
};

export default Extended;