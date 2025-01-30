import React from 'react';

import { useStateValue } from '../Utils/StateProvider';

import Loader from '../Utils/Loader';

import {
    Form,
    Input,
    Layout,
    Button,
    Divider,
    Checkbox,
    Typography
} from 'antd';

const { TextArea } = Input;

const { Title, Text } = Typography;

const { Content } = Layout;

import { columnList } from '../Utils/UtilData'

import * as Types from "../Utils/actionType";
import MainHeader from "./MainHeader";

const CheckboxGroup = Checkbox.Group;

const columns = columnList.map( ( currentValue) => {
    return {
        label: currentValue.title,
        value: currentValue.key
    }
} );

const plainOptions = columnList.map( ( currentValue) => {
    return currentValue.key;
} );

function Settings() {

    const [stateValue, dispatch] = useStateValue();

    const isCheckedDiff = Object.keys( plainOptions ).length === Object.keys( stateValue.options.media_table_column ).length;

    const onChangeColumnList = (list) => {
        dispatch({
            type: Types.UPDATE_OPTIONS,
            options : {
                ...stateValue.options,
                media_table_column: list,
            }
        });

    };

    const onCheckAllColumn = (e) => {
        dispatch({
            type: Types.UPDATE_OPTIONS,
            options : {
                ...stateValue.options,
                media_table_column: e.target.checked ? plainOptions : [],
            }
        });
    };

    const setDefaultText = (e) => {
        if ( ! tsmltParams.hasExtended ){
            const fields = [
                'enable_auto_rename' ,
                'alt_text_by_post_title',
                'auto_rename_by_post_title',
                'caption_text_by_post_title',
                'desc_text_by_post_title',
            ];
            if( fields.indexOf( e.target.name ) !== -1 ) {
                dispatch({
                    type: Types.GENERAL_DATA,
                    generalData: {
                        ...stateValue.generalData,
                        openProModal: true,
                    },
                });
                return;
            }
        }
        dispatch({
            type: Types.UPDATE_OPTIONS,
            options : {
                ...stateValue.options,
                [e.target.name]: stateValue.options[e.target.name] !== e.target.value ? e.target.value : '',
            }
        });

    }

    const onChangeOthersFileList = (list) => {
        dispatch({
            type: Types.UPDATE_OPTIONS,
            options : {
                ...stateValue.options,
                others_file_support: list,
            }
        });

    };

    return (<>
        <MainHeader/>
        <Layout className="layout" style={{ overflowY: 'auto' }} >
        <Layout style={{ position: 'relative' }}>
            <Form
                labelCol={{
                    span: 7,
                    offset: 0,
                    style:{
                        textAlign: 'left',
                    }
                }}
                wrapperCol={{ span: 19 }}
                layout="horizontal"
                style={{
                    height: '100%'
                }}
            >
                { stateValue.options.isLoading ? <Loader/> :
                    <Content style={{
                        padding: '25px',
                        background: 'rgb(255 255 255 / 35%)',
                        borderRadius: '5px',
                        boxShadow: 'rgb(0 0 0 / 1%) 0px 0 20px',
                    }}>
                        <Title level={3} style={{ margin:0 }}> Media Table Settings </Title>
                        <Divider />
                        <Form.Item label={<Title level={5} style={{ margin:0, fontSize:'14px' }}> Media Table Column </Title>} >
                            <Checkbox indeterminate={ ! isCheckedDiff } onChange={onCheckAllColumn} checked={isCheckedDiff}>Check all </Checkbox>
                            <Divider style={ { margin: '10px 0' } }/>
                            <CheckboxGroup options={columns} value={stateValue.options.media_table_column} onChange={onChangeColumnList} />
                        </Form.Item>
                        <Divider />
                        <Form.Item label={<Title level={5} style={{ margin:0, fontSize:'14px' }}> Others File Support </Title>} >
                            <CheckboxGroup options={
                                [
                                    {
                                        label: 'SVG',
                                        value: 'svg'
                                    }
                                ]
                            } value={stateValue.options.others_file_support} onChange={ onChangeOthersFileList } />
                            <br/>
                            <Text  type="secondary"  >
                                Svg And Others File Upload.
                            </Text>
                        </Form.Item>
                        <Divider />
                        <Form.Item label={<Title level={5} style={{ margin:0, fontSize:'14px' }}> Use Post Title as Alt Text </Title>} >
                            <Checkbox
                                onChange={setDefaultText}
                                name={`alt_text_by_post_title`}
                                value={`alt_text_by_post_title`}
                                checked={ 'alt_text_by_post_title' === stateValue.options.alt_text_by_post_title }>
                                Default Alt Text Base On Post Title { ! tsmltParams.hasExtended && <span style={ { color: '#ff0000', fontWeight: 'bold' } }> - PRO</span> }
                            </Checkbox>
                            <br/>
                            <br/>
                            <Text type="secondary" >
                                Alt Text will add automatically when upload Media as attached posts.
                            </Text>
                            <Divider style={ { margin: '10px 0' } }/>
                        </Form.Item>

                        <Form.Item label={<Title level={5} style={{ margin:0, fontSize:'14px' }}> Default Images Alt Text</Title>} >
                            <Checkbox
                                onChange={setDefaultText}
                                name={`default_alt_text`}
                                value={`image_name_to_alt`}
                                checked={ 'image_name_to_alt' === stateValue.options.default_alt_text }>
                                Image name use as alt text
                            </Checkbox>
                            <Checkbox
                                onChange={setDefaultText}
                                name={`default_alt_text`}
                                value={`custom_text_to_alt`}
                                checked={ 'custom_text_to_alt' === stateValue.options.default_alt_text } >
                                Custom text
                            </Checkbox>
                            { 'custom_text_to_alt' === stateValue.options.default_alt_text &&
                                <>
                                    <Divider style={ { margin: '10px 0' } }/>
                                    <TextArea
                                        type="primary"
                                        size="large"
                                        onChange={
                                            (event) => dispatch({
                                                type: Types.UPDATE_OPTIONS,
                                                options : {
                                                    ...stateValue.options,
                                                    media_default_alt: event.target.value,
                                                }
                                            })
                                        }
                                        value={stateValue.options.media_default_alt}
                                    />

                                </>
                            }
                            <br/>
                            <br/>
                            <Text  type="secondary">
                                Alt Text Will add automatically when upload Media file
                            </Text>

                        </Form.Item>
                        <Divider />
                        <Form.Item label={<Title level={5} style={{ margin:0, fontSize:'14px' }}> Use Post Title as Caption </Title>} >
                            <Checkbox
                                onChange={setDefaultText}
                                name={`caption_text_by_post_title`}
                                value={`caption_text_by_post_title`}
                                checked={ 'caption_text_by_post_title' === stateValue.options.caption_text_by_post_title }>
                                Default Caption Text Base On Post Title { ! tsmltParams.hasExtended && <span style={ { color: '#ff0000', fontWeight: 'bold' } }> - PRO</span> }
                            </Checkbox>
                            <br/>
                            <br/>
                            <Text type="secondary" >
                                Caption Text will add automatically when upload Media as attached posts.
                            </Text>
                            <Divider style={ { margin: '10px 0' } }/>
                        </Form.Item>
                        <Form.Item label={<Title level={5} style={{ margin:0, fontSize:'14px' }}>Default Caption Text </Title>} >
                            <Checkbox
                                onChange={setDefaultText}
                                name={`default_caption_text`}
                                value={`image_name_to_caption`}
                                checked={ 'image_name_to_caption' === stateValue.options.default_caption_text }>
                                Image name use as caption
                            </Checkbox>
                            <Checkbox
                                onChange={setDefaultText}
                                name={`default_caption_text`}
                                value={`custom_text_to_caption`}
                                checked={ 'custom_text_to_caption' === stateValue.options.default_caption_text } >
                                Custom text
                            </Checkbox>
                            { 'custom_text_to_caption' === stateValue.options.default_caption_text &&
                                <>
                                    <Divider style={ { margin: '10px 0' } }/>
                                    <TextArea
                                        type="primary"
                                        size="large"
                                        onChange={
                                            (event) => dispatch({
                                                type: Types.UPDATE_OPTIONS,
                                                options : {
                                                    ...stateValue.options,
                                                    media_default_caption: event.target.value,
                                                }
                                            })
                                        }
                                        value={stateValue.options.media_default_caption}
                                    />
                                </>
                            }
                            <br/>
                            <br/>
                            <Text  type="secondary" >
                                Caption text will add automatically when upload Media file
                            </Text>

                        </Form.Item>
                        <Divider />

                        <Form.Item label={<Title level={5} style={{ margin:0, fontSize:'14px' }}> Use Post Title as Description </Title>} >
                            <Checkbox
                                onChange={setDefaultText}
                                name={`desc_text_by_post_title`}
                                value={`desc_text_by_post_title`}
                                checked={ 'desc_text_by_post_title' === stateValue.options.desc_text_by_post_title }>
                                Default Description Text Base On Post Title { ! tsmltParams.hasExtended && <span style={ { color: '#ff0000', fontWeight: 'bold' } }> - PRO</span> }
                            </Checkbox>
                            <br/>
                            <br/>
                            <Text type="secondary" >
                                Description Text will add automatically when upload Media as attached posts.
                            </Text>
                            <Divider style={ { margin: '10px 0' } }/>
                        </Form.Item>

                        <Form.Item label={<Title level={5} style={{ margin:0, fontSize:'14px' }}> Default Description Text </Title>} >

                            <Checkbox
                                onChange={setDefaultText}
                                name={`default_desc_text`}
                                value={`image_name_to_desc`}
                                checked={ 'image_name_to_desc' === stateValue.options.default_desc_text }>
                                Image name use as description
                            </Checkbox>
                            <Checkbox
                                onChange={setDefaultText}
                                name={`default_desc_text`}
                                value={`custom_text_to_desc`}
                                checked={ 'custom_text_to_desc' === stateValue.options.default_desc_text } >
                                Custom text
                            </Checkbox>
                            { 'custom_text_to_desc' === stateValue.options.default_desc_text &&
                                <>
                                    <Divider style={ { margin: '10px 0' } }/>
                                    <TextArea
                                        type="primary"
                                        size="large"
                                        onChange={
                                            (event) => dispatch({
                                                type: Types.UPDATE_OPTIONS,
                                                options : {
                                                    ...stateValue.options,
                                                    media_default_desc: event.target.value,
                                                }
                                            })
                                        }
                                        value={stateValue.options.media_default_desc}
                                    />

                                </>
                            }
                            <br/>
                            <br/>
                            <Text  type="secondary"  >
                                Description text will add automatically when upload Media file
                            </Text>

                        </Form.Item>
                        <Divider />
                        <Title level={3} style={{ margin:0 }}> Media Renamer Settings </Title>
                        <Divider />
                        <Form.Item label={<Title level={5} style={{margin: 0, fontSize: '14px'}}> File Rename Prefix And
                            Suffix </Title>}>
                            <Title level={5} style={{ marginTop: 0, fontSize: '14px'}}> Rename
                                prefix {!tsmltParams.hasExtended &&
                                    <span style={{color: '#ff0000', fontWeight: 'bold'}}> - PRO</span>} </Title>
                            <Input
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    width: '300px'
                                }}
                                type="primary"
                                size="large"
                                placeholder="Prefix"
                                onChange={
                                    (event) => dispatch({
                                        type: Types.UPDATE_OPTIONS,
                                        options: {
                                            ...stateValue.options,
                                            media_rename_prefix: event.target.value,
                                        }
                                    })
                                }
                                value={stateValue.options.media_rename_prefix}
                            />
                            <br/>
                            <Text type="secondary">
                                A file rename prefix is a set of characters, words, or numbers added at the beginning of
                                a filename when renaming it. This helps in organizing files, improving SEO, or
                                maintaining a consistent naming convention.
                            </Text>

                            <Divider />
                            <Title level={5} style={{ fontSize:'14px' }}> Rename suffix { ! tsmltParams.hasExtended && <span style={ { color: '#ff0000', fontWeight: 'bold' } }> - PRO</span> } </Title>
                            <Input
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    width: '300px'
                                }}
                                type="primary"
                                size="large"
                                placeholder="Suffix"
                                onChange={
                                    (event) => dispatch({
                                        type: Types.UPDATE_OPTIONS,
                                        options: {
                                            ...stateValue.options,
                                            media_rename_suffix: event.target.value,
                                        }
                                    })
                                }
                                value={stateValue.options.media_rename_suffix}

                            />
                            <br/>
                            <Text type="secondary">
                                A file rename suffix is a set of characters, words, or numbers added at the end of a filename when renaming it. This helps differentiate files, improve SEO, or maintain a structured naming convention.
                            </Text>

                        </Form.Item>
                        <Divider />
                        <Form.Item label={<Title level={5} style={{ margin:0, fontSize:'14px' }}> Rename based on attached posts </Title>} >
                            <Checkbox
                                onChange={setDefaultText}
                                name={`auto_rename_by_post_title`}
                                value={`auto_rename_by_post_title`}
                                checked={ 'auto_rename_by_post_title' === stateValue.options.auto_rename_by_post_title } >
                                Auto Rename by post title { ! tsmltParams.hasExtended && <span style={ { color: '#ff0000', fontWeight: 'bold' } }> - PRO</span> }
                            </Checkbox>
                            <br/>
                            <br/>
                            <Text type="secondary" >
                                When you edit a post and upload an image, it will be renamed automatically based on the post title.
                            </Text>
                        </Form.Item>
                        <Divider />
                        <Form.Item label={<Title level={5} style={{margin: 0, fontSize: '14px'}}> Others Media Auto
                            Rename </Title>}>
                            <Checkbox
                                onChange={setDefaultText}
                                name={`enable_auto_rename`}
                                value={`enable_auto_rename`}
                                checked={'enable_auto_rename' === stateValue.options.enable_auto_rename}>
                                Custom text {!tsmltParams.hasExtended &&
                                <span style={{color: '#ff0000', fontWeight: 'bold'}}> - PRO</span>}
                            </Checkbox>
                            <br/>
                            <br/>
                            <Text type="secondary">
                                Auto rename will apply automatically when upload Media file. File name will be unique by
                                incremental number. Example: file-name.jpg next one file-name-1.jpg
                            </Text>
                            {tsmltParams.hasExtended && 'enable_auto_rename' === stateValue.options.enable_auto_rename &&
                                <>
                                    <Divider style={{margin: '10px 0'}}/>
                                    <Input
                                        type="primary"
                                        size="large"
                                        placeholder="file name"
                                        onChange={
                                            (event) => dispatch({
                                                type: Types.UPDATE_OPTIONS,
                                                options: {
                                                    ...stateValue.options,
                                                    media_auto_rename_text: event.target.value,
                                                }
                                            })
                                        }
                                        value={stateValue.options.media_auto_rename_text}
                                    />
                                    <Text type="secondary">
                                        <span style={{color: '#ff0000'}}>Required Field. Write file name without extension. Remember !! Empty Value will not apply. <br/> Example: File Name </span>
                                    </Text>
                                </>
                            }

                        </Form.Item>
                        <Divider style={{margin: '10px 0'}}/>
                    </Content>
                }

            </Form>
            <Button
                type="primary"
                size="large"
                style={{
                    position: 'fixed',
                    bottom: '100px',
                    right: '100px'
                }}
                onClick={ () => dispatch({
                    ...stateValue,
                    type: Types.UPDATE_OPTIONS,
                    saveType: Types.UPDATE_OPTIONS,
                }) } >
                Save Settings
            </Button>
        </Layout>
        </Layout>
    </>
    );
}

export default Settings;