import React from "react";

import {columns, defaultBulkSubmitData} from '../../Utils/UtilData';

import Loader from "../../Utils/Loader";

import {  Pagination, Table, Layout } from 'antd';

const { Content } = Layout;

import TheHeader from "./TheHeader";

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

import BulkModal from "./BulkModal";

import BulkModalForCSV from "./BulkModalForCSV";

import MainHeader from "../MainHeader";
import SearchUsesModal from "./SearchUsesModal";

export default function Datatable() {

    const [stateValue, dispatch] = useStateValue();

    const handlePagination = ( current ) => {
        dispatch({
            type: Types.GET_MEDIA_LIST,
            mediaData: {
                ...stateValue.mediaData,
                isLoading: true,
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
    
    const renderModal = () => {
        if (stateValue.searchUses.isModalOpen){
            return <SearchUsesModal />
        } else if (stateValue.bulkSubmitData.isModalOpen) {
            return <BulkModal />
        } else if (stateValue.bulkExport.isModalOpen) {
            return <BulkModalForCSV />
        }
        return null;
    };
    // optionsData
    return (
        <>
            <MainHeader/>
            <Layout className="layout" >
                <TheHeader/>
                { stateValue.generalData.isLoading || stateValue.mediaData.isLoading ?  <Loader/>  :
                    <>
                    <Content>
                        <Table
                            rowKey={(item) => item.ID}
                            pagination={false}
                            columns={ tablecolumn }
                            dataSource={ stateValue.mediaData.posts }
                            scroll={{
                                x: 1300
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
                    { renderModal() }
                    </>
                }
            </Layout>
    </>);
}

