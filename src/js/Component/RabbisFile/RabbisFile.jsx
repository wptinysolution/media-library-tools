import React, {useEffect} from "react";

import {useStateValue} from "../../Utils/StateProvider";

import RabbisHeader from "../RabbisFile/RabbisHeader";

import Loader from "../../Utils/Loader";

import {Layout, Pagination, Table} from "antd";

const { Content } = Layout;

import * as Types from "../../Utils/actionType";

import { RabbisFileColumns} from "../../Utils/UtilData";

import { getRabbisFile} from "../../Utils/Data";

import DirectoryModal from "./DirectoryModal";

function RabbisFile() {

    const [stateValue, dispatch] = useStateValue();

    const getTheRabbisFile = async () => {
        const rabbisFile = await getRabbisFile( stateValue.rubbishMedia.postQuery );
        console.log( rabbisFile.mediaFile )
        await dispatch({
            type: Types.RUBBISH_MEDIA,
            rubbishMedia:{
                ...stateValue.rubbishMedia,
                isLoading: false,
                mediaFile: rabbisFile.mediaFile,
                paged: rabbisFile.paged,
                totalPost: rabbisFile.totalPost,
                postsPerPage: rabbisFile.postsPerPage
            }
        });
        console.log( 'getRabbisFile' );
    }

    const handlePagination = ( current ) => {
        dispatch({
            type: Types.RUBBISH_MEDIA,
            rubbishMedia: {
                ...stateValue.rubbishMedia,
                postQuery:{
                    ...stateValue.rubbishMedia.postQuery,
                    paged: current,
                    isQueryUpdate: true
                }
            },
        });
        dispatch({
            type: Types.BALK_RUBBISH,
            bulkRabbisData: {
                ...stateValue.bulkRabbisData,
                bulkChecked: false,
                ids: [],
            },
        });
    }

    const rabbisColumns = RabbisFileColumns();

    useEffect(() => {
        getTheRabbisFile();
    }, [stateValue.rubbishMedia.postQuery] );

    return (
        <Layout className="layout">
            { stateValue.rubbishMedia.isLoading ? <Loader/>  :
            <>
                <RabbisHeader />
                <Content>
                    <Table
                        rowKey={(item) => (Math.random() + 1).toString(36).substring(7) }
                        pagination={false}
                        columns={ rabbisColumns }
                        dataSource={ stateValue.rubbishMedia.mediaFile }
                        scroll={{
                            x: 1300,
                        }}
                    />
                    <Pagination
                        style={{ padding: '30px', textAlign: 'right'  }}
                        showTitle={true}
                        showSizeChanger={false}
                        showQuickJumper={true}
                        showTotal={(total, range) => `${range[0]}-${range[1]} of ${total} items`}
                        total={ stateValue.rubbishMedia.totalPost }
                        pageSize={ stateValue.rubbishMedia.postsPerPage }
                        current={ stateValue.rubbishMedia.paged }
                        onChange={(current) => handlePagination(current)}
                    />
                    <DirectoryModal />
                </Content>

            </>
            }
        </Layout>
    )
}
export default RabbisFile;