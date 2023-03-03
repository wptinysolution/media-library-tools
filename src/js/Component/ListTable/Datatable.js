import React, { useContext } from "react";

import { TheMediaTableContext } from '../../Utils/TheContext';

import { columns } from '../../Utils/UtilData';

import {LoadingOutlined} from "@ant-design/icons";

import {
    Pagination,
    Table,
    Layout,
    Spin
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
        formEdited

    } = useContext( TheMediaTableContext );

    const antIcon = <LoadingOutlined style={{ fontSize: 24 }} spin />;

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
                                columns={ columns( bulkChecked, onBulkCheck, checkedData, onCheckboxChange, handleSortClick, formEdited, handleFocusout, handleChange ) }
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

