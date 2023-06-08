import React, {useEffect} from "react";

import {useStateValue} from "../../Utils/StateProvider";

import RabbisHeader from "../RabbisFile/RabbisHeader";

import Loader from "../../Utils/Loader";

import { Layout, Table } from "antd";

const { Content } = Layout;

import * as Types from "../../Utils/actionType";

import { RabbisFileColumns } from "../../Utils/UtilData";

const  posts = [
    {
        "post_title": "blog32",
        "post_name": "blog-32",
        "uploaddir": "http://woo-cpt.local/wp-content/uploads",
        "alt_text": "",
        "post_mime_type": "image/jpeg"
    },
    {
        "post_title": "blog32",
        "post_name": "blog-32",
        "uploaddir": "http://woo-cpt.local/wp-content/uploads",
        "alt_text": "",
        "post_mime_type": "image/jpeg"
    }
];


function RabbisFile() {

    const [stateValue, dispatch] = useStateValue();

    const getRabbisFile = async () => {
        await dispatch({
            type: Types.RUBBISH_MEDIA,
            rubbishMedia:{
                isLoading: false,
            }
        });
        console.log( 'getRabbisFile' );
    }

    const rabbisColumns = RabbisFileColumns();

    useEffect(() => {
        getRabbisFile();
    }, [] );

    return (
        <Layout className="layout">
            { stateValue.rubbishMedia.isLoading ? <Loader/>  :
                <RabbisHeader />
            }
            <Content>
                <Table
                    rowKey={(item) => (Math.random() + 1).toString(36).substring(7) }
                    pagination={false}
                    columns={ rabbisColumns }
                    dataSource={ posts }
                    scroll={{
                        x: 1300,
                        y: 900,
                    }}
                />
            </Content>
        </Layout>
    )
}
export default RabbisFile;