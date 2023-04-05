
import React, {useState, useEffect, useContext} from "react";

import { TheAppContext, TheMediaTableContext } from '../../Utils/TheContext';

import RenamerTableData from "./RenamerTableData";


import {
    getMedia,
    upDateSingleMedia
} from "../../Utils/Data";

import {
    defaultPosts,
    defaultPostsQuery
} from '../../Utils/UtilData'

import {useStateValue} from "../../Utils/StateProvider";

import * as Types from "../../Utils/actionType";

function ProcessRenamerTableData() {
    const {
        isUpdated,
        setIsUpdated
    } = useContext( TheAppContext );

    const [stateValue, dispatch] = useStateValue();

    const [postQuery, setPostQuery] = useState( { ...defaultPostsQuery, orderby: 'id' } );

    const { posts, total_post, posts_per_page, paged } = stateValue.mediaData;

    const getTheMedia = async () => {
        const response = await getMedia('', postQuery);
        dispatch({
            type: Types.GET_MEDIA_LIST,
            isLoading: false,
            mediaData: response,
        })
    }

    /*
    const handleColumnEditMode = () => {
        setFormEdited( ! formEdited );
    }
    */

    const handleFocusout = async ( event ) => {
        let edited =  currentItemEdited.thefile.originalname && currentItemEdited.thefile.originalname.localeCompare( event.target.value );
        if( edited ){
            const response = await upDateSingleMedia( currentItemEdited );
            200 === parseInt( response.status ) && setIsUpdated( ! isUpdated );
            currentItemEdited.thefile.originalname = event.target.value;
        }
    }

    const handlePagination = ( current ) => {
        //setIsloading( true )
        dispatch({
            ...stateValue,
            type: Types.GET_MEDIA_LIST,
            isLoading: false,
        })

        setPostQuery({
            ...postQuery,
            paged: current
        })
        setIsUpdated( ! isUpdated );
    }

    useEffect(() => {
        getTheMedia();
    }, [isUpdated]  );
    console.log( stateValue )
    return (
        <TheMediaTableContext.Provider value={ {
            handleFocusout,
            handlePagination,
        } }>
             <RenamerTableData/>
        </TheMediaTableContext.Provider>
    );
}

export default ProcessRenamerTableData;