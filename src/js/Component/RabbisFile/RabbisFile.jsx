import React, {useEffect} from "react";

import {useStateValue} from "../../Utils/StateProvider";

import RabbisHeader from "../RabbisFile/RabbisHeader";

import Loader from "../../Utils/Loader";

import { Layout } from "antd";

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

        </Layout>
    )
}
export default RabbisFile;