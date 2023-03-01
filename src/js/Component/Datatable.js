import React, {useState, useEffect, useRef , useContext } from "react";

import { TheContext } from '../Utils/TheContext';

const { TextArea } = Input;

import {LoadingOutlined} from "@ant-design/icons";

import {
    Pagination,
    Table,
    Input,
    Modal,
    Checkbox,
    Select,
    Layout,
    Button,
    Space,
    Typography,
    Spin,
    Divider
} from 'antd';

const {
    Content,
    Footer
} = Layout;


import TheHeader from "./TheHeader";


export default function DataTable() {

    const {
        posts,
        paged,
        isLoading,
        total_post,
        bulkChecked,
        onBulkCheck,
        checkedData,
        handleChange,
        handleFocusout,
        posts_per_page,
        handleSortClick,
        handlePagination,
        onCheckboxChange,
        formEdited,
    } = useContext( TheContext );

    const antIcon = <LoadingOutlined style={{ fontSize: 24 }} spin />;
    const columns = [
        {
            title: <Checkbox checked={ bulkChecked } onChange={onBulkCheck}/>,
            key: 'ID',
            dataIndex: 'ID',
            width: '50px',
            align: 'center',
            render:  ( id, record ) => <Checkbox checked={ -1 !== checkedData.indexOf( id ) } name="item_id" value={id} onChange={onCheckboxChange} />
        },
        {
            title: <Space wrap> { `ID` } <Button size={`small`} sort-by={`id`} onClick={ () => handleSortClick( 'id' )}> {`Sort`} </Button> </Space>,
            key: 'ID',
            dataIndex: 'ID',
            width: '105px',
            align: 'top'
        },
        {
            title: 'File',
            key: 'Image',
            dataIndex: 'guid',
            width: '130px',
            align: 'top',
            render:  ( text, record ) =>  <img width={`80`} src={text}  />,
        },
        {
            title: <Space wrap> { `Title` } <Button size={`small`} onClick={ () => handleSortClick( 'title' )} > Sort </Button> </Space>,
            key: 'Title',
            dataIndex: 'post_title',
            align: 'top',
            width: '300px',
            render: ( text, record, i ) => <> { formEdited ? <TextArea name={`post_title`} placeholder={`Title Shouldn't leave empty`} current={i} onBlur={handleFocusout}  onChange={handleChange} value={ text } /> : text }   </>
        },
        {
            title: <Space wrap> { `Alt` } <Button size={`small`} onClick={ () => handleSortClick( 'alt' )}> Sort </Button> </Space>,
            key: 'Alt',
            dataIndex: 'alt_text',
            align: 'top',
            width: '300px',
            render: ( text, record, i ) => <> { formEdited ? <TextArea name={`alt_text`} placeholder={`Alt Text Shouldn't leave empty`} current={i} onBlur={handleFocusout}  onChange={handleChange} value={ text } /> : text }   </>
        },
        {
            title: <Space wrap> { `Caption` } <Button size={`small`} onClick={   () => handleSortClick( 'caption' ) }> Sort </Button> </Space>,
            key: 'Caption',
            dataIndex: 'post_excerpt',
            width: '300px',
            render: ( text, record, i ) => <> { formEdited ? <TextArea name={`post_excerpt`} placeholder={`Caption Text`} current={i} onBlur={handleFocusout}  onChange={handleChange} value={ text } /> : text }   </>
        },
        {
            title: <Space wrap> { `Description` } <Button size={`small`} onClick={  () => handleSortClick( 'description' ) }> Sort </Button> </Space>,
            key: 'Description',
            dataIndex: 'post_content',
            width: '350px',
            render: ( text, record, i ) => <> { formEdited ? <TextArea name={`post_content`} placeholder={`Description Text`} current={i} onBlur={handleFocusout}  onChange={handleChange} value={ text } /> : text }   </>
        },
    ];



    return (
            <Layout className="layout">
                <TheHeader/>
                { isLoading || ! total_post > 0 ?
                    <Layout className="spain-icon" style={{height: "90vh", justifyContent: 'center'}}> <Spin indicator={antIcon}/></Layout>
                    : <>
                        <Content>
                            <Table
                                rowKey={(item) => item.ID}
                                pagination={false}
                                columns={columns}
                                dataSource={posts}
                                scroll={{
                                    x: 1300,
                                }}
                            />
                        </Content>
                        <Footer style={{textAlign: 'right'}}>
                            <Pagination
                                showTitle={true}
                                showSizeChanger={false}
                                showQuickJumper={true}
                                showTotal={(total, range) => `${range[0]}-${range[1]} of ${total} items`}
                                total={total_post}
                                pageSize={posts_per_page}
                                current={paged}
                                onChange={(current) => handlePagination(current)}
                            />
                        </Footer>
                    </>
                }

            </Layout>
    );
}

