
import React, {useState, useEffect, useContext} from "react";

import { TheAppContext, TheMediaTableContext } from '../../Utils/TheContext';

import RenamerTableData from "./RenamerTableData";


import {
    getMedia
} from "../../Utils/Data";

import {
    defaultPosts,
    defaultPostsFilter,
    defaultBulkSubmitData,
    defaultPostsQuery
} from '../../Utils/UtilData'

function ProcessRenamerTableData() {
    const {
        isUpdated,
        setIsUpdated
    } = useContext( TheAppContext );

    const [data, setData] = useState( defaultPosts );

    const [postQuery, setPostQuery] = useState( defaultPostsQuery );

    const { posts, total_post, posts_per_page, paged } = data;

    // console.log( posts );

    const getTheMedia = async () => {
        const response = await getMedia('', {
            ...postQuery
        } );
        setData( response );
    }


    useEffect(() => {
        getTheMedia();
    }, [isUpdated]  );

    return (
        <TheMediaTableContext.Provider value={ { posts } }>
             <RenamerTableData/>
        </TheMediaTableContext.Provider>
    );
}

export default ProcessRenamerTableData;