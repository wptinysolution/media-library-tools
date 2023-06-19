import React, {useEffect} from "react";

import {useStateValue} from "../../Utils/StateProvider";

import RabbisHeader from "../RabbisFile/RabbisHeader";

import Loader from "../../Utils/Loader";

import { Layout, Table } from "antd";

const { Content } = Layout;

import * as Types from "../../Utils/actionType";

import { RabbisFileColumns } from "../../Utils/UtilData";
import { getRabbisFile } from "../../Utils/Data";

function RabbisFile() {

    const [stateValue, dispatch] = useStateValue();

    const getTheRabbisFile = async () => {
        const rabbisFile = await getRabbisFile();
        await dispatch({
            type: Types.RUBBISH_MEDIA,
            rubbishMedia:{
                ...stateValue.rubbishMedia,
                bulkChecked: true,
                isLoading: false,
                mediaFile: rabbisFile,
            }
        });
        console.log( 'getRabbisFile' );
    }

    const rabbisColumns = RabbisFileColumns();

    useEffect(() => {
        getTheRabbisFile();
    }, [] );

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
                            y: 900,
                        }}
                    />
                </Content>
            </>
            }
        </Layout>
    )
}
export default RabbisFile;