
import React, {useContext, useState} from "react";

import {TheAppContext, TheMediaTableContext} from '../../Utils/TheContext';

import {LoadingOutlined} from "@ant-design/icons";

import {Layout, Pagination, Spin, Space, Table} from "antd";

const {
    Content,
} = Layout;

import {
    renamerColumns
} from '../../Utils/UtilData'

import RenamerMainHeader from "./RenamerMainHeader";

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

function RenamerTableData() {

    const { handlePagination } = useContext( TheMediaTableContext );

    const [stateValue, dispatch] = useStateValue();

    const RenameTableColumns = renamerColumns();
    // console.log( stateValue )
    const antIcon = <LoadingOutlined style={{ fontSize: 24 }} spin />;

    return (
            <Layout className="layout">
                <RenamerMainHeader/>
                { stateValue.mediaData.isLoading || ! stateValue.mediaData.total_post > 0 ?
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
                            dataSource={ stateValue.mediaData.posts }
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
                            total={stateValue.mediaData.total_post}
                            pageSize={stateValue.mediaData.posts_per_page}
                            current={stateValue.mediaData.paged}
                            onChange={(current) => handlePagination(current)}
                        />
                    </Content>
                }
            </Layout>
    );
}

export default RenamerTableData;

