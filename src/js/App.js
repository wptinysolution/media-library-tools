
import axios from "axios";
import React, { useMemo, useState, useEffect } from "react";

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

    // Using useEffect to call the API once mounted and set the data
    useEffect(() => {
        (async () => {
            const result = await axios("http://mediaedit.local/wp-json/wp/v2/media");
            setData(result.data);
        })();
    }, []);

    return (
        <div className="tttme-App">
            <Table columns={columns} data={data} />
        </div>
    );
}
export default App