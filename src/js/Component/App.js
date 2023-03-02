
import React, {useState, useEffect } from "react";

import { TheContext } from '../Utils/TheContext';

import ProcessTableData from "./ListTable/ProcessTableData";


import {
    getTerms,
    getDates,
    getOptions,
} from "../Utils/Data";


function App() {

    const [dateList, setDateList] = useState( [] );

    const [termsList, setTermsList] = useState( [] );

    const [optionsData, setOptionsData] = useState( [] );

    const getDateList = async () => {
        const response = await getDates();
        const preparedData =  JSON.parse( response.data );
        setDateList( preparedData );
    }

    const getTermsList = async () => {
        const response = await getTerms();
        const preparedData =  JSON.parse( response.data );
        setTermsList( preparedData );
    }

    const getTheOptins = async () => {
        const response = await getOptions();
        const preparedData =  JSON.parse( response.data );
        setOptionsData( preparedData );
    }

    useEffect(() => {
        getDateList();
        getTermsList();
        getTheOptins();
    }, []  );

    return (
        <TheContext.Provider value={ { dateList, termsList, optionsData } }>
            <div className="tttme-App">
                 <ProcessTableData/>
            </div>
        </TheContext.Provider>
    );
}
export default App