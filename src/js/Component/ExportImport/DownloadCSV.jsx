import React from 'react';
import { CSVLink } from "react-csv";
import {useStateValue} from "../../Utils/StateProvider";
import {Button} from "antd";

const buttonStyle = {
    width: '200px',
    height: '70px',
    fontSize: '25px',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: '5px'
}

function DownloadCSV() {

    const [stateValue, dispatch] = useStateValue();

    const headers = [
        { label: "First Name", key: "firstname" },
        { label: "Last Name", key: "lastname" },
        { label: "Email", key: "email" }
    ];

    const data = [
        { firstname: "Ahmed", lastname: "Tomi", email: "ah@smthing.co.com" },
        { firstname: "Raed", lastname: "Labes", email: "rl@smthing.co.com" },
        { firstname: "Yezzi", lastname: "Min l3b", email: "ymin@cocococo.com" }
    ];

    return (
        <CSVLink data={data} headers={headers}>
            <Button
                style={
                    {
                        ...buttonStyle,
                        marginLeft: 'auto',
                        marginRight: 'auto',
                    }
                }
                type="primary"
                size={`large`}
            >
                Download CSV
            </Button>
        </CSVLink>
    );
}

export default DownloadCSV;