
import axios from "axios";
import React, { useState, useEffect, useContext } from "react";

import Table from "./Component/Table";
import SystemContext from './SystemContext';
import {get_data} from "./Utils/Url";

function App() {
    const  { columns }  = useContext( SystemContext );
    // data state to store the TV Maze API data. Its initial value is an empty array
    const [data, setData] = useState();
    const [params, setParams] = useState('');
    const getData = async () => {
        const response = await get_data( params )
        setData( response );
    }

    useEffect(() => {
        getData()
    }, [params]);

    return (
        <div className="tttme-App">
            { data && <Table columns={columns} data={data} /> }
        </div>
    );
}
export default App