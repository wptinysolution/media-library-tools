import React from "react";

import {columns, defaultBulkSubmitData} from '../../Utils/UtilData';

import Loader from "../../Utils/Loader";

import {  Pagination, Table, Layout } from 'antd';

const { Content } = Layout;

import TheHeader from "./TheHeader";

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

import BulkModal from "./BulkModal";

export default function Datatable() {

    const [stateValue, dispatch] = useStateValue();

    const handlePagination = ( current ) => {
        dispatch({
            type: Types.GET_MEDIA_LIST,
            mediaData: {
                ...stateValue.mediaData,
                postQuery : {
                    ...stateValue.mediaData.postQuery,
                    paged : current
                }
            },
        });
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: defaultBulkSubmitData,
        });
    }

    const thecolumn = columns();
    const tablecolumn = thecolumn.filter( ( currentValue) => {
        if( ! stateValue.options.media_table_column || 'CheckboxID' === currentValue.key ){
            return true;
        }
        return stateValue.options.media_table_column.includes( `${currentValue.key}` );
    } );

    // optionsData
    return (
            <Layout className="layout">
                { stateValue.generalData.isLoading || stateValue.mediaData.isLoading || stateValue.mediaData.total_post < 0 ?  <Loader/>  :
                    <>
                    <TheHeader/>
                    <Content>
                        <Table
                            rowKey={(item) => item.ID}
                            pagination={false}
                            columns={ tablecolumn }
                            dataSource={ stateValue.mediaData.posts }
                            scroll={{
                                x: 1300,
                                y: 900,
                            }}
                        />
                        <Pagination
                            style={{ padding: '30px', textAlign: 'right'  }}
                            showTitle={true}
                            showSizeChanger={false}
                            showQuickJumper={true}
                            showTotal={(total, range) => `${range[0]}-${range[1]} of ${total} items`}
                            total={ stateValue.mediaData.total_post }
                            pageSize={ stateValue.mediaData.posts_per_page }
                            current={ stateValue.mediaData.paged }
                            onChange={(current) => handlePagination(current)}

                        />
                    </Content>
                    <BulkModal />
                    </>
                }
            </Layout>
    );
}

