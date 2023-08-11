import React, {useEffect} from "react";

import {useStateValue} from "../../Utils/StateProvider";

import RubbishHeader from "./RubbishHeader";

import Loader from "../../Utils/Loader";

import {Layout, Pagination, Table} from "antd";

const { Content } = Layout;

import * as Types from "../../Utils/actionType";

import { RubbishFileColumns} from "../../Utils/UtilData";

import { getRubbishFile } from "../../Utils/Data";

import DirectoryModal from "./DirectoryModal";

import RubbishNotice from "./RubbishNotice";

function RubbishFile() {

    const [stateValue, dispatch] = useStateValue();

    const getTheRubbishFile = async () => {
        const rubbishFile = await getRubbishFile( stateValue.rubbishMedia.postQuery );
        await dispatch({
            type: Types.RUBBISH_MEDIA,
            rubbishMedia:{
                ...stateValue.rubbishMedia,
                isLoading: false,
                mediaFile: rubbishFile.mediaFile,
                paged: rubbishFile.paged,
                totalPost: rubbishFile.totalPost,
                postsPerPage: rubbishFile.postsPerPage
            }
        });
        console.log( 'getTheRubbishFile' );
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
            bulkRubbishData: {
                ...stateValue.bulkRubbishData,
                bulkChecked: false,
                ids: [],
            },
        });
    }

    const rubbishColumns = RubbishFileColumns();

    useEffect(() => {
        getTheRubbishFile();
    }, [stateValue.rubbishMedia.postQuery, stateValue.saveType ] );

    return (
        <Layout className="layout">

            <RubbishHeader />
            <Content>
                { stateValue.rubbishMedia.isLoading ? <Loader/>  :
                    <>
                        <Table
                            rowKey={(item) => (Math.random() + 1).toString(36).substring(7) }
                            pagination={false}
                            columns={ rubbishColumns }
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
                    </>
                }
                <DirectoryModal />
            </Content>
            <RubbishNotice/>

        </Layout>
    )
}
export default RubbishFile;