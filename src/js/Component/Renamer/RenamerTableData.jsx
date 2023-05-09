
import React from "react";

import { Layout, Pagination, Table } from "antd";

import { renamerColumns } from '../../Utils/UtilData'

import RenamerMainHeader from "./RenamerMainHeader";

import { useStateValue } from "../../Utils/StateProvider";

import Loader from "../../Utils/Loader";

import * as Types from "../../Utils/actionType";

const { Content } = Layout;

function RenamerTableData() {

    const [ stateValue, dispatch ] = useStateValue();

    const RenameTableColumns = renamerColumns();

    const handlePagination = ( current ) => {
        dispatch({
            type: Types.GET_MEDIA_LIST,
            mediaData: {
                ...stateValue.mediaData,
                postQuery : {
                    ...stateValue.mediaData.postQuery,
                    paged : current,
                    orderby: 'id',
                    order: 'DESC'
                }
            },
        })
    }

    return (
            <Layout className="layout">
                <RenamerMainHeader/>
                { stateValue.mediaData.isLoading || ! stateValue.mediaData.total_post > 0 ?  <Loader/> :
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
                            total={ stateValue.mediaData.total_post }
                            pageSize={ stateValue.mediaData.posts_per_page }
                            current={ stateValue.mediaData.paged }
                            onChange={ ( current ) => handlePagination( current ) }
                        />
                    </Content>
                }
            </Layout>
    );
}

export default RenamerTableData;

