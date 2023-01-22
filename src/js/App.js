
import axios from "axios";
import React, { useState, useEffect } from "react";

import Table from "./Component/Table";

function App() {
    /*
      - Columns is a simple array right now, but it will contain some logic later on. It is recommended by react-table to memoize the columns data
      - Here in this example, we have grouped our columns into two headers. react-table is flexible enough to create grouped table headers
    */
    const columns = [
            {
                Header: "Id",
                Width: "20px",
            },
            {
                Header: "Image",
                Width: "50px",
            },
            {
                Header: "Title",
                Width: "150",
            },

            {
                Header: "Alt",
                Width: "150",
            },
            {
                Header: "Caption",
                Width: "150",
            },
            {
                Header: "Description",
                Width: "150",
            },
        ];
    // data state to store the TV Maze API data. Its initial value is an empty array
    const [data, setData] = useState([]);
    const [params, setParams] = useState('');
    const apibaseUrl = `${tttemeParams.restApiUrl}/TheTinyTools/ME/v1/media/${params}`;
    // console.log( apibaseUrl)
    // Using useEffect to call the API once mounted and set the data
    useEffect(() => {
        (async () => {
            const additonal_data = {
                'current_user' : tttemeParams.current_user
            }
            const result = await axios.get(apibaseUrl, { params: { ...additonal_data } });
            setData( JSON.parse( result.data ) );
        })();
    }, []);

    return (
        <div className="tttme-App">
            <Table columns={columns} data={data} />
        </div>
    );
}
export default App