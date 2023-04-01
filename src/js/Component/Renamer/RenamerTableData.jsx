
import React, {useContext} from "react";

import { TheMediaTableContext } from '../../Utils/TheContext';

import {LoadingOutlined} from "@ant-design/icons";

import {Layout, Pagination, Spin, Space, Table} from "antd";

const {
    Content,
} = Layout;

import {
    renamerColumns
} from '../../Utils/UtilData'

import RenamerMainHeader from "./RenamerMainHeader";

function RenamerTableData() {

    const {
        posts,
        formEdited,
        handleFocusout,
        handleChange,
        paged,
        isLoading,
        total_post,
        posts_per_page,
        handlePagination,
    } = useContext( TheMediaTableContext );


    const RenameTableColumns = renamerColumns(
        formEdited,
        handleFocusout,
        handleChange
    );

    const antIcon = <LoadingOutlined style={{ fontSize: 24 }} spin />;

    return (
            <Layout className="layout">
                <RenamerMainHeader/>
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
                            columns={ RenameTableColumns }
                            dataSource={ posts }
                            scroll={{
                                x: 1300,
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

export default RenamerTableData;

