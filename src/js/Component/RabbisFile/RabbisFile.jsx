import React, {useEffect} from "react";

import {useStateValue} from "../../Utils/StateProvider";

import RabbisHeader from "../RabbisFile/RabbisHeader";

import Loader from "../../Utils/Loader";

import {Layout, Typography} from "antd";
const { Content } = Layout;
const { Title, Paragraph  } = Typography;
import * as Types from "../../Utils/actionType";

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


    useEffect(() => {
        getRabbisFile();
    }, [] );

    //Types.RUBBISH_MEDIA
    return (
        <Layout className="layout">
            { stateValue.rubbishMedia.isLoading ? <Loader/>  :
                <RabbisHeader />
            }
            <Content style={{
                padding: '150px',
                background: 'rgb(255 255 255 / 35%)',
                borderRadius: '5px',
                boxShadow: 'rgb(0 0 0 / 1%) 0px 0 20px',
            }}>
                <Title level={2} > Announcement: Exciting Upcoming Feature - "Rabbis File Finder" . </Title>
            </Content>
        </Layout>
    )
}
export default RabbisFile;