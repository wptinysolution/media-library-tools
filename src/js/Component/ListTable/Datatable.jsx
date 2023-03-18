import React, { useContext } from "react";

import {TheAppContext, TheMediaTableContext} from '../../Utils/TheContext';

import {columns, renamerColumns} from '../../Utils/UtilData';

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

export default function Datatable() {
    const {
        optionsData,
        selectedMenu
    } = useContext( TheAppContext );

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
        filtering,
        setFiltering,
        handleFilterData

    } = useContext( TheMediaTableContext );

    const antIcon = <LoadingOutlined style={{ fontSize: 24 }} spin />;

    const thecolumn = columns( bulkChecked, onBulkCheck, checkedData, onCheckboxChange, handleSortClick, formEdited, handleFocusout, handleChange, filtering,
        setFiltering, handleFilterData );
    const tablecolumn = thecolumn.filter( ( currentValue) => {
        if( ! optionsData.media_table_column || 'CheckboxID' === currentValue.key ){
            return true;
        }
        return optionsData.media_table_column.includes( `${currentValue.key}` );
    } );

    // optionsData
    return (
            <Layout className="layout">
                <TheHeader/>
                { isLoading || ! total_post > 0 ?
                    <Content className="spain-icon" style={{
                        height: "90vh",
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center'
                    }}> <Spin indicator={antIcon}/></Content>
                    :
                    <Content>

                        <Table
                            rowKey={(item) => item.ID}
                            pagination={false}
                            columns={ tablecolumn }
                            dataSource={posts}
                            scroll={{
                                x: 1300,
                                y: 900,
                            }}
                        />
                        <Pagination
                            style={{
                                padding: '30px',
                                textAlign: 'right'
                            }}
                            showTitle={true}
                            showSizeChanger={false}
                            showQuickJumper={true}
                            showTotal={(total, range) => `${range[0]}-${range[1]} of ${total} items`}
                            total={total_post}
                            pageSize={posts_per_page}
                            current={paged}
                            onChange={(current) => handlePagination(current)}
                        />
                    </Content>
                }
            </Layout>
    );
}

