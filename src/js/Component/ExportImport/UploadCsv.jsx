import React, { useEffect, useState } from 'react';

import {useStateValue} from "../../Utils/StateProvider";
import {Button, Upload} from "antd";
import {UploadOutlined} from "@ant-design/icons";
import {fileUpload } from "../../Utils/Data";

const buttonStyle = {
    width: '280px',
    height: '70px',
    fontSize: '25px',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: '5px'
}

/**
 *
 * @returns {JSX.Element}
 * @constructor
 */
function UploadCsv() {

    const [stateValue, dispatch] = useStateValue();

    const uploadProps = {
        name: 'file',
        action: `${tsmltParams.restApiUrl}wp/v2/media`, // Replace with your API endpoint
        headers: {
            'X-WP-Nonce': tsmltParams.rest_nonce // You can add any custom headers here
        },
        onChange(info) {
            console.log( info.file );
        },
    };

    return (
        <Upload {...uploadProps} >
            <Button
                icon={<UploadOutlined />}
                style={
                    {
                        ...buttonStyle,
                        marginLeft: 'auto',
                        marginRight: 'auto',
                    }
                }
            >Upload CSV File</Button>
        </Upload>
    );
}

export default UploadCsv;