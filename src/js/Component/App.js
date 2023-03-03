
import React, {useState, useEffect } from "react";

import { TheAppContext } from '../Utils/TheContext';

import ProcessTableData from "./ListTable/ProcessTableData";

import {
    getTerms,
    getDates,
    getOptions,
    updateOptins,
} from "../Utils/Data";

function App() {

    const [dateList, setDateList] = useState( [] );

    const [termsList, setTermsList] = useState( [] );

    const [optionsData, setOptionsData] = useState( [] );

    const [isUpdated, setIsUpdated] = useState(false );

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

    const handleUpdateOption = async ( event ) => {
        const response = await updateOptins( optionsData );
        200 === parseInt( response.status ) && setIsUpdated( ! isUpdated );
    }

    useEffect(() => {
        getDateList();
        getTermsList();
        getTheOptins();
    }, []  );

    return (
        <TheAppContext.Provider value={ {
            dateList,
            termsList,
            optionsData,
            setOptionsData,
            handleUpdateOption,
            isUpdated,
            setIsUpdated
        } }>
            <div className="tttme-App">
                 <ProcessTableData/>
            </div>
        </TheAppContext.Provider>
    );
}
export default App